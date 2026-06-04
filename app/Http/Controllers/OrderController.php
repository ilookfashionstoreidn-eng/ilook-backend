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



class OrderController extends Controller
{
    public function showByTracking($trackingNumber)
    {
        $order = $this->findOrderByTracking($trackingNumber, ['items']);

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

        $order = $this->findOrderByTracking($trackingNumber, ['items', 'items.serials']);

        if (!$order) {
            return response()->json(['message' => 'Order tidak ditemukan'], 404);
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
        $skuModels = Sku::whereIn('sku', $skuList)
            ->get()
            ->keyBy('sku');

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
                        $stockEntries = GudangProdukWorkspaceStockEntry::where('sku_id', $skuModel->id)
                            ->where('qty', '>', 0)
                            ->orderBy('id')
                            ->lockForUpdate()
                            ->get();

                        // Jika tidak ada stok di workspace, lanjutkan saja
                        // (barang belum di-input ke sistem gudang, bukan error)
                        if ($stockEntries->isEmpty()) {
                            continue;
                        }

                        $availableQty = (int) $stockEntries->sum('qty');
                        $requiredQty = (int) $item['quantity'];

                        // Stok ada tapi kurang — ini baru jadi error
                        if ($availableQty < $requiredQty) {
                            throw new \Exception("Stok gudang produk untuk SKU {$sku} tidak mencukupi. Stok tersedia: {$availableQty}, dibutuhkan: {$requiredQty}");
                        }

                        $remainingToDeduct = $requiredQty;

                        foreach ($stockEntries as $workspaceEntry) {
                            if ($remainingToDeduct <= 0) {
                                break;
                            }

                            $entryQty = (int) $workspaceEntry->qty;
                            $deductQty = min($entryQty, $remainingToDeduct);

                            if ($deductQty <= 0) {
                                continue;
                            }

                            $slotId = $workspaceEntry->slot_id;
                            $workspaceEntry->qty -= $deductQty;

                            if ($workspaceEntry->qty <= 0) {
                                $workspaceEntry->delete();
                            } else {
                                $workspaceEntry->save();
                            }

                            GudangProdukActivityLog::create([
                                'type' => 'packing_out',
                                'sku_id' => $skuModel->id,
                                'from_slot_id' => $slotId,
                                'to_slot_id' => null,
                                'qty' => $deductQty,
                                'notes' => "Packing order #{$order->order_number} - SKU: {$sku}",
                                'created_by' => Auth::id(),
                            ]);

                            $remainingToDeduct -= $deductQty;
                        }
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

    private function isSpecialBypass($sku, $serial): bool
    {
        $normalizedSerial = strtoupper(trim((string) $serial));
        $normalizedSku = strtoupper(trim((string) $sku));

        $bypasses = [
            ['sku' => 'SET BANGWOOL - OLIVE L', 'serial' => '3161.102.189'],
            ['sku' => 'SET KITANO - CREAM XL', 'serial' => '121.1'],
        ];

        foreach ($bypasses as $bypass) {
            if (($normalizedSku === $bypass['sku'] && $normalizedSerial === $bypass['serial']) || 
                $normalizedSerial === $bypass['serial']) {
                return true;
            }
        }

        return false;
    }

    private function getDuplicateSerialMessage(array $items): ?string
    {
        $seenSerials = [];

        foreach ($items as $item) {
            $sku = $item['sku'] ?? '-';

            foreach (($item['serials'] ?? []) as $serial) {
                $normalizedSerial = $this->normalizeSerialNumber($serial);

                if ($normalizedSerial === '') {
                    continue;
                }

                if ($this->isSpecialBypass($sku, $normalizedSerial)) {
                    continue;
                }

                if (isset($seenSerials[$normalizedSerial])) {
                    return "Nomor seri {$serial} sudah pernah di-scan untuk SKU {$seenSerials[$normalizedSerial]}";
                }

                $seenSerials[$normalizedSerial] = $sku;
            }
        }

        return null;
    }

    private function getUsedSerialMessage(array $items): ?string
    {
        $serials = $this->collectSerialLookup($items);

        if (empty($serials)) {
            return null;
        }

        $normalizedSerials = array_keys($serials);

        $normalPackingSerial = DB::table('order_item_serials as serials')
            ->join('order_items as items', 'items.id', '=', 'serials.order_item_id')
            ->leftJoin('order as orders', 'orders.id', '=', 'items.order_id')
            ->whereIn(DB::raw('UPPER(TRIM(serials.serial_number))'), $normalizedSerials)
            ->select([
                'serials.serial_number',
                'items.sku',
                'orders.order_number',
                'orders.tracking_number',
            ])
            ->first();

        if ($normalPackingSerial) {
            return $this->formatUsedSerialMessage(
                $normalPackingSerial->serial_number,
                $normalPackingSerial->sku,
                $normalPackingSerial->tracking_number,
                $normalPackingSerial->order_number
            );
        }

        $randomPackingSerial = DB::table('order_packing_result_serials as serials')
            ->join('order_packing_results as results', 'results.id', '=', 'serials.order_packing_result_id')
            ->leftJoin('order as orders', 'orders.id', '=', 'results.order_id')
            ->whereIn(DB::raw('UPPER(TRIM(serials.serial_number))'), $normalizedSerials)
            ->select([
                'serials.serial_number',
                'results.actual_sku as sku',
                'orders.order_number',
                'orders.tracking_number',
            ])
            ->first();

        if ($randomPackingSerial) {
            return $this->formatUsedSerialMessage(
                $randomPackingSerial->serial_number,
                $randomPackingSerial->sku,
                $randomPackingSerial->tracking_number,
                $randomPackingSerial->order_number
            );
        }

        $noDataGineeSerial = DB::table('no_data_ginee_log_scans as scans')
            ->join('no_data_ginee_logs as logs', 'logs.id', '=', 'scans.no_data_ginee_log_id')
            ->whereIn(DB::raw('UPPER(TRIM(scans.serial_number))'), $normalizedSerials)
            ->select([
                'scans.serial_number',
                'scans.actual_sku as sku',
                'logs.tracking_number',
            ])
            ->first();

        if ($noDataGineeSerial) {
            return $this->formatUsedSerialMessage(
                $noDataGineeSerial->serial_number,
                $noDataGineeSerial->sku,
                $noDataGineeSerial->tracking_number
            );
        }

        return null;
    }

    private function collectSerialLookup(array $items): array
    {
        $serials = [];

        foreach ($items as $item) {
            $sku = $item['sku'] ?? '-';
            foreach (($item['serials'] ?? []) as $serial) {
                $normalizedSerial = $this->normalizeSerialNumber($serial);

                if ($normalizedSerial !== '') {
                    if ($this->isSpecialBypass($sku, $normalizedSerial)) {
                        continue;
                    }
                    $serials[$normalizedSerial] = $serial;
                }
            }
        }

        return $serials;
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
            ->first();

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
                'created_at',
                'updated_at',
            ])
            ->whereIn('order_number', $identifiers)
            ->orWhereIn('tracking_number', $identifiers)
            ->get();

        $byOrderNumber = $orders->filter(fn ($order) => !empty($order->order_number))->keyBy('order_number');
        $byTrackingNumber = $orders->filter(fn ($order) => !empty($order->tracking_number))->keyBy('tracking_number');

        $rows = $identifiers->map(function ($identifier) use ($byOrderNumber, $byTrackingNumber) {
            $order = $byOrderNumber->get($identifier) ?: $byTrackingNumber->get($identifier);

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
            $request->only(['start_date', 'end_date', 'status', 'tracking_number', 'performed_by', 'mode'])
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
        $rawFilters = $request->only(['start_date', 'end_date', 'status', 'tracking_number', 'performed_by', 'mode']);

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
            $request->only(['start_date', 'end_date', 'status', 'tracking_number', 'performed_by', 'mode'])
        );

        $fileName = 'packing_logs_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new OrderLogsExport($filters), $fileName);
    }

    public function requestLogsExport(Request $request)
    {
        $filters = app(PackingLogReportService::class)->prepareFilters(
            $request->only(['start_date', 'end_date', 'status', 'tracking_number', 'performed_by', 'mode'])
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
            'is_packed' => (int) $order->is_packed === 1,
            'label_print_status' => $order->label_print_status,
            'label_print_time' => $this->formatDateTimeValue($order->label_print_time),
            'picked_at' => $this->formatDateTimeValue($order->picked_at),
            'created_at' => $this->formatDateTimeValue($order->created_at),
            'updated_at' => $this->formatDateTimeValue($order->updated_at),
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
}
