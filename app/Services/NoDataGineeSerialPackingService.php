<?php

namespace App\Services;

use App\Models\GudangProdukActivityLog;
use App\Models\GudangProdukDetail;
use App\Models\GudangProdukWorkspaceStockEntry;
use App\Models\NoDataGineeLog;
use App\Models\Order;
use App\Models\OrderPackingResult;
use App\Models\ProdukSku;
use App\Models\Sku;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Traits\TracksWarehouseSerials;

class NoDataGineeSerialPackingService
{
    use TracksWarehouseSerials;
    public function __construct(
        private GudangProdukPackingStockService $stockService
    ) {
    }

    public function attachScans(NoDataGineeLog $log, array $scanPayload): void
    {
        $normalizedScans = collect($scanPayload)
            ->values()
            ->map(function ($scan, $index) {
                return [
                    'scan_index' => $index + 1,
                    'actual_sku' => trim((string) ($scan['actual_sku'] ?? '')),
                    'serial_number' => trim((string) ($scan['serial_number'] ?? '')),
                ];
            });

        if ($normalizedScans->isEmpty()) {
            return;
        }

        $skuList = $normalizedScans
            ->pluck('actual_sku')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $skuModels = $this->resolveSkuModels($skuList);
        $metadataMap = $this->resolveSkuMetadataMap($skuList, $skuModels);

        $log->scans()->delete();
        $log->scans()->createMany(
            $normalizedScans->map(function ($scan) use ($metadataMap) {
                $metadata = $metadataMap[$scan['actual_sku']] ?? $this->buildFallbackSkuMetadata($scan['actual_sku']);

                return [
                    'scan_index' => $scan['scan_index'],
                    'actual_sku' => $scan['actual_sku'],
                    'actual_sku_id' => $metadata['sku_id'],
                    'actual_product_name' => $metadata['product_name'] ?: $scan['actual_sku'],
                    'actual_image' => $metadata['image'],
                    'serial_number' => $scan['serial_number'],
                ];
            })->all()
        );
    }

    public function reconcilePendingLogsForOrder(Order $order, ?string $trackingNumber = null): void
    {
        $normalizedTracking = $this->normalizeTrackingNumber($trackingNumber ?: $order->tracking_number);

        if ($normalizedTracking === '') {
            return;
        }

        NoDataGineeLog::query()
            ->whereIn('scan_mode', ['serial_scan', 'tracking_only'])
            ->where(function ($query) {
                $query->whereNull('reconciliation_status')
                    ->orWhereNotIn('reconciliation_status', ['reconciled', 'skipped_already_packed']);
            })
            ->where(function ($query) use ($order, $normalizedTracking) {
                $query->where('order_id', $order->id)
                    ->orWhere(function ($subQuery) use ($normalizedTracking) {
                        $subQuery->whereNull('order_id')
                            ->whereRaw('TRIM(tracking_number) = ?', [$normalizedTracking]);
                    });
            })
            ->orderBy('id')
            ->get()
            ->each(function (NoDataGineeLog $log) use ($order) {
                if (!$log->order_id) {
                    $log->order_id = $order->id;
                    $log->save();
                }

                $this->reconcileLog($log);
            });
    }

    public function reconcileLog(NoDataGineeLog $log): array
    {
        $log->loadMissing([
            'scans' => fn ($query) => $query->orderBy('scan_index'),
            'order.items',
        ]);

        if ($log->scan_mode === 'tracking_only') {
            return $this->reconcileTrackingOnlyLog($log);
        }

        if ($log->scan_mode !== 'serial_scan') {
            return $this->finalizeLogState(
                $log,
                null,
                $log->notes ?: "Tracking {$log->tracking_number} dicatat via No Data Ginee",
                null
            );
        }

        if ($log->scans->isEmpty()) {
            return $this->finalizeLogState(
                $log,
                'pending_reconciliation',
                "Tracking {$log->tracking_number} belum memiliki data scan serial untuk direkonsiliasi",
                null
            );
        }

        $order = $log->order ?: $this->findOrderByTracking($log->tracking_number);

        if (!$order) {
            $this->clearResolvedScans($log);

            return $this->finalizeLogState(
                $log,
                'pending_order',
                "Tracking {$log->tracking_number} dan {$log->scans->count()} scan serial berhasil dicatat. Menunggu data order Ginee masuk untuk rekonsiliasi status.",
                null
            );
        }

        if ((int) $log->order_id !== (int) $order->id) {
            $log->order_id = $order->id;
            $log->save();
        }

        $order->loadMissing('items');

        if ($order->items->isEmpty()) {
            $this->clearResolvedScans($log);

            return $this->finalizeLogState(
                $log,
                'pending_reconciliation',
                "Order #{$order->order_number} untuk tracking {$log->tracking_number} sudah ditemukan, tetapi item order belum siap direkonsiliasi.",
                null,
                $order
            );
        }

        $resolution = $this->buildResolution($order, $log->scans);
        $this->applyResolutionToScans($log, $resolution['resolved_scans']);

        $summary = $this->buildSummary($order, $resolution['resolved_scans']);

        if ($order->is_packed) {
            return $this->finalizeLogState(
                $log,
                'skipped_already_packed',
                "Tracking {$log->tracking_number} punya data scan serial No Data Ginee, tetapi order #{$order->order_number} sudah packed lebih dulu sehingga tidak diterapkan otomatis.",
                null,
                $order,
                $summary,
                $resolution['packing_rows']
            );
        }

        if (!$resolution['is_complete']) {
            return $this->finalizeLogState(
                $log,
                'needs_review',
                "Tracking {$log->tracking_number} berhasil dicocokkan ke order #{$order->order_number}, tetapi qty scan belum lengkap ({$summary['captured_order_qty']} dari {$summary['total_order_qty']}).",
                null,
                $order,
                $summary,
                $resolution['packing_rows']
            );
        }

        try {
            DB::transaction(function () use ($order, $resolution) {
                $lockedOrder = Order::with('items')
                    ->lockForUpdate()
                    ->findOrFail($order->id);

                if ($lockedOrder->is_packed) {
                    throw new \RuntimeException(
                        "Order #{$lockedOrder->order_number} sudah berstatus packed"
                    );
                }

                $this->persistPackingResults($lockedOrder, $resolution['packing_rows']);
                $lockedOrder->update(['is_packed' => 1]);
            });

            $order->refresh();
        } catch (\Throwable $e) {
            return $this->finalizeLogState(
                $log,
                'failed',
                "Tracking {$log->tracking_number} gagal direkonsiliasi otomatis: {$e->getMessage()}",
                null,
                $order,
                $summary,
                $resolution['packing_rows']
            );
        }

        return $this->finalizeLogState(
            $log,
            'reconciled',
            "Tracking {$log->tracking_number} berhasil direkonsiliasi ke order #{$order->order_number}. Hasil: {$summary['sesuai']} sesuai, {$summary['random']} random, {$summary['bonus']} bonus.",
            now(),
            $order,
            $summary,
            $resolution['packing_rows']
        );
    }

    private function reconcileTrackingOnlyLog(NoDataGineeLog $log): array
    {
        $order = $log->order ?: $this->findOrderByTracking($log->tracking_number);

        if (!$order) {
            return $this->finalizeLogState(
                $log,
                'pending_order',
                "Tracking {$log->tracking_number} dicatat via No Data Ginee tracking only dan menunggu data order Ginee masuk.",
                null
            );
        }

        if ((int) $log->order_id !== (int) $order->id) {
            $log->order_id = $order->id;
            $log->save();
        }

        $order->loadMissing('items');

        if ($order->items->isEmpty()) {
            return $this->finalizeLogState(
                $log,
                'pending_reconciliation',
                "Order #{$order->order_number} untuk tracking {$log->tracking_number} sudah ditemukan, tetapi item order belum siap direkonsiliasi.",
                null,
                $order
            );
        }

        if ($order->is_packed) {
            return $this->finalizeLogState(
                $log,
                'skipped_already_packed',
                "Tracking {$log->tracking_number} punya data No Data Ginee tracking only, tetapi order #{$order->order_number} sudah packed lebih dulu sehingga tidak diterapkan otomatis.",
                null,
                $order
            );
        }

        try {
            $deduction = DB::transaction(function () use ($order) {
                $lockedOrder = Order::with('items')
                    ->lockForUpdate()
                    ->findOrFail($order->id);

                if ($lockedOrder->is_packed) {
                    throw new \RuntimeException(
                        "Order #{$lockedOrder->order_number} sudah berstatus packed"
                    );
                }

                $deduction = $this->stockService->deductOrderStock(
                    $lockedOrder,
                    "Packing No Data Ginee tracking only order #{$lockedOrder->order_number}",
                    true
                );

                $lockedOrder->update(['is_packed' => 1]);

                return $deduction;
            });

            $order->refresh();
        } catch (\Throwable $e) {
            return $this->finalizeLogState(
                $log,
                'failed',
                "Tracking {$log->tracking_number} gagal memotong stok gudang secara otomatis: {$e->getMessage()}",
                null,
                $order
            );
        }

        $summaryText = $this->stockService->buildSummaryText($deduction['summary']);
        $notes = "Tracking {$log->tracking_number} dicatat via No Data Ginee tracking only dan order #{$order->order_number} ditandai packed";

        if ($summaryText !== '') {
            $notes .= ". {$summaryText}";
        }

        return $this->finalizeLogState(
            $log,
            'reconciled',
            $notes,
            now(),
            $order,
            $deduction['summary']
        );
    }

    private function buildResolution(Order $order, $scans): array
    {
        $rows = $order->items->values()->map(function ($item) {
            $image = $this->normalizeImageUrl($item->image);

            return [
                'row_type' => 'order_item',
                'order_item_id' => $item->id,
                'actual_sku_id' => null,
                'original_sku' => $item->sku,
                'original_product_name' => $item->product_name,
                'original_image' => $image,
                'actual_sku' => $item->sku,
                'actual_product_name' => $item->product_name,
                'actual_image' => $image,
                'ordered_qty' => (int) $item->quantity,
                'scanned_qty' => 0,
                'status' => 'scan',
                'serials' => [],
                'scan_ids' => [],
            ];
        })->all();

        $resolvedScans = [];

        foreach ($scans as $scan) {
            $scanSku = trim((string) $scan->actual_sku);
            $scanSerial = trim((string) $scan->serial_number);
            $matchingIndex = $this->findMatchingRowIndex($rows, $scanSku);

            if ($matchingIndex !== null) {
                if (empty($rows[$matchingIndex]['actual_sku_id']) && !empty($scan->actual_sku_id)) {
                    $rows[$matchingIndex]['actual_sku_id'] = $scan->actual_sku_id;
                }

                $rows[$matchingIndex]['scanned_qty']++;
                $rows[$matchingIndex]['serials'][] = $scanSerial;
                $rows[$matchingIndex]['scan_ids'][] = $scan->id;
                $rows[$matchingIndex]['status'] = $this->isSameSku(
                    $rows[$matchingIndex]['actual_sku'],
                    $rows[$matchingIndex]['original_sku']
                ) ? 'sesuai' : 'random';

                $resolvedScans[$scan->id] = $this->buildResolvedScanPayload($rows[$matchingIndex], $scan->id);
                continue;
            }

            $pendingIndex = $this->findPendingRowIndex($rows);

            if ($pendingIndex === null) {
                $bonusIndex = $this->findBonusRowIndex($rows, $scanSku);

                if ($bonusIndex === null) {
                    $rows[] = [
                        'row_type' => 'bonus',
                        'order_item_id' => null,
                        'actual_sku_id' => $scan->actual_sku_id,
                        'original_sku' => null,
                        'original_product_name' => null,
                        'original_image' => null,
                        'actual_sku' => $scanSku,
                        'actual_product_name' => $scan->actual_product_name ?: $scanSku,
                        'actual_image' => $scan->actual_image,
                        'ordered_qty' => 0,
                        'scanned_qty' => 1,
                        'status' => 'bonus',
                        'serials' => [$scanSerial],
                        'scan_ids' => [$scan->id],
                    ];
                    $bonusIndex = count($rows) - 1;
                } else {
                    $rows[$bonusIndex]['scanned_qty']++;
                    $rows[$bonusIndex]['serials'][] = $scanSerial;
                    $rows[$bonusIndex]['scan_ids'][] = $scan->id;
                }

                $resolvedScans[$scan->id] = $this->buildResolvedScanPayload($rows[$bonusIndex], $scan->id);
                continue;
            }

            $targetRow = $rows[$pendingIndex];
            $targetStatus = $this->isSameSku($scanSku, $targetRow['original_sku']) ? 'sesuai' : 'random';

            if ($targetRow['scanned_qty'] > 0) {
                $remainingQty = $targetRow['ordered_qty'] - $targetRow['scanned_qty'];

                $rows[$pendingIndex]['ordered_qty'] = $targetRow['scanned_qty'];
                $rows[$pendingIndex]['status'] = $this->isSameSku(
                    $rows[$pendingIndex]['actual_sku'],
                    $rows[$pendingIndex]['original_sku']
                ) ? 'sesuai' : 'random';

                array_splice($rows, $pendingIndex + 1, 0, [[
                    'row_type' => 'order_item',
                    'order_item_id' => $targetRow['order_item_id'],
                    'actual_sku_id' => $scan->actual_sku_id,
                    'original_sku' => $targetRow['original_sku'],
                    'original_product_name' => $targetRow['original_product_name'],
                    'original_image' => $targetRow['original_image'],
                    'actual_sku' => $scanSku,
                    'actual_product_name' => $scan->actual_product_name ?: $scanSku,
                    'actual_image' => $scan->actual_image ?: $targetRow['original_image'],
                    'ordered_qty' => $remainingQty,
                    'scanned_qty' => 1,
                    'status' => $targetStatus,
                    'serials' => [$scanSerial],
                    'scan_ids' => [$scan->id],
                ]]);

                $resolvedScans[$scan->id] = $this->buildResolvedScanPayload($rows[$pendingIndex + 1], $scan->id);
                continue;
            }

            $rows[$pendingIndex]['actual_sku_id'] = $scan->actual_sku_id;
            $rows[$pendingIndex]['actual_sku'] = $scanSku;
            $rows[$pendingIndex]['actual_product_name'] = $scan->actual_product_name ?: $scanSku;
            $rows[$pendingIndex]['actual_image'] = $scan->actual_image ?: $targetRow['original_image'];
            $rows[$pendingIndex]['scanned_qty'] = 1;
            $rows[$pendingIndex]['status'] = $targetStatus;
            $rows[$pendingIndex]['serials'] = [$scanSerial];
            $rows[$pendingIndex]['scan_ids'] = [$scan->id];

            $resolvedScans[$scan->id] = $this->buildResolvedScanPayload($rows[$pendingIndex], $scan->id);
        }

        $packingRows = collect($rows)
            ->map(function ($row) {
                return [
                    'order_item_id' => $row['row_type'] === 'bonus' ? null : $row['order_item_id'],
                    'line_type' => $row['row_type'] === 'bonus' ? 'bonus' : 'order_item',
                    'status' => $row['row_type'] === 'bonus' ? 'bonus' : $row['status'],
                    'original_sku' => $row['original_sku'],
                    'original_product_name' => $row['original_product_name'],
                    'original_image' => $row['original_image'],
                    'actual_sku' => $row['actual_sku'],
                    'actual_sku_id' => $row['actual_sku_id'],
                    'actual_product_name' => $row['actual_product_name'],
                    'actual_image' => $row['actual_image'],
                    'ordered_qty' => (int) $row['ordered_qty'],
                    'scanned_qty' => (int) $row['scanned_qty'],
                    'serials' => $row['serials'],
                ];
            })
            ->all();

        $isComplete = collect($rows)
            ->filter(fn ($row) => $row['row_type'] === 'order_item')
            ->every(fn ($row) => (int) $row['scanned_qty'] === (int) $row['ordered_qty']);

        return [
            'resolved_scans' => $resolvedScans,
            'packing_rows' => $packingRows,
            'is_complete' => $isComplete,
        ];
    }

    private function buildResolvedScanPayload(array $row, int $scanId): array
    {
        return [
            'scan_id' => $scanId,
            'resolved_status' => $row['row_type'] === 'bonus' ? 'bonus' : $row['status'],
            'resolved_order_item_id' => $row['row_type'] === 'bonus' ? null : $row['order_item_id'],
            'resolved_original_sku' => $row['original_sku'],
            'resolved_original_product_name' => $row['original_product_name'],
        ];
    }

    private function applyResolutionToScans(NoDataGineeLog $log, array $resolvedScans): void
    {
        $resolvedAt = now();

        foreach ($log->scans as $scan) {
            $payload = $resolvedScans[$scan->id] ?? null;

            $scan->update([
                'resolved_status' => $payload['resolved_status'] ?? null,
                'resolved_order_item_id' => $payload['resolved_order_item_id'] ?? null,
                'resolved_original_sku' => $payload['resolved_original_sku'] ?? null,
                'resolved_original_product_name' => $payload['resolved_original_product_name'] ?? null,
                'resolved_at' => $payload ? $resolvedAt : null,
            ]);
        }

        $log->unsetRelation('scans');
        $log->load([
            'scans' => fn ($query) => $query->orderBy('scan_index'),
        ]);
    }

    private function clearResolvedScans(NoDataGineeLog $log): void
    {
        $log->scans()->update([
            'resolved_status' => null,
            'resolved_order_item_id' => null,
            'resolved_original_sku' => null,
            'resolved_original_product_name' => null,
            'resolved_at' => null,
        ]);

        $log->unsetRelation('scans');
        $log->load([
            'scans' => fn ($query) => $query->orderBy('scan_index'),
        ]);
    }

    private function persistPackingResults(Order $order, array $packingRows): void
    {
        $stockRequestBySkuId = [];

        foreach ($packingRows as $row) {
            if (!empty($row['actual_sku_id'])) {
                if (!isset($stockRequestBySkuId[$row['actual_sku_id']])) {
                    $stockRequestBySkuId[$row['actual_sku_id']] = [
                        'sku' => $row['actual_sku'],
                        'qty' => 0,
                        'serials' => [],
                    ];
                }
                $stockRequestBySkuId[$row['actual_sku_id']]['qty'] += (int) $row['scanned_qty'];
                $stockRequestBySkuId[$row['actual_sku_id']]['serials'] = array_merge(
                    $stockRequestBySkuId[$row['actual_sku_id']]['serials'],
                    $row['serials'] ?? []
                );
            }
        }

        foreach ($stockRequestBySkuId as $skuId => $stockRequest) {
            $sku = $stockRequest['sku'];
            $serials = $stockRequest['serials'] ?? [];

            foreach ($serials as $serial) {
                $targetSlotId = $this->findSlotForSerial($skuId, $serial);

                if ($targetSlotId !== null) {
                    $workspaceEntry = GudangProdukWorkspaceStockEntry::where('sku_id', $skuId)
                        ->where('slot_id', $targetSlotId)
                        ->lockForUpdate()
                        ->first();

                    if ($workspaceEntry) {
                        $workspaceEntry->qty -= 1;

                        if ($workspaceEntry->qty <= 0) {
                            $workspaceEntry->delete();
                        } else {
                            $workspaceEntry->save();
                        }

                        GudangProdukActivityLog::create([
                            'type' => 'packing_out',
                            'sku_id' => $skuId,
                            'from_slot_id' => $targetSlotId,
                            'to_slot_id' => null,
                            'qty' => 1,
                            'notes' => "Packing order #{$order->order_number} - SKU: {$sku} | Seri: {$serial}",
                            'created_by' => Auth::id(),
                        ]);
                    }
                }
            }
        }

        OrderPackingResult::where('order_id', $order->id)->delete();

        foreach ($packingRows as $row) {
            $serials = $row['serials'];
            unset($row['serials']);

            $packingResult = OrderPackingResult::create(array_merge($row, [
                'order_id' => $order->id,
            ]));

            $packingResult->serials()->createMany(
                collect($serials)->map(fn ($serial) => ['serial_number' => $serial])->all()
            );
        }
    }

    private function buildSummary(Order $order, array $resolvedScans): array
    {
        $counts = collect($resolvedScans)->countBy('resolved_status');

        return [
            'total_order_qty' => (int) $order->items->sum('quantity'),
            'total_scanned_qty' => count($resolvedScans),
            'captured_order_qty' => (int) $counts->get('sesuai', 0) + (int) $counts->get('random', 0),
            'sesuai' => (int) $counts->get('sesuai', 0),
            'random' => (int) $counts->get('random', 0),
            'bonus' => (int) $counts->get('bonus', 0),
        ];
    }

    private function finalizeLogState(
        NoDataGineeLog $log,
        ?string $status,
        string $notes,
        $reconciledAt = null,
        ?Order $order = null,
        ?array $summary = null,
        ?array $rows = null
    ): array {
        $log->update([
            'order_id' => $order?->id ?? $log->order_id,
            'reconciliation_status' => $status,
            'reconciled_at' => $reconciledAt,
            'notes' => $notes,
        ]);

        $log->refresh();

        return [
            'tracking_number' => $log->tracking_number,
            'scan_mode' => $log->scan_mode,
            'reconciliation_status' => $log->reconciliation_status,
            'reconciled_at' => optional($log->reconciled_at)->toDateTimeString(),
            'notes' => $log->notes,
            'order_found' => $order !== null || $log->order_id !== null,
            'order' => $order ? $this->formatOrderPreview($order) : null,
            'summary' => $summary,
            'rows' => $rows,
        ];
    }

    private function formatOrderPreview(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'tracking_number' => $order->tracking_number,
            'customer_name' => $order->customer_name,
            'status' => $order->status,
            'total_qty' => (int) $order->total_qty,
            'is_packed' => (bool) $order->is_packed,
        ];
    }

    private function findOrderByTracking(?string $trackingNumber): ?Order
    {
        $normalizedTracking = $this->normalizeTrackingNumber($trackingNumber);

        if ($normalizedTracking === '') {
            return null;
        }

        $order = Order::with('items')
            ->whereRaw('TRIM(tracking_number) = ?', [$normalizedTracking])
            ->first();

        if ($order) {
            return $order;
        }

        return Order::with('items')
            ->where('tracking_number', $normalizedTracking)
            ->first();
    }

    private function findMatchingRowIndex(array $rows, string $sku): ?int
    {
        foreach ($rows as $index => $row) {
            if (
                $row['row_type'] === 'order_item' &&
                (int) $row['scanned_qty'] < (int) $row['ordered_qty'] &&
                $this->isSameSku($row['actual_sku'], $sku)
            ) {
                return $index;
            }
        }

        return null;
    }

    private function findPendingRowIndex(array $rows): ?int
    {
        foreach ($rows as $index => $row) {
            if (
                $row['row_type'] === 'order_item' &&
                (int) $row['scanned_qty'] < (int) $row['ordered_qty']
            ) {
                return $index;
            }
        }

        return null;
    }

    private function findBonusRowIndex(array $rows, string $sku): ?int
    {
        foreach ($rows as $index => $row) {
            if ($row['row_type'] === 'bonus' && $this->isSameSku($row['actual_sku'], $sku)) {
                return $index;
            }
        }

        return null;
    }

    private function resolveSkuModels(array $skuList): array
    {
        $skuList = collect($skuList)
            ->map(fn ($sku) => trim((string) $sku))
            ->filter()
            ->unique()
            ->values();

        $exactModels = Sku::whereIn('sku', $skuList)->get()->keyBy('sku');
        $resolvedModels = [];

        foreach ($skuList as $sku) {
            if ($exactModels->has($sku)) {
                $resolvedModels[$sku] = $exactModels[$sku];
            }
        }

        $remaining = $skuList->filter(fn ($sku) => !isset($resolvedModels[$sku]))->values();

        if ($remaining->isEmpty()) {
            return $resolvedModels;
        }

        $normalizedIndex = [];
        foreach (Sku::query()->get(['id', 'sku']) as $skuModel) {
            $normalizedSku = $this->normalizeSku($skuModel->sku);
            if ($normalizedSku !== '' && !isset($normalizedIndex[$normalizedSku])) {
                $normalizedIndex[$normalizedSku] = $skuModel;
            }
        }

        foreach ($remaining as $sku) {
            $resolvedModels[$sku] = $normalizedIndex[$this->normalizeSku($sku)] ?? null;
        }

        return $resolvedModels;
    }

    private function resolveSkuMetadataMap(array $skuList, array $skuModels = []): array
    {
        $skuList = collect($skuList)
            ->map(fn ($sku) => trim((string) $sku))
            ->filter()
            ->unique()
            ->values();

        $skuModels = !empty($skuModels) ? $skuModels : $this->resolveSkuModels($skuList->all());

        $resolvedModels = collect($skuModels)
            ->filter(fn ($skuModel) => !empty($skuModel?->id))
            ->unique('id')
            ->values();

        $resolvedSkuStrings = $resolvedModels->pluck('sku');
        $resolvedSkuIds = $resolvedModels->pluck('id');

        $produkSkus = ProdukSku::whereIn('sku', $resolvedSkuStrings)
            ->with('produk')
            ->get()
            ->keyBy('sku');

        $gudangPhotos = GudangProdukDetail::whereIn('sku_id', $resolvedSkuIds)
            ->whereNotNull('foto')
            ->orderBy('id', 'desc')
            ->get()
            ->groupBy('sku_id');

        $result = [];
        foreach ($skuList as $sku) {
            $skuModel = $skuModels[$sku] ?? null;
            $metadata = $this->buildFallbackSkuMetadata($sku);

            if ($skuModel) {
                $produkSku = $produkSkus[$skuModel->sku] ?? null;
                $photoPath = $gudangPhotos->get($skuModel->id)?->first()?->foto;

                $metadata['sku_id'] = $skuModel->id;
                $metadata['resolved_sku'] = $skuModel->sku;
                $metadata['stock_managed'] = true;

                if ($produkSku && $produkSku->produk) {
                    $warna = strtoupper(trim((string) ($produkSku->warna ?? '')));
                    $ukuran = strtoupper(trim((string) ($produkSku->ukuran ?? '')));
                    $suffix = trim(collect([$warna, $ukuran])->filter()->implode(' '));
                    $metadata['product_name'] = trim(
                        $produkSku->produk->nama_produk . ($suffix ? " - {$suffix}" : '')
                    );
                    $metadata['image'] = $this->normalizeImageUrl($produkSku->produk->gambar_produk);
                }

                if ($photoPath) {
                    $metadata['image'] = $this->normalizeImageUrl($photoPath);
                }
            }

            $result[$sku] = $metadata;
        }

        return $result;
    }

    private function buildFallbackSkuMetadata(?string $sku): array
    {
        $sku = trim((string) $sku);

        return [
            'sku' => $sku,
            'sku_id' => null,
            'resolved_sku' => null,
            'product_name' => $sku,
            'image' => null,
            'stock_managed' => false,
        ];
    }

    private function isSameSku(?string $left, ?string $right): bool
    {
        $left = $this->normalizeSku($left);
        $right = $this->normalizeSku($right);

        return $left !== '' && $left === $right;
    }

    private function normalizeSku(?string $sku): string
    {
        return preg_replace('/\s+/', ' ', Str::upper(trim((string) $sku))) ?? '';
    }

    private function normalizeTrackingNumber($trackingNumber): string
    {
        return trim((string) $trackingNumber);
    }

    private function normalizeImageUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return asset('storage/' . ltrim($path, '/'));
    }
}
