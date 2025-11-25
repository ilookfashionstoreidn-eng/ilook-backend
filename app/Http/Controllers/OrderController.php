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
        $request->validate([
            'items' => 'required|array',
            'items.*.sku' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.serials' => 'required|array',
           'items.*.serials.*' => 'required|string|min:1',

        ]);

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

            if (count($serials) !== count(array_unique($serials))) {
                return response()->json([
                    'message' => "Nomor seri SKU {$sku} ada yang duplikat"
                ], 422);
            }

            if (empty($serials)) {
                return response()->json([
                    'message' => "Nomor seri SKU {$sku} tidak boleh kosong"
                ], 422);
            }


            OrderItemSerial::where('order_item_id', $expectedItems[$sku]->id)->delete();

            foreach ($serials as $serial) {
                OrderItemSerial::create([
                    'order_item_id' => $expectedItems[$sku]->id,
                    'serial_number' => $serial,
                ]);
            }
        }

        $order->update(['is_packed' => 1]);

        OrderLog::create([
            'order_id'     => $order->id,
            'action'       => 'scan_validasi',
            'performed_by' => Auth::user()->name ?? 'System',
            'notes'        => 'Order berhasil discan dan divalidasi',
        ]);

        return response()->json([
            'message' => 'Order berhasil divalidasi',
            'order' => $order->fresh(['items', 'items.serials']) 
        ]);
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


}
