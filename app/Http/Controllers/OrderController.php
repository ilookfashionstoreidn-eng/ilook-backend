<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\OrderLogsExport; 
use App\Models\OrderItemSerial;
use App\Models\StokGudangProduk;
use App\Models\Sku;



class OrderController extends Controller
{
    public function showByTracking($trackingNumber)
    {
        $order = Order::with('items')
            ->where('tracking_number', $trackingNumber)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order tidak ditemukan'], 404);
        }

        if ($order->is_packed == '1'){
            return response()->json(['message' => 'Orderan sudah di packing'], 409);
        }

        return response()->json($order);
    }
  public function validateScan(Request $request, $trackingNumber)
    {
        try {
            $request->validate([
                'items' => 'required|array|min:1|max:1000',
                'items.*.sku' => 'required|string|max:255',
                'items.*.quantity' => 'required|integer|min:1|max:10000',
                'items.*.serials' => 'required|array|min:1',
                'items.*.serials.*' => 'required|string|min:1|max:255',
            ], [
                'items.required' => 'Data items tidak boleh kosong',
                'items.array' => 'Data items harus berupa array',
                'items.min' => 'Minimal harus ada 1 item',
                'items.*.sku.required' => 'SKU tidak boleh kosong',
                'items.*.quantity.required' => 'Quantity tidak boleh kosong',
                'items.*.quantity.integer' => 'Quantity harus berupa angka',
                'items.*.quantity.min' => 'Quantity minimal 1',
                'items.*.serials.required' => 'Nomor seri tidak boleh kosong',
                'items.*.serials.array' => 'Nomor seri harus berupa array',
                'items.*.serials.min' => 'Minimal harus ada 1 nomor seri',
                'items.*.serials.*.required' => 'Nomor seri tidak boleh kosong',
                'items.*.serials.*.string' => 'Nomor seri harus berupa string',
                'items.*.serials.*.min' => 'Nomor seri minimal 1 karakter',
                'items.*.serials.*.max' => 'Nomor seri maksimal 255 karakter',
                'items.max' => 'Maksimal 1000 items per request',
                'items.*.quantity.max' => 'Quantity maksimal 10000',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Data tidak valid',
                'errors' => $e->errors()
            ], 422);
        }

        $order = Order::with(['items', 'items.serials'])
            ->where('tracking_number', $trackingNumber)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order tidak ditemukan'], 404);
        }

        if ($order->is_packed) {
            return response()->json([
                'message' => 'Order ini sudah berstatus packed dan tidak bisa divalidasi ulang.'
            ], 422);
        }

        $expectedItems = $order->items->keyBy('sku');

        // Validasi semua item terlebih dahulu
        foreach ($request->items as $item) {

            $sku = $item['sku'];
            $qty = $item['quantity'];
            $serials = $item['serials'];

            if (!isset($expectedItems[$sku])) {
                return response()->json([
                    'message' => "SKU {$sku} tidak ada dalam order"
                ], 422);
            }

            if ($expectedItems[$sku]->quantity != $qty) {
                return response()->json([
                    'message' => "Quantity SKU {$sku} tidak cocok. Diharapkan {$expectedItems[$sku]->quantity}, tapi scan {$qty}"
                ], 422);
            }

            if (count($serials) != $qty) {
                return response()->json([
                    'message' => "Jumlah nomor seri SKU {$sku} harus {$qty} buah"
                ], 422);
            }


            if (empty($serials)) {
                return response()->json([
                    'message' => "Nomor seri SKU {$sku} tidak boleh kosong"
                ], 422);
            }
        }

        // OPTIMASI: Ambil semua SKU dan Stok sekaligus (batch query)
        $skuList = array_column($request->items, 'sku');
        $skuModels = Sku::whereIn('sku', $skuList)
            ->get()
            ->keyBy('sku');
        
        $skuIds = $skuModels->pluck('id')->toArray();
        $stokGudangList = StokGudangProduk::whereIn('sku_id', $skuIds)
            ->get()
            ->keyBy('sku_id');

        // Lakukan semua operasi dalam transaction
        try {
            DB::transaction(function () use ($request, $expectedItems, $order, $skuModels, $stokGudangList) {
                $allSerialsToInsert = [];
                $now = now();

                foreach ($request->items as $item) {
                    $sku = $item['sku'];
                    $serials = $item['serials'];

                    // Hapus serial lama
                    OrderItemSerial::where('order_item_id', $expectedItems[$sku]->id)->delete();

                    // Prepare batch insert untuk serial numbers
                    foreach ($serials as $serial) {
                        $allSerialsToInsert[] = [
                            'order_item_id' => $expectedItems[$sku]->id,
                            'serial_number' => $serial,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    // Kurangi stok gudang produk dengan lock untuk mencegah race condition
                    $skuModel = $skuModels[$sku] ?? null;
                    
                    if ($skuModel) {
                        // Gunakan lockForUpdate untuk mencegah race condition
                        $stokGudang = StokGudangProduk::where('sku_id', $skuModel->id)
                            ->lockForUpdate()
                            ->first();
                        
                        if (!$stokGudang) {
                            throw new \Exception("Stok gudang produk untuk SKU {$sku} tidak ditemukan");
                        }

                        // Validasi stok cukup
                        if ($stokGudang->qty < $item['quantity']) {
                            throw new \Exception("Stok gudang produk untuk SKU {$sku} tidak mencukupi. Stok tersedia: {$stokGudang->qty}, dibutuhkan: {$item['quantity']}");
                        }
                        
                        // Kurangi stok
                        $stokGudang->decrement('qty', $item['quantity']);
                    }
                }

                // Batch insert semua serial numbers sekaligus (lebih cepat)
                if (!empty($allSerialsToInsert)) {
                    // Chunk insert untuk menghindari query terlalu besar
                    $chunks = array_chunk($allSerialsToInsert, 500);
                    foreach ($chunks as $chunk) {
                        OrderItemSerial::insert($chunk);
                    }
                }

                // Update status order
                $order->update(['is_packed' => 1]);

                // Buat log
                OrderLog::create([
                    'order_id'     => $order->id,
                    'action'       => 'scan_validasi',
                    'performed_by' => Auth::user()->name ?? 'System',
                    'notes'        => 'Order berhasil discan dan divalidasi',
                ]);
            });

            return response()->json([
                'message' => 'Order berhasil divalidasi',
                'order' => $order->fresh(['items', 'items.serials']) 
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }

public function getAllLogs(Request $request)
    {
        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : null;

        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : null;

        $status = $request->input('status');

        $tracking = $request->input('tracking_number');

        $performedBy = $request->input('performed_by');

         $logs = OrderLog::with([
        'order' => function ($q) {
            $q->select('id', 'order_number', 'tracking_number', 'status', 'total_amount')
            ->with([
                'items:id,order_id,sku,quantity',  
                'items.serials:id,order_item_id,serial_number'
            ])
            ->withCount('items as total_items');
        }
        ])
        ->when($startDate && $endDate, function ($q) use ($startDate, $endDate) {
            $q->whereBetween('created_at', [$startDate, $endDate]);
        })
        ->when($status, function ($q) use ($status) {
            $q->whereHas('order', function ($sub) use ($status) {
                $sub->whereRaw('LOWER(status) = ?', [strtolower($status)]);
            });
        })
        ->when ($tracking, function ($q) use ($tracking) {
            $q->whereHas('order', function ($sub) use ($tracking) {
                $sub->where('tracking_number', 'LIKE', "%{$tracking}%");
            });
        })
       ->when($performedBy, function ($q) use ($performedBy) {
           $q->where('performed_by', 'LIKE', "%{$performedBy}%");

        })
        ->orderBy('created_at', 'desc')
        ->paginate(20);

        return response()->json($logs);
    }

        

    public function getSummaryReport(Request $request)
    {
        $startDate = $request->input('start_date')
            ? \Carbon\Carbon::parse($request->input('start_date'))->startOfDay()
            : now()->startOfDay();

        $endDate = $request->input('end_date')
            ? \Carbon\Carbon::parse($request->input('end_date'))->endOfDay()
            : now()->endOfDay();

        $action = $request->input('action');
        $status = $request->input('status');
        $tracking = $request->input('tracking_number');
        $performedBy = $request->input('performed_by');

        $query = DB::table('order_logs')
            ->join(DB::raw('`order`'), 'order.id', '=', 'order_logs.order_id')
            ->leftJoin('order_items', 'order_items.order_id', '=', 'order.id')
            ->selectRaw('
                COUNT(DISTINCT `order`.id) as total_order,
                SUM(order_items.quantity) as total_items,
                SUM(`order`.total_amount) as total_amount
            ')
            ->whereBetween('order_logs.created_at', [$startDate, $endDate]);

        if ($status) {
            $query->whereRaw('LOWER(`order`.status) = ?', [strtolower($status)]);
        }
        
        if ($tracking) {
            $query->where('order.tracking_number', 'LIKE', "%{$tracking}%");
        }

        if ($performedBy) {
            $query->where('order_logs.performed_by', $performedBy);
        }

        $report = $query->get();

        $kasirSummary = DB::table('order_logs')
            ->join(DB::raw('`order`'), 'order.id', '=', 'order_logs.order_id')
            ->whereBetween('order_logs.created_at', [$startDate, $endDate])
            ->select('order_logs.performed_by', DB::raw('COUNT(*) as total_orders'))
            ->groupBy('order_logs.performed_by')
            ->get();


        return response()->json([
            'message' => 'Summary report berhasil diambil',
            'filters' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                 'status'     => $status ?? 'Semua',
                 'tracking_number' => $tracking ?? 'Semua',
                 'performed_by' => $performedBy ?? 'Semua',
            ],
            'data' => $report,
            'kasir_summary' => $kasirSummary 
        ]);
    }

    public function exportLogsToExcel(Request $request)
    {
        $startDate = $request->input('start_date')
            ? \Carbon\Carbon::parse($request->input('start_date'))->startOfDay()
            : now()->startOfDay();

        $endDate = $request->input('end_date')
            ? \Carbon\Carbon::parse($request->input('end_date'))->endOfDay()
            : now()->endOfDay();

        $action = $request->input('action');

        $fileName = 'order_logs_' . $startDate->format('Ymd') . '_to_' . $endDate->format('Ymd') . '.xlsx';

        return Excel::download(new OrderLogsExport($startDate, $endDate, $action), $fileName);
    }
public function pickingQueue(){
    $orders = Order::where('label_print_status', 'printed')
        ->whereNull('picked_at')
        ->orderBy('label_print_time', 'asc')
        ->get();

        return response()->json($orders);
}

public function markPicked($id){
    $order = Order::findOrFail($id);

    $order->update([
        'picked_at' => now()
    ]);

    return response()->json(['message' => 'Order marked as picked']);
}

public function batchMarkPicked(Request $request)
{
    $limit = $request->input('limit', 1);

    // 1. Ambil N order teratas yang belum dipick
    $orders = Order::where('label_print_status', 'printed')
        ->whereNull('picked_at')
        ->orderBy('label_print_time', 'asc')
        ->take($limit)
        ->get();

    if ($orders->isEmpty()) {
        return response()->json(['message' => 'Tidak ada orderan untuk diproses'], 404);
    }

    // 2. Update picked_at
    Order::whereIn('id', $orders->pluck('id'))->update([
        'picked_at' => now()
    ]);

    // 3. Hitung Summary SKU
    $summary = [];
    foreach ($orders as $order) {
        foreach ($order->items as $item) {
            $sku = $item->sku;
            $qty = $item->quantity;

            if (isset($summary[$sku])) {
                $summary[$sku] += $qty;
            } else {
                $summary[$sku] = $qty;
            }
        }
    }
    
    // Sort summary by key (SKU name)
    ksort($summary);

    // 4. Return data untuk PDF
    return response()->json([
        'message' => count($orders) . ' orderan, berhasil diproses',
        'processed_orders' => $orders->pluck('order_number'),
        'summary' => $summary,
        'timestamp' => now()->format('d-m-Y H:i:s')
    ]);
}
}

