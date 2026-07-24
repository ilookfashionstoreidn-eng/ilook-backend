<?php

namespace App\Http\Controllers;

use App\Exports\OrderLogsExport;
use App\Jobs\GeneratePackingLogExport;
use App\Models\PackingLogExport;
use App\Models\GudangProdukActivityLog;
use App\Models\GudangProdukWorkspaceStockEntry;
use App\Models\SyncLog;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderLog;
use App\Services\PackingLogReportService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\OrderItemSerial;
use App\Models\Sku;
use Illuminate\Support\Facades\Storage;
use App\Traits\TracksWarehouseSerials;

class OrderController extends Controller
{
    use TracksWarehouseSerials;

    /**
     * Monitoring orders masuk via webhook Ginee - untuk halaman /orderPacking.
     * Hanya tampilkan order yang relevan untuk packing: READY_TO_SHIP atau PRINTED.
     * (Semua status kini tersimpan di DB, filter dilakukan di sini.)
     */
    public function webhookOrders(Request $request)
    {
        $date       = $request->query('date', Carbon::today()->toDateString());
        $status     = $request->query('status');
        $platform   = $request->query('platform');
        $search     = $request->query('search');
        $perPage    = min((int) $request->query('per_page', 50), 200);

        // Hanya tampil order yang relevan untuk gudang packing
        $packingStatuses = ['READY_TO_SHIP', 'PRINTED'];

        $query = Order::with('items')
            ->whereNotNull('tracking_number')
            ->whereDate('created_at', $date)
            ->where(function ($q) use ($packingStatuses) {
                $q->whereIn('status', $packingStatuses)
                  ->orWhere('label_print_status', 'PRINTED');
            })
            ->orderBy('created_at', 'desc');

        if ($status) {
            // Jika filter status eksplisit dari frontend, override default
            $query->where('status', $status);
        }
        if ($platform) {
            $query->where('platform', $platform);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('tracking_number', 'like', "%{$search}%")
                  ->orWhere('order_number', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }

        $orders = $query->paginate($perPage);

        // Statistik harian — hitung dari semua order READY_TO_SHIP/PRINTED hari ini
        $base = Order::whereNotNull('tracking_number')
            ->whereDate('created_at', $date)
            ->where(function ($q) use ($packingStatuses) {
                $q->whereIn('status', $packingStatuses)
                  ->orWhere('label_print_status', 'PRINTED');
            });
        $stats = [
            'total'         => (clone $base)->count(),
            'ready_to_scan' => (clone $base)->where('is_packed', 0)->where('label_print_status', 'PRINTED')->count(),
            'packed'        => (clone $base)->where('is_packed', 1)->count(),
            'platforms'     => (clone $base)->select('platform')->distinct()->pluck('platform')->filter()->values(),
        ];

        return response()->json([
            'orders' => $orders,
            'stats'  => $stats,
        ]);
    }

    public function showByTracking($trackingNumber)
    {
        $order = $this->findOrderByTracking($trackingNumber, ['items']);

        if (!$order) {
            return response()->json(['message' => 'Order tidak ditemukan'], 404);
        }

        if (strtoupper(trim((string)$order->status)) === 'CANCELLED') {
            return response()->json(['message' => '⚠️ Order ini sudah DI-CANCEL oleh pembeli / marketplace! Tidak bisa dipacking.'], 422);
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

        $order = $this->findOrderByTracking($trackingNumber, ['items', 'items.serials']);

        if (!$order) {
            return response()->json(['message' => 'Order tidak ditemukan'], 404);
        }

        if (strtoupper(trim((string)$order->status)) === 'CANCELLED') {
            return response()->json(['message' => '⚠️ Order ini sudah DI-CANCEL oleh pembeli / marketplace! Tidak bisa dipacking.'], 422);
        }

        if ($order->is_packed) {
            return response()->json([
                'message' => 'Order ini sudah berstatus packed dan tidak bisa divalidasi ulang.'
            ], 422);
        }

        $duplicateSerialMessage = $this->getDuplicateSerialMessage($request->items);
        if ($duplicateSerialMessage) {
            return response()->json([
                'message' => $duplicateSerialMessage,
            ], 422);
        }

        $usedSerialMessage = $this->getUsedSerialMessage($request->items);
        if ($usedSerialMessage) {
            return response()->json([
                'message' => $usedSerialMessage,
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
        $skuModels = collect();
        foreach ($skuList as $sku) {
            $normalized = $this->normalizeSku($sku);
            $skuModel = Sku::where('sku', $sku)
                ->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(UPPER(sku), 'SET ', ''), ' ', ''), '-', ''), ',', '') = ?", [$normalized])
                ->first();
            if ($skuModel) {
                $skuModels->put($sku, $skuModel);
            }
        }

        $skuIds = $skuModels->pluck('id')->toArray();

        // Lakukan semua operasi dalam transaction
        try {
            DB::transaction(function () use ($request, $expectedItems, $order, $skuModels) {
                $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->first();

                if (!$lockedOrder || $lockedOrder->is_packed) {
                    throw new \Exception('Order ini sudah berstatus packed dan tidak bisa divalidasi ulang.');
                }

                $usedSerialMessage = $this->getUsedSerialMessage($request->items);
                if ($usedSerialMessage) {
                    throw new \Exception($usedSerialMessage);
                }

                $allSerialsToInsert = [];
                $now = now();

                foreach ($request->items as $item) {
                    $sku = $item['sku'];
                    $serials = $item['serials'];

                    // Hapus serial lama
                    OrderItemSerial::where('order_item_id', $expectedItems[$sku]->id)->delete();

                    // Kurangi stok gudang produk dengan lock untuk mencegah race condition
                    $skuModel = $skuModels[$sku] ?? null;

                    $resolvedSerials = [];
                    if ($skuModel) {
                        foreach ($serials as $serial) {
                            $matchedSerial = null;
                            $targetSlotId = $this->findSlotForSerial($skuModel->id, $serial, $matchedSerial);
                            $resolvedSerial = $matchedSerial ?: $serial;
                            $resolvedSerials[] = $resolvedSerial;

                            if ($targetSlotId !== null) {
                                $workspaceEntry = GudangProdukWorkspaceStockEntry::where('sku_id', $skuModel->id)
                                    ->where('slot_id', $targetSlotId)
                                    ->lockForUpdate()
                                    ->first();

                                if ($workspaceEntry) {
                                    $workspaceEntry->qty -= 1;

                                    $workspaceEntry->save();

                                    GudangProdukActivityLog::create([
                                        'type' => 'packing_out',
                                        'sku_id' => $skuModel->id,
                                        'from_slot_id' => $targetSlotId,
                                        'to_slot_id' => null,
                                        'qty' => 1,
                                        'notes' => "Packing order #{$order->order_number} - SKU: {$sku} | Seri: {$resolvedSerial}",
                                        'created_by' => Auth::id(),
                                    ]);
                                }
                            }
                        }
                    } else {
                        $resolvedSerials = $serials;
                    }

                    // Prepare batch insert untuk serial numbers
                    foreach ($resolvedSerials as $resolvedSerial) {
                        $allSerialsToInsert[] = [
                            'order_item_id' => $expectedItems[$sku]->id,
                            'serial_number' => $resolvedSerial,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
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
                $lockedOrder->update(['is_packed' => 1]);

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

    public function checkSerialUsage(Request $request)
    {
        try {
            $request->validate([
                'sku' => 'nullable|string|max:255',
                'serial_number' => 'required|string|min:1|max:255',
            ], [
                'serial_number.required' => 'Nomor seri tidak boleh kosong',
                'serial_number.string' => 'Nomor seri harus berupa string',
                'serial_number.min' => 'Nomor seri minimal 1 karakter',
                'serial_number.max' => 'Nomor seri maksimal 255 karakter',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Data tidak valid',
                'errors' => $e->errors(),
            ], 422);
        }

        $message = $this->getUsedSerialMessage([
            [
                'sku' => $request->input('sku', '-'),
                'serials' => [$request->input('serial_number')],
            ],
        ]);

        if ($message) {
            return response()->json([
                'message' => $message,
                'available' => false,
            ], 409);
        }

        return response()->json([
            'message' => 'Nomor seri belum pernah digunakan',
            'available' => true,
        ]);
    }

    private function findOrderByTracking($trackingNumber, array $relations = [])
    {
        $normalizedTrackingNumber = $this->normalizeTrackingNumber($trackingNumber);

        if ($normalizedTrackingNumber === '') {
            return null;
        }

        $query = Order::query();

        if (!empty($relations)) {
            $query->with($relations);
        }

        $order = (clone $query)
            ->where('tracking_number', $normalizedTrackingNumber)
            ->first();

        if ($order) {
            return $order;
        }

        return (clone $query)
            ->whereNotNull('tracking_number')
            ->whereRaw('TRIM(tracking_number) = ?', [$normalizedTrackingNumber])
            ->first();
    }

    private function normalizeTrackingNumber($trackingNumber): string
    {
        return trim(urldecode((string) $trackingNumber));
    }

    private function normalizeSerialNumber($serialNumber): string
    {
        return strtoupper(trim((string) $serialNumber));
    }

    private function normalizeSku($sku): string
    {
        $sku = strtoupper(trim((string) $sku));
        
        $prefixes = ['SA-', 'RTN-', 'SET-', 'SET ', 'RTN '];
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($prefixes as $prefix) {
                if (str_starts_with($sku, $prefix)) {
                    $sku = substr($sku, strlen($prefix));
                    $changed = true;
                }
            }
        }
        
        return preg_replace('/[^A-Z0-9]/', '', $sku);
    }

    private function buildSkuSerialKey($sku, $serial): string
    {
        return $this->normalizeSku($sku) . '::' . $this->normalizeSerialNumber($serial);
    }

    private function isSpecialBypass($sku, $serial): bool
    {
        return true;
    }

    private function getDuplicateSerialMessage(array $items): ?string
    {
        $seen = [];

        foreach ($items as $item) {
            $sku = $item['sku'] ?? '-';

            foreach (($item['serials'] ?? []) as $serial) {
                $normalizedSerial = $this->normalizeSerialNumber($serial);

                if ($normalizedSerial === '' || $this->isSpecialBypass($sku, $serial)) {
                    continue;
                }

                $key = $this->buildSkuSerialKey($sku, $serial);

                if (isset($seen[$key])) {
                    return "Nomor seri {$serial} sudah pernah di-scan untuk SKU {$sku}.";
                }

                $seen[$key] = true;
            }
        }

        return null;
    }

    private function getUsedSerialMessage(array $items): ?string
    {
        $lookup = $this->collectSerialLookup($items);

        if (empty($lookup)) {
            return null;
        }

        $normalizedSerials = array_values(array_unique(array_column($lookup, 'normalized_serial')));

        $usedNormalSerial = DB::table('order_item_serials as ois')
            ->join('order_items as oi', 'oi.id', '=', 'ois.order_item_id')
            ->join('order as o', 'o.id', '=', 'oi.order_id')
            ->select([
                'ois.serial_number',
                'oi.sku',
                'o.tracking_number',
                'o.order_number',
            ])
            ->where('o.is_packed', 1)
            ->whereIn(DB::raw('UPPER(TRIM(ois.serial_number))'), $normalizedSerials)
            ->get()
            ->first(function ($row) use ($lookup) {
                return isset($lookup[$this->buildSkuSerialKey($row->sku, $row->serial_number)]);
            });

        if ($usedNormalSerial) {
            return $this->formatUsedSerialMessage(
                $usedNormalSerial->serial_number,
                $usedNormalSerial->sku,
                $usedNormalSerial->tracking_number,
                $usedNormalSerial->order_number
            );
        }

        $usedPackingResultSerial = DB::table('order_packing_result_serials as oprs')
            ->join('order_packing_results as opr', 'opr.id', '=', 'oprs.order_packing_result_id')
            ->join('order as o', 'o.id', '=', 'opr.order_id')
            ->select([
                'oprs.serial_number',
                'opr.actual_sku as sku',
                'o.tracking_number',
                'o.order_number',
            ])
            ->whereIn(DB::raw('UPPER(TRIM(oprs.serial_number))'), $normalizedSerials)
            ->get()
            ->first(function ($row) use ($lookup) {
                return isset($lookup[$this->buildSkuSerialKey($row->sku, $row->serial_number)]);
            });

        if ($usedPackingResultSerial) {
            return $this->formatUsedSerialMessage(
                $usedPackingResultSerial->serial_number,
                $usedPackingResultSerial->sku,
                $usedPackingResultSerial->tracking_number,
                $usedPackingResultSerial->order_number
            );
        }

        return null;
    }

    private function collectSerialLookup(array $items): array
    {
        $lookup = [];

        foreach ($items as $item) {
            $sku = $item['sku'] ?? '-';
            foreach (($item['serials'] ?? []) as $serial) {
                $normalizedSerial = $this->normalizeSerialNumber($serial);

                if ($normalizedSerial !== '') {
                    if ($this->isSpecialBypass($sku, $normalizedSerial)) {
                        continue;
                    }

                    $key = $this->buildSkuSerialKey($sku, $serial);
                    $lookup[$key] = [
                        'sku' => $sku,
                        'serial' => $serial,
                        'normalized_sku' => $this->normalizeSku($sku),
                        'normalized_serial' => $normalizedSerial,
                    ];
                }
            }
        }

        return $lookup;
    }

    private function formatUsedSerialMessage($serial, $sku = null, $trackingNumber = null, $orderNumber = null): string
    {
        $context = [];

        if ($sku) {
            $context[] = "SKU {$sku}";
        }

        if ($trackingNumber) {
            $context[] = "tracking {$trackingNumber}";
        } elseif ($orderNumber) {
            $context[] = "order {$orderNumber}";
        }

        $suffix = empty($context) ? '' : ' di ' . implode(', ', $context);

        return "Nomor seri {$serial} sudah pernah digunakan{$suffix} dan tidak bisa digunakan lagi.";
    }

    private function normalizeLogPerPage($perPage): int
    {
        $allowedValues = [25, 50, 100];
        $normalized = (int) $perPage;

        return in_array($normalized, $allowedValues, true) ? $normalized : 25;
    }

    public function dailyMonitoring(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
        ]);

        $startDate = $validated['start_date'] ?? now()->toDateString();
        $endDate = $validated['end_date'] ?? now()->toDateString();

        $orders = Order::select('id', 'order_number', 'tracking_number', 'status', 'platform', 'customer_name', 'total_qty', 'order_date', 'picked_at', 'updated_at')
            ->addSelect(['packed_log_time' => \Illuminate\Support\Facades\DB::table('order_logs')
                ->select('created_at')
                ->whereColumn('order_id', 'order.id')
                ->where('action', 'scan_validasi')
                ->latest('id')
                ->take(1)
            ])
            ->where('is_packed', 1)
            ->whereExists(function ($query) use ($startDate, $endDate) {
                $query->select(\Illuminate\Support\Facades\DB::raw(1))
                      ->from('order_logs')
                      ->whereColumn('order_logs.order_id', 'order.id')
                      ->where('order_logs.action', 'scan_validasi')
                      ->whereBetween('order_logs.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            })
            ->orderByDesc('updated_at')
            ->get();

        $chartData = \Illuminate\Support\Facades\DB::table('order_logs')
            ->join('order', 'order.id', '=', 'order_logs.order_id')
            ->where('order.is_packed', 1)
            ->where('order_logs.action', 'scan_validasi')
            ->whereBetween('order_logs.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select(
                \Illuminate\Support\Facades\DB::raw('DATE(order_logs.created_at) as date'),
                \Illuminate\Support\Facades\DB::raw('COUNT(DISTINCT order.id) as total_qty'),
                \Illuminate\Support\Facades\DB::raw("SUM(CASE WHEN order.status = 'SHIPPING' THEN 1 ELSE 0 END) as shipping_qty"),
                \Illuminate\Support\Facades\DB::raw("SUM(CASE WHEN order.status = 'DELIVERED' THEN 1 ELSE 0 END) as delivered_qty"),
                \Illuminate\Support\Facades\DB::raw("SUM(CASE WHEN order.status = 'CANCELLED' THEN 1 ELSE 0 END) as cancelled_qty"),
                \Illuminate\Support\Facades\DB::raw("SUM(CASE WHEN order.status NOT IN ('SHIPPING', 'DELIVERED', 'CANCELLED') THEN 1 ELSE 0 END) as other_qty")
            )
            ->groupBy(\Illuminate\Support\Facades\DB::raw('DATE(order_logs.created_at)'))
            ->orderBy('date', 'asc')
            ->get();

        $summary = [
            'total' => $orders->count(),
            'shipping' => $orders->where('status', 'SHIPPING')->count(),
            'delivered' => $orders->where('status', 'DELIVERED')->count(),
            'cancelled' => $orders->where('status', 'CANCELLED')->count(),
            'other' => $orders->whereNotIn('status', ['SHIPPING', 'DELIVERED', 'CANCELLED'])->count(),
            'chart_data' => $chartData,
        ];

        return response()->json([
            'message' => 'Data monitoring berhasil diambil',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'summary' => $summary,
            'data' => $orders->map(function($order) {
                $pickedAt = $order->picked_at ?: ($order->packed_log_time ?: $order->updated_at);
                
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'tracking_number' => $order->tracking_number,
                    'status' => $order->status,
                    'platform' => $order->platform,
                    'customer_name' => $order->customer_name,
                    'total_qty' => $order->total_qty,
                    'order_date' => $order->order_date,
                    'picked_at' => $pickedAt,
                    'updated_at' => $order->updated_at,
                ];
            })->values()
        ]);
    }

    public function monitor(Request $request)
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:120',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
            'status' => 'nullable|string|max:80',
            'packed' => 'nullable|in:packed,unpacked',
            'label_print_status' => 'nullable|string|max:80',
            'per_page' => 'nullable|integer|in:25,50,100',
            'cursor' => 'nullable|string|max:2000',
            'time_window_unit' => 'nullable|in:minute,hour',
            'time_window_value' => 'nullable|integer|min:1|max:1440',
        ]);

        $filters = $this->prepareMonitorFilters($validated);

        if (!$filters['q'] && $filters['start_date'] && $filters['end_date']) {
            $start = Carbon::parse($filters['start_date']);
            $end = Carbon::parse($filters['end_date']);

            if ($start->diffInDays($end) > 90) {
                return response()->json([
                    'message' => 'Rentang tanggal maksimal 90 hari jika tidak memakai pencarian order/resi.',
                ], 422);
            }
        }

        $baseQuery = Order::query();
        $this->applyMonitorFilters($baseQuery, $filters);

        $summary = (clone $baseQuery)
            ->selectRaw('COUNT(*) as total_in_window')
            ->selectRaw('SUM(CASE WHEN is_packed = 1 THEN 1 ELSE 0 END) as packed')
            ->selectRaw('SUM(CASE WHEN is_packed IS NULL OR is_packed = 0 THEN 1 ELSE 0 END) as unpacked')
            ->selectRaw("SUM(CASE WHEN LOWER(COALESCE(label_print_status, '')) = 'printed' THEN 1 ELSE 0 END) as printed")
            ->selectRaw('MAX(created_at) as latest_created_at')
            // Rata-rata jeda cetak resi -> packing (menit), proxy waktu packing = updated_at
            ->selectRaw('AVG(CASE WHEN is_packed = 1 AND label_print_time IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, label_print_time, updated_at) END) as avg_pack_minutes')
            // Resi tercetak paling lama yang belum dipacking (untuk deteksi backlog)
            ->selectRaw('MIN(CASE WHEN (is_packed IS NULL OR is_packed = 0) AND label_print_time IS NOT NULL THEN label_print_time END) as oldest_unpacked_print')
            ->first();

        // Distribusi per ekspedisi (berdasarkan prefix nomor resi) untuk seluruh data terfilter.
        $courierBreakdown = (clone $baseQuery)
            ->selectRaw($this->monitorCourierCaseExpression() . ' as courier')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN is_packed = 1 THEN 1 ELSE 0 END) as packed')
            ->selectRaw('SUM(CASE WHEN is_packed IS NULL OR is_packed = 0 THEN 1 ELSE 0 END) as unpacked')
            ->groupBy('courier')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'courier' => $row->courier,
                'total' => (int) $row->total,
                'packed' => (int) $row->packed,
                'unpacked' => (int) $row->unpacked,
            ])
            ->values();

        $orders = (clone $baseQuery)
            ->select([
                'id',
                'order_number',
                'tracking_number',
                'platform',
                'customer_name',
                'customer_phone',
                'total_amount',
                'status',
                'order_date',
                'total_qty',
                'sku',
                'is_packed',
                'label_print_status',
                'label_print_time',
                'picked_at',
                'created_at',
                'updated_at',
            ])
            ->orderByDesc('id')
            ->cursorPaginate(
                $filters['per_page'],
                ['*'],
                'cursor',
                $request->input('cursor')
            );

        return response()->json([
            'message' => 'Data monitoring order berhasil diambil',
            'data' => $orders->getCollection()->map(fn ($order) => $this->formatMonitorOrder($order))->values(),
            'summary' => [
                'total_in_window' => (int) ($summary->total_in_window ?? 0),
                'packed' => (int) ($summary->packed ?? 0),
                'unpacked' => (int) ($summary->unpacked ?? 0),
                'printed' => (int) ($summary->printed ?? 0),
                'avg_pack_minutes' => $summary->avg_pack_minutes !== null ? round((float) $summary->avg_pack_minutes, 1) : null,
                'oldest_unpacked_print' => $this->formatDateTimeValue($summary->oldest_unpacked_print ?? null),
                'courier_breakdown' => $courierBreakdown,
                'latest_created_at' => $this->formatDateTimeValue($summary->latest_created_at ?? null),
                'window_label' => $filters['window']['label'],
                'window_start_at' => $this->formatDateTimeValue($filters['window']['start_at']),
                'window_end_at' => $this->formatDateTimeValue($filters['window']['end_at']),
            ],
            'sync' => $this->getOrderSyncLogs(),
            'filters' => $filters,
            'pagination' => [
                'per_page' => $orders->perPage(),
                'next_cursor' => optional($orders->nextCursor())->encode(),
                'prev_cursor' => optional($orders->previousCursor())->encode(),
                'has_more' => $orders->hasMorePages(),
            ],
        ]);
    }

    public function checkPresence(Request $request)
    {
        $validated = $request->validate([
            'identifiers' => 'required|array|min:1|max:200',
            'identifiers.*' => 'required|string|min:1|max:255',
        ], [
            'identifiers.required' => 'Daftar order/resi wajib diisi',
            'identifiers.max' => 'Maksimal 200 nomor dalam sekali cek',
        ]);

        $identifiers = collect($validated['identifiers'])
            ->map(fn ($identifier) => trim((string) $identifier))
            ->filter()
            ->unique()
            ->values();

        if ($identifiers->isEmpty()) {
            return response()->json([
                'message' => 'Daftar order/resi wajib diisi',
            ], 422);
        }

        $orders = Order::query()
            ->select([
                'id',
                'order_number',
                'tracking_number',
                'status',
                'is_packed',
                'ginee_order_id',
                'created_at',
                'updated_at',
            ])
            ->whereIn('order_number', $identifiers)
            ->orWhereIn('tracking_number', $identifiers)
            ->orWhereIn('ginee_order_id', $identifiers)
            ->get();

        $byOrderNumber = $orders->filter(fn ($order) => !empty($order->order_number))->keyBy('order_number');
        $byTrackingNumber = $orders->filter(fn ($order) => !empty($order->tracking_number))->keyBy('tracking_number');
        $byGineeOrderId = $orders->filter(fn ($order) => !empty($order->ginee_order_id))->keyBy('ginee_order_id');

        $rows = $identifiers->map(function ($identifier) use ($byOrderNumber, $byTrackingNumber, $byGineeOrderId) {
            $order = $byOrderNumber->get($identifier) ?: $byTrackingNumber->get($identifier) ?: $byGineeOrderId->get($identifier);

            return [
                'identifier' => $identifier,
                'exists' => (bool) $order,
                'order' => $order ? $this->formatMonitorOrder($order) : null,
            ];
        })->values();

        $found = $rows->where('exists', true)->count();

        return response()->json([
            'message' => 'Hasil cek order berhasil diambil',
            'summary' => [
                'checked' => $rows->count(),
                'found' => $found,
                'missing' => $rows->count() - $found,
            ],
            'data' => $rows,
        ]);
    }

    /**
     * Rekapitulasi Monitoring Order per Bulan, per Tanggal & Status
     */
    public function monitorSummaryReport(Request $request)
    {
        $validated = $request->validate([
            'year'       => 'nullable|integer|min:2020|max:2035',
            'month'      => 'nullable|string',
            'date_basis' => 'nullable|in:order_date,created_at',
        ]);

        $dateCol    = ($validated['date_basis'] ?? 'order_date') === 'created_at' ? 'created_at' : 'order_date';
        $targetYear = (int) ($validated['year'] ?? now()->year);
        $targetMonth = $validated['month'] ?? now()->format('m');

        // 1. Available years
        $availableYears = DB::table('order')
            ->whereNotNull($dateCol)
            ->selectRaw("YEAR({$dateCol}) as year_val")
            ->distinct()
            ->orderByDesc('year_val')
            ->pluck('year_val')
            ->filter()
            ->values();

        if ($availableYears->isEmpty()) {
            $availableYears = collect([now()->year]);
        }

        // 2. Daily Summary
        $dailyQuery = DB::table('order')
            ->whereNotNull($dateCol)
            ->whereYear($dateCol, $targetYear);

        if ($targetMonth !== 'all' && is_numeric($targetMonth)) {
            $dailyQuery->whereMonth($dateCol, (int)$targetMonth);
        }

        $rawDaily = (clone $dailyQuery)
            ->selectRaw("DATE({$dateCol}) as date_key")
            ->selectRaw("COUNT(*) as total_orders")
            ->selectRaw("SUM(total_amount) as total_amount")
            ->selectRaw("SUM(CASE WHEN is_packed = 1 THEN 1 ELSE 0 END) as packed_count")
            ->selectRaw("SUM(CASE WHEN is_packed IS NULL OR is_packed = 0 THEN 1 ELSE 0 END) as unpacked_count")
            ->selectRaw("SUM(CASE WHEN status = 'PAID' THEN 1 ELSE 0 END) as status_paid")
            ->selectRaw("SUM(CASE WHEN status = 'READY_TO_SHIP' THEN 1 ELSE 0 END) as status_ready_to_ship")
            ->selectRaw("SUM(CASE WHEN status = 'PRINTED' THEN 1 ELSE 0 END) as status_printed")
            ->selectRaw("SUM(CASE WHEN status = 'SHIPPING' OR status = 'SHIPPED' THEN 1 ELSE 0 END) as status_shipped")
            ->selectRaw("SUM(CASE WHEN status = 'DELIVERED' OR status = 'COMPLETED' THEN 1 ELSE 0 END) as status_delivered")
            ->selectRaw("SUM(CASE WHEN status = 'CANCELLED' THEN 1 ELSE 0 END) as status_cancelled")
            ->selectRaw("SUM(CASE WHEN status = 'PENDING_PAYMENT' OR status = 'PAID_NOT_CONFIRM' THEN 1 ELSE 0 END) as status_pending")
            ->selectRaw("SUM(CASE WHEN status NOT IN ('PAID', 'READY_TO_SHIP', 'PRINTED', 'SHIPPING', 'SHIPPED', 'DELIVERED', 'COMPLETED', 'CANCELLED', 'PENDING_PAYMENT', 'PAID_NOT_CONFIRM') THEN 1 ELSE 0 END) as status_other")
            ->groupBy('date_key')
            ->orderByDesc('date_key')
            ->get();

        // 3. Monthly Summary
        $rawMonthly = DB::table('order')
            ->whereNotNull($dateCol)
            ->whereYear($dateCol, $targetYear)
            ->selectRaw("DATE_FORMAT({$dateCol}, '%Y-%m') as month_key")
            ->selectRaw("COUNT(*) as total_orders")
            ->selectRaw("SUM(total_amount) as total_amount")
            ->selectRaw("SUM(CASE WHEN is_packed = 1 THEN 1 ELSE 0 END) as packed_count")
            ->selectRaw("SUM(CASE WHEN is_packed IS NULL OR is_packed = 0 THEN 1 ELSE 0 END) as unpacked_count")
            ->selectRaw("SUM(CASE WHEN status = 'PAID' THEN 1 ELSE 0 END) as status_paid")
            ->selectRaw("SUM(CASE WHEN status = 'READY_TO_SHIP' THEN 1 ELSE 0 END) as status_ready_to_ship")
            ->selectRaw("SUM(CASE WHEN status = 'PRINTED' THEN 1 ELSE 0 END) as status_printed")
            ->selectRaw("SUM(CASE WHEN status = 'SHIPPING' OR status = 'SHIPPED' THEN 1 ELSE 0 END) as status_shipped")
            ->selectRaw("SUM(CASE WHEN status = 'DELIVERED' OR status = 'COMPLETED' THEN 1 ELSE 0 END) as status_delivered")
            ->selectRaw("SUM(CASE WHEN status = 'CANCELLED' THEN 1 ELSE 0 END) as status_cancelled")
            ->selectRaw("SUM(CASE WHEN status = 'PENDING_PAYMENT' OR status = 'PAID_NOT_CONFIRM' THEN 1 ELSE 0 END) as status_pending")
            ->selectRaw("SUM(CASE WHEN status NOT IN ('PAID', 'READY_TO_SHIP', 'PRINTED', 'SHIPPING', 'SHIPPED', 'DELIVERED', 'COMPLETED', 'CANCELLED', 'PENDING_PAYMENT', 'PAID_NOT_CONFIRM') THEN 1 ELSE 0 END) as status_other")
            ->groupBy('month_key')
            ->orderByDesc('month_key')
            ->get();

        // 4. Overall KPI Summary
        $kpi = [
            'total_orders'     => (int) $rawDaily->sum('total_orders'),
            'total_amount'     => (float) $rawDaily->sum('total_amount'),
            'packed_count'     => (int) $rawDaily->sum('packed_count'),
            'unpacked_count'   => (int) $rawDaily->sum('unpacked_count'),
            'status_paid'      => (int) $rawDaily->sum('status_paid'),
            'status_ready'     => (int) $rawDaily->sum('status_ready_to_ship'),
            'status_printed'   => (int) $rawDaily->sum('status_printed'),
            'status_shipped'   => (int) $rawDaily->sum('status_shipped'),
            'status_delivered' => (int) $rawDaily->sum('status_delivered'),
            'status_cancelled' => (int) $rawDaily->sum('status_cancelled'),
            'status_pending'   => (int) $rawDaily->sum('status_pending'),
            'status_other'     => (int) $rawDaily->sum('status_other'),
        ];

        return response()->json([
            'available_years' => $availableYears,
            'selected_year'   => $targetYear,
            'selected_month'  => $targetMonth,
            'date_basis'      => $dateCol,
            'kpi'             => $kpi,
            'daily'           => $rawDaily,
            'monthly'         => $rawMonthly,
        ]);
    }

    private function prepareMonitorFilters(array $validated): array
    {
        $q = trim((string) ($validated['q'] ?? ''));

        $windowUnit = $validated['time_window_unit'] ?? '';
        $windowValue = (int) ($validated['time_window_value'] ?? 0);
        $window = $this->prepareMonitorTimeWindow($windowUnit, $windowValue);

        $startDate = $validated['start_date'] ?? ($q || $window['active'] ? null : now()->subDays(7)->toDateString());
        $endDate = $validated['end_date'] ?? ($q || $window['active'] ? null : now()->toDateString());

        return [
            'q' => $q,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'time_window_unit' => $window['unit'],
            'time_window_value' => $window['value'],
            'window' => $window,
            'status' => trim((string) ($validated['status'] ?? '')),
            'packed' => $validated['packed'] ?? '',
            'label_print_status' => trim((string) ($validated['label_print_status'] ?? '')),
            'per_page' => $this->normalizeMonitorPerPage($validated['per_page'] ?? 50),
        ];
    }

    private function applyMonitorFilters($query, array $filters): void
    {
        if ($filters['q'] !== '') {
            $search = $filters['q'];
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('order_number', $search)
                    ->orWhere('tracking_number', $search)
                    ->orWhere('order_number', 'LIKE', $search . '%')
                    ->orWhere('tracking_number', 'LIKE', $search . '%');
            });
        }

        if ($filters['window']['active']) {
            $query->where('created_at', '>=', $filters['window']['start_at'])
                ->where('created_at', '<=', $filters['window']['end_at']);
        } elseif ($filters['start_date']) {
            $query->where('created_at', '>=', Carbon::parse($filters['start_date'])->startOfDay());

            if ($filters['end_date']) {
                $query->where('created_at', '<=', Carbon::parse($filters['end_date'])->endOfDay());
            }
        }

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if ($filters['packed'] === 'packed') {
            $query->where('is_packed', 1);
        } elseif ($filters['packed'] === 'unpacked') {
            $query->where(function ($subQuery) {
                $subQuery->whereNull('is_packed')->orWhere('is_packed', 0);
            });
        }

        if ($filters['label_print_status'] !== '') {
            $query->where('label_print_status', $filters['label_print_status']);
        }
    }

    /**
     * Ekspresi SQL untuk memetakan prefix nomor resi ke nama ekspedisi.
     * Disinkronkan dengan getCourier() di frontend (PackingPrintedComparison.js).
     */
    private function monitorCourierCaseExpression(): string
    {
        return "CASE
            WHEN UPPER(COALESCE(tracking_number, '')) LIKE 'SPX%' THEN 'Shopee Xpress'
            WHEN UPPER(COALESCE(tracking_number, '')) LIKE 'JX%' THEN 'J&T Cargo'
            WHEN UPPER(COALESCE(tracking_number, '')) LIKE 'JT%' THEN 'J&T Express'
            WHEN UPPER(COALESCE(tracking_number, '')) LIKE 'TK%' THEN 'J&T Express'
            WHEN UPPER(COALESCE(tracking_number, '')) LIKE 'ID%' THEN 'ID Express'
            WHEN UPPER(COALESCE(tracking_number, '')) LIKE '00%' THEN 'SiCepat'
            WHEN UPPER(COALESCE(tracking_number, '')) LIKE 'NL%' THEN 'Ninja Xpress'
            WHEN UPPER(COALESCE(tracking_number, '')) LIKE 'LX%' THEN 'Lex Express'
            WHEN COALESCE(tracking_number, '') = '' THEN 'Tanpa Resi'
            ELSE CONCAT('Lainnya (', LEFT(UPPER(tracking_number), 3), '...)')
        END";
    }

    private function normalizeMonitorPerPage($perPage): int
    {
        $allowedValues = [25, 50, 100];
        $normalized = (int) $perPage;

        return in_array($normalized, $allowedValues, true) ? $normalized : 50;
    }

    private function prepareMonitorTimeWindow(?string $unit, int $value): array
    {
        if (!in_array($unit, ['minute', 'hour'], true) || $value < 1) {
            return [
                'active' => false,
                'unit' => '',
                'value' => '',
                'label' => 'Rentang tanggal',
                'start_at' => null,
                'end_at' => null,
            ];
        }

        $maxValue = $unit === 'hour' ? 720 : 1440;
        $normalizedValue = min($value, $maxValue);
        $endAt = now();
        $startAt = $unit === 'hour'
            ? $endAt->copy()->subHours($normalizedValue)
            : $endAt->copy()->subMinutes($normalizedValue);
        $unitLabel = $unit === 'hour' ? 'jam' : 'menit';

        return [
            'active' => true,
            'unit' => $unit,
            'value' => $normalizedValue,
            'label' => "{$normalizedValue} {$unitLabel} kebelakang",
            'start_at' => $startAt,
            'end_at' => $endAt,
        ];
    }

    public function getAllLogs(Request $request)
    {
        $filters = app(PackingLogReportService::class)->prepareFilters(
            $request->only(['start_date', 'end_date', 'status', 'tracking_number', 'performed_by', 'mode', 'serial'])
        );

        $logs = app(PackingLogReportService::class)->paginateLogs(
            $filters,
            $this->normalizeLogPerPage($request->input('per_page', 25)),
            $request->input('cursor')
        ); 

        return response()->json($logs);
    }

    public function getLogDetail(string $sourceType, int $sourceId)
    {
        $detail = app(PackingLogReportService::class)->getDetail($sourceType, $sourceId);

        return response()->json([
            'message' => 'Detail log berhasil diambil',
            'data' => $detail,
        ]);
    } 

    public function getSummaryReport(Request $request)
    {
        $rawFilters = $request->only(['start_date', 'end_date', 'status', 'tracking_number', 'performed_by', 'mode', 'serial']);

        $rawFilters['start_date'] = $rawFilters['start_date'] ?? now()->toDateString();
        $rawFilters['end_date'] = $rawFilters['end_date'] ?? now()->toDateString();

        $summary = app(PackingLogReportService::class)->getSummary(
            app(PackingLogReportService::class)->prepareFilters($rawFilters)
        );

        return response()->json(array_merge([
            'message' => 'Summary report berhasil diambil',
        ], $summary));
    }

    public function exportLogsToExcel(Request $request)
    {
        $filters = app(PackingLogReportService::class)->prepareFilters(
            $request->only(['start_date', 'end_date', 'status', 'tracking_number', 'performed_by', 'mode', 'serial'])
        );

        $fileName = 'packing_logs_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new OrderLogsExport($filters), $fileName);
    }

    public function requestLogsExport(Request $request)
    {
        $filters = app(PackingLogReportService::class)->prepareFilters(
            $request->only(['start_date', 'end_date', 'status', 'tracking_number', 'performed_by', 'mode', 'serial'])
        );

        $timestamp = now()->format('Ymd_His');
        $fileName = 'packing_logs_' . $timestamp . '.xlsx';

        $exportRequest = PackingLogExport::create([
            'user_id' => Auth::id(),
            'status' => 'queued',
            'filters' => [
                'start_date' => $filters['start_date'],
                'end_date' => $filters['end_date'],
                'status' => $filters['status'],
                'tracking_number' => $filters['tracking_number'],
                'performed_by' => $filters['performed_by'],
                'mode' => $filters['mode'],
                'serial' => $filters['serial'] ?? null,
            ],
            'file_name' => $fileName,
            'file_path' => 'exports/packing-logs/' . $fileName,
        ]);

        GeneratePackingLogExport::dispatch($exportRequest->id);
        $exportRequest->refresh();

        return response()->json([
            'message' => 'Export logs sedang diproses',
            'data' => $this->formatExportResponse($exportRequest),
        ], $exportRequest->status === 'completed' ? 200 : 202);
    }

    public function showLogsExport(int $exportId)
    {
        $exportRequest = $this->findUserExportOrFail($exportId);

        return response()->json([
            'message' => 'Status export berhasil diambil',
            'data' => $this->formatExportResponse($exportRequest),
        ]);
    }

    public function downloadLogsExport(int $exportId)
    {
        $exportRequest = $this->findUserExportOrFail($exportId);

        if ($exportRequest->status !== 'completed' || !$exportRequest->file_path) {
            return response()->json([
                'message' => 'File export belum siap diunduh',
            ], 422);
        }

        if (!Storage::disk('public')->exists($exportRequest->file_path)) {
            return response()->json([
                'message' => 'File export tidak ditemukan',
            ], 404);
        }

        return Storage::disk('public')->download($exportRequest->file_path, $exportRequest->file_name);
    }

    private function findUserExportOrFail(int $exportId): PackingLogExport
    {
        return PackingLogExport::where('id', $exportId)
            ->where('user_id', Auth::id())
            ->firstOrFail();
    }

    private function formatExportResponse(PackingLogExport $exportRequest): array
    {
        return [
            'id' => $exportRequest->id,
            'status' => $exportRequest->status,
            'file_name' => $exportRequest->file_name,
            'error_message' => $exportRequest->error_message,
            'created_at' => optional($exportRequest->created_at)->toDateTimeString(),
            'started_at' => optional($exportRequest->started_at)->toDateTimeString(),
            'completed_at' => optional($exportRequest->completed_at)->toDateTimeString(),
            'can_download' => $exportRequest->status === 'completed' && !empty($exportRequest->file_path),
        ];
    }

    private function getOrderSyncLogs(): array
    {
        $labels = [
            'orders_packing_hot' => 'Packing hot',
            'orders_packing_hot_printed' => 'Printed',
            'orders_packing_hot_ready_to_ship' => 'Ready to ship',
            'orders_printed' => 'Printed sync',
            'orders' => 'Orders',
        ];

        return SyncLog::query()
            ->whereIn('type', array_keys($labels))
            ->orderByRaw("FIELD(type, 'orders_packing_hot', 'orders_packing_hot_printed', 'orders_packing_hot_ready_to_ship', 'orders_printed', 'orders')")
            ->get(['type', 'last_sync_at', 'updated_at'])
            ->map(function ($syncLog) use ($labels) {
                return [
                    'type' => $syncLog->type,
                    'label' => $labels[$syncLog->type] ?? $syncLog->type,
                    'last_sync_at' => $this->formatDateTimeValue($syncLog->last_sync_at),
                    'updated_at' => $this->formatDateTimeValue($syncLog->updated_at),
                ];
            })
            ->values()
            ->all();
    }

    private function formatMonitorOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'tracking_number' => $order->tracking_number,
            'platform' => $order->platform,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'total_amount' => $order->total_amount,
            'status' => $order->status,
            'order_date' => $this->formatDateTimeValue($order->order_date),
            'total_qty' => $order->total_qty,
            'sku' => $order->sku,
            'is_packed' => (int) $order->is_packed === 1,
            'label_print_status' => $order->label_print_status,
            'label_print_time' => $this->formatDateTimeValue($order->label_print_time),
            'picked_at' => $this->formatDateTimeValue($order->picked_at),
            'created_at' => $this->formatDateTimeValue($order->created_at),
            'updated_at' => $this->formatDateTimeValue($order->updated_at),
            'source' => $order->source ?? 'syncing',
        ];
    }

    private function formatDateTimeValue($value): ?string
    {
        if (!$value) {
            return null;
        }

        return Carbon::parse($value)->toIso8601String();
    }

    public function pickingQueue()
    {
        $orders = Order::where('label_print_status', 'printed')
            ->whereNull('picked_at')
            ->orderBy('label_print_time', 'asc')
            ->get();

        return response()->json($orders);
    }

    public function markPicked($id)
    {
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

    public function dailyPrintReport(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
        ]);

        $startDate = $validated['start_date'] ?? now()->subDays(7)->toDateString();
        $endDate = $validated['end_date'] ?? now()->toDateString();

        $report = \Illuminate\Support\Facades\DB::table('order')
            ->whereNotNull('label_print_time')
            ->whereBetween(\Illuminate\Support\Facades\DB::raw('DATE(label_print_time)'), [$startDate, $endDate])
            ->select(
                \Illuminate\Support\Facades\DB::raw('DATE(label_print_time) as print_date'),
                \Illuminate\Support\Facades\DB::raw('COUNT(*) as total_printed'),
                \Illuminate\Support\Facades\DB::raw('SUM(CASE WHEN is_packed = 1 THEN 1 ELSE 0 END) as total_packed'),
                \Illuminate\Support\Facades\DB::raw('SUM(CASE WHEN is_packed IS NULL OR is_packed = 0 THEN 1 ELSE 0 END) as total_unpacked'),
                \Illuminate\Support\Facades\DB::raw('MIN(CASE WHEN (is_packed IS NULL OR is_packed = 0) THEN shipping_deadline ELSE NULL END) as earliest_shipping_deadline')
            )
            ->groupBy(\Illuminate\Support\Facades\DB::raw('DATE(label_print_time)'))
            ->orderByDesc('print_date')
            ->get();

        $hourlyChart = \Illuminate\Support\Facades\DB::table('order_logs')
            ->join('order', 'order.id', '=', 'order_logs.order_id')
            ->where('order.is_packed', 1)
            ->where('order_logs.action', 'scan_validasi')
            ->whereBetween('order_logs.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select(
                \Illuminate\Support\Facades\DB::raw('DATE(order_logs.created_at) as date'),
                \Illuminate\Support\Facades\DB::raw('HOUR(order_logs.created_at) as hour'),
                \Illuminate\Support\Facades\DB::raw('COUNT(DISTINCT order.id) as total_packed')
            )
            ->groupBy(\Illuminate\Support\Facades\DB::raw('DATE(order_logs.created_at)'), \Illuminate\Support\Facades\DB::raw('HOUR(order_logs.created_at)'))
            ->orderBy('date', 'asc')
            ->orderBy('hour', 'asc')
            ->get();

        return response()->json([
            'message' => 'Laporan cetak harian berhasil diambil',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'data' => $report,
            'hourly_chart' => $hourlyChart,
        ]);
    }

    public function dailyPackingReport(Request $request, \App\Services\PackingLogReportService $logService)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
        ]);

        $startDate = $validated['start_date'] ?? now()->subDays(7)->toDateString();
        $endDate = $validated['end_date'] ?? now()->toDateString();

        $filters = $logService->prepareFilters([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $baseQuery = $logService->buildExportQuery($filters);

        // Stable "unit key" per packed order: order_log rows collapse on order_id,
        // No Data Ginee rows stay distinct per source row (matches the COUNT(DISTINCT ...) semantics below).
        $unitKeyExpr = "CASE WHEN source_type = 'order_log' THEN CONCAT('o:', order_id) ELSE CONCAT(source_type, ':', source_id) END";

        $report = \Illuminate\Support\Facades\DB::query()
            ->fromSub(clone $baseQuery, 'logs_data')
            ->select(
                \Illuminate\Support\Facades\DB::raw('DATE(created_at) as pack_date'),
                \Illuminate\Support\Facades\DB::raw("COUNT(DISTINCT $unitKeyExpr) as total_packed"),
                \Illuminate\Support\Facades\DB::raw("COUNT(DISTINCT CASE WHEN order_status = 'READY_TO_SHIP' THEN $unitKeyExpr ELSE NULL END) as total_ready_to_ship"),
                \Illuminate\Support\Facades\DB::raw("COUNT(DISTINCT CASE WHEN order_status = 'SHIPPING' THEN $unitKeyExpr ELSE NULL END) as total_shipping"),
                \Illuminate\Support\Facades\DB::raw("COUNT(DISTINCT CASE WHEN order_status = 'DELIVERED' THEN $unitKeyExpr ELSE NULL END) as total_delivered"),
                \Illuminate\Support\Facades\DB::raw("COUNT(DISTINCT CASE WHEN order_status = 'CANCELLED' THEN $unitKeyExpr ELSE NULL END) as total_cancelled")
            )
            ->groupBy(\Illuminate\Support\Facades\DB::raw('DATE(created_at)'))
            ->orderByDesc('pack_date')
            ->get();

        // Per-day item volume. Dedup item count to one value per order per day first,
        // then sum — a naive SUM() over all log rows would double-count re-scanned orders.
        $dailyItems = \Illuminate\Support\Facades\DB::query()
            ->fromSub(
                \Illuminate\Support\Facades\DB::query()
                    ->fromSub(clone $baseQuery, 'logs_data')
                    ->select(
                        \Illuminate\Support\Facades\DB::raw('DATE(created_at) as pack_date'),
                        \Illuminate\Support\Facades\DB::raw("$unitKeyExpr as unit_key"),
                        \Illuminate\Support\Facades\DB::raw('MAX(total_items) as items')
                    )
                    ->groupBy(\Illuminate\Support\Facades\DB::raw('DATE(created_at)'), \Illuminate\Support\Facades\DB::raw($unitKeyExpr)),
                'per_unit'
            )
            ->select(
                'pack_date',
                \Illuminate\Support\Facades\DB::raw('SUM(items) as total_items')
            )
            ->groupBy('pack_date')
            ->pluck('total_items', 'pack_date');

        // Merge per-day item totals into the daily report rows.
        $report = $report->map(function ($row) use ($dailyItems) {
            $row->total_items = (int) ($dailyItems[$row->pack_date] ?? 0);
            return $row;
        });

        $hourlyChart = \Illuminate\Support\Facades\DB::query()
            ->fromSub(clone $baseQuery, 'logs_data')
            ->select(
                \Illuminate\Support\Facades\DB::raw('DATE(created_at) as date'),
                \Illuminate\Support\Facades\DB::raw('HOUR(created_at) as hour'),
                \Illuminate\Support\Facades\DB::raw("COUNT(DISTINCT $unitKeyExpr) as total_packed")
            )
            ->groupBy(\Illuminate\Support\Facades\DB::raw('DATE(created_at)'), \Illuminate\Support\Facades\DB::raw('HOUR(created_at)'))
            ->orderBy('date', 'asc')
            ->orderBy('hour', 'asc')
            ->get();

        // Deduplicated unit set: one row per packed order, carrying the latest log's
        // operator/mode/status (GROUP_CONCAT trick — no window functions needed).
        $latestPick = function (string $column) {
            return "SUBSTRING_INDEX(GROUP_CONCAT($column ORDER BY created_at DESC, source_priority DESC, source_id DESC SEPARATOR '\\x1f'), '\\x1f', 1)";
        };

        $dedupedUnits = \Illuminate\Support\Facades\DB::query()
            ->fromSub(clone $baseQuery, 'logs_data')
            ->select(
                \Illuminate\Support\Facades\DB::raw("$unitKeyExpr as unit_key"),
                \Illuminate\Support\Facades\DB::raw($latestPick('COALESCE(NULLIF(TRIM(performed_by), \'\'), \'Tidak diketahui\')') . ' as performed_by'),
                \Illuminate\Support\Facades\DB::raw($latestPick('action') . ' as action'),
                \Illuminate\Support\Facades\DB::raw($latestPick('order_status') . ' as order_status'),
                \Illuminate\Support\Facades\DB::raw('MAX(total_items) as total_items')
            )
            ->groupBy(\Illuminate\Support\Facades\DB::raw($unitKeyExpr));

        $byOperator = \Illuminate\Support\Facades\DB::query()
            ->fromSub(clone $dedupedUnits, 'units')
            ->select(
                'performed_by',
                \Illuminate\Support\Facades\DB::raw('COUNT(*) as total_orders'),
                \Illuminate\Support\Facades\DB::raw('COALESCE(SUM(total_items), 0) as total_items')
            )
            ->groupBy('performed_by')
            ->orderByDesc('total_orders')
            ->get();

        $byModeRaw = \Illuminate\Support\Facades\DB::query()
            ->fromSub(clone $dedupedUnits, 'units')
            ->select(
                'action',
                \Illuminate\Support\Facades\DB::raw('COUNT(*) as total_orders'),
                \Illuminate\Support\Facades\DB::raw('COALESCE(SUM(total_items), 0) as total_items')
            )
            ->groupBy('action')
            ->orderByDesc('total_orders')
            ->get();

        $byMode = $byModeRaw->map(function ($row) use ($logService) {
            return [
                'action' => $row->action,
                'mode_label' => $logService->getModeLabel($row->action),
                'total_orders' => (int) $row->total_orders,
                'total_items' => (int) $row->total_items,
            ];
        })->values();

        // Headline totals from the deduplicated unit set (true distinct orders in range).
        $totals = \Illuminate\Support\Facades\DB::query()
            ->fromSub(clone $dedupedUnits, 'units')
            ->select(
                \Illuminate\Support\Facades\DB::raw('COUNT(*) as total_orders'),
                \Illuminate\Support\Facades\DB::raw('COALESCE(SUM(total_items), 0) as total_items')
            )
            ->first();

        // Busiest hour across the period (by order count).
        $peakHourRow = \Illuminate\Support\Facades\DB::query()
            ->fromSub(clone $baseQuery, 'logs_data')
            ->select(
                \Illuminate\Support\Facades\DB::raw('HOUR(created_at) as hour'),
                \Illuminate\Support\Facades\DB::raw("COUNT(DISTINCT $unitKeyExpr) as total_packed")
            )
            ->groupBy(\Illuminate\Support\Facades\DB::raw('HOUR(created_at)'))
            ->orderByDesc('total_packed')
            ->orderBy('hour', 'asc')
            ->first();

        $summary = [
            'total_orders' => (int) ($totals->total_orders ?? 0),
            'total_items' => (int) ($totals->total_items ?? 0),
            'total_operators' => $byOperator->count(),
            'peak_hour' => $peakHourRow ? (int) $peakHourRow->hour : null,
            'peak_hour_count' => $peakHourRow ? (int) $peakHourRow->total_packed : 0,
        ];

        return response()->json([
            'message' => 'Laporan packing harian berhasil diambil',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'summary' => $summary,
            'data' => $report,
            'by_operator' => $byOperator,
            'by_mode' => $byMode,
            'hourly_chart' => $hourlyChart,
        ]);
    }

    public function syncHistorical()
    {
        try {
            $command = 'nohup php ' . base_path('artisan') . ' sync:extended-history > ' . storage_path('logs/sync-history.log') . ' 2>&1 &';
            shell_exec($command);

            return response()->json([
                'success' => true,
                'message' => 'Proses penarikan data historis (90 hari) telah dimulai di latar belakang.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memulai proses penarikan: ' . $e->getMessage()
            ], 500);
        }
    }
}
