<?php

namespace App\Services;

use App\Models\NoDataGineeLog;
use App\Models\OrderLog;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PackingLogReportService
{
    public function prepareFilters(array $filters): array
    {
        $startDate = !empty($filters['start_date'])
            ? Carbon::parse($filters['start_date'])->startOfDay()
            : null;

        $endDate = !empty($filters['end_date'])
            ? Carbon::parse($filters['end_date'])->endOfDay()
            : null;

        return [
            'start_date' => $startDate ? $startDate->toDateString() : null,
            'end_date' => $endDate ? $endDate->toDateString() : null,
            'start_at' => $startDate,
            'end_at' => $endDate,
            'status' => $this->cleanString($filters['status'] ?? null),
            'tracking_number' => $this->cleanString($filters['tracking_number'] ?? null),
            'performed_by' => $this->cleanString($filters['performed_by'] ?? null),
            'mode' => $this->normalizeMode($filters['mode'] ?? null),
        ];
    }

    public function paginateLogs(array $filters, int $perPage = 20, ?string $cursor = null): array
    {
        $paginator = $this->buildIndexQuery($filters)
            ->cursorPaginate($perPage, ['*'], 'cursor', $cursor)
            ->withQueryString();

        return [
            'data' => collect($paginator->items())->map(function ($row) {
                return $this->transformIndexRow($row);
            })->values()->all(),
            'path' => $paginator->path(),
            'per_page' => $paginator->perPage(),
            'next_page_url' => $paginator->nextPageUrl(),
            'prev_page_url' => $paginator->previousPageUrl(),
            'next_cursor' => optional($paginator->nextCursor())->encode(),
            'prev_cursor' => optional($paginator->previousCursor())->encode(),
        ];
    }

    public function getSummary(array $filters): array
    {
        $mode = $filters['mode'] ?? null;
        $orderLogActions = $this->getOrderLogActionsForMode($mode);

        $baseSummary = [
            'total_order' => 0,
            'total_items' => 0,
            'total_amount' => 0,
        ];

        $orderKasir = collect();

        if ($orderLogActions !== []) {
            $latestOrderLogsQuery = $this->buildLatestOrderLogSummaryQuery($filters, $orderLogActions);

            $summaryRow = DB::query()
                ->fromSub(clone $latestOrderLogsQuery, 'summary_rows')
                ->selectRaw(
                    'COUNT(*) as total_order, COALESCE(SUM(CASE WHEN action = ? THEN total_packed_qty ELSE total_qty END), 0) as total_items, COALESCE(SUM(total_amount), 0) as total_amount',
                    ['scan_validasi_random']
                )
                ->first();

            $baseSummary = [
                'total_order' => (int) ($summaryRow->total_order ?? 0),
                'total_items' => (int) ($summaryRow->total_items ?? 0),
                'total_amount' => (float) ($summaryRow->total_amount ?? 0),
            ];

            $orderKasir = DB::query()
                ->fromSub(clone $latestOrderLogsQuery, 'summary_rows')
                ->selectRaw('performed_by, COUNT(*) as total_orders')
                ->groupBy('performed_by')
                ->orderByDesc('total_orders')
                ->get()
                ->map(function ($row) {
                    return [
                        'performed_by' => $row->performed_by,
                        'total_orders' => (int) $row->total_orders,
                    ];
                });
        }

        $ndgCount = 0;
        $ndgTotalItems = 0;
        $ndgTotalAmount = 0;
        $ndgKasir = collect();

        if ($this->shouldIncludeNoDataGineeLogs($mode)) {
            $ndgBaseQuery = $this->buildNoDataGineeSummaryBaseQuery($filters);

            $ndgSummaryRow = (clone $ndgBaseQuery)
                ->selectRaw(
                    'COUNT(*) as total_order,
                     COALESCE(SUM(COALESCE(linked_orders.total_qty, matched_orders.total_qty, 0)), 0) as total_items,
                     COALESCE(SUM(COALESCE(linked_orders.total_amount, matched_orders.total_amount, 0)), 0) as total_amount'
                )
                ->first();

            $ndgCount       = (int)   ($ndgSummaryRow->total_order  ?? 0);
            $ndgTotalItems  = (int)   ($ndgSummaryRow->total_items  ?? 0);
            $ndgTotalAmount = (float) ($ndgSummaryRow->total_amount ?? 0);

            $ndgKasir = (clone $ndgBaseQuery)
                ->selectRaw('ndg.scanner_name as performed_by, COUNT(*) as total_orders')
                ->groupBy('ndg.scanner_name')
                ->orderByDesc('total_orders')
                ->get()
                ->map(function ($row) {
                    return [
                        'performed_by' => $row->performed_by,
                        'total_orders' => (int) $row->total_orders,
                    ];
                });
        }

        $kasirSummary = $orderKasir
            ->concat($ndgKasir)
            ->groupBy('performed_by')
            ->map(function (Collection $rows, $performedBy) {
                return [
                    'performed_by' => $performedBy,
                    'total_orders' => $rows->sum('total_orders'),
                ];
            })
            ->sortByDesc('total_orders')
            ->values();

        return [
            'filters' => [
                'start_date'     => $filters['start_date']     ?? 'Semua',
                'end_date'       => $filters['end_date']       ?? 'Semua',
                'status'         => $filters['status']         ?? 'Semua',
                'mode'           => $filters['mode']           ?? 'Semua',
                'tracking_number'=> $filters['tracking_number']?? 'Semua',
                'performed_by'   => $filters['performed_by']   ?? 'Semua',
            ],
            'data' => [[
                'total_order'  => $baseSummary['total_order'] + $ndgCount,
                'total_items'  => $baseSummary['total_items'] + $ndgTotalItems,
                'total_amount' => $baseSummary['total_amount'] + $ndgTotalAmount,
            ]],
            'kasir_summary' => $kasirSummary,
        ];
    }

    public function getDetail(string $sourceType, int $sourceId): array
    {
        if ($sourceType === 'order_log') {
            return $this->buildOrderLogDetail($sourceId);
        }

        if ($sourceType === 'no_data_ginee') {
            return $this->buildNoDataGineeDetail($sourceId);
        }

        abort(404, 'Sumber log tidak ditemukan');
    }

    public function buildExportQuery(array $filters): Builder
    {
        return $this->buildIndexQuery($filters);
    }

    public function getModeLabel(?string $action): string
    {
        if ($action === 'scan_validasi_random') {
            return 'Random';
        }

        if ($action === 'scan_validasi_belum_barcode') {
            return 'Belum Barcode';
        }

        if ($action === 'scan_no_data_ginee') {
            return 'No Data Ginee';
        }

        return 'Normal';
    }

    public function normalizeMode($mode): ?string
    {
        $normalized = strtolower(trim((string) $mode));

        if ($normalized === '') {
            return null;
        }

        $allowedModes = ['normal', 'random', 'belum-barcode', 'no-data-ginee'];

        return in_array($normalized, $allowedModes, true) ? $normalized : null;
    }

    public function getOrderLogActionsForMode(?string $mode): ?array
    {
        if ($mode === null) {
            return null;
        }

        if ($mode === 'normal') {
            return ['scan_validasi'];
        }

        if ($mode === 'random') {
            return ['scan_validasi_random'];
        }

        if ($mode === 'belum-barcode') {
            return ['scan_validasi_belum_barcode'];
        }

        if ($mode === 'no-data-ginee') {
            return [];
        }

        return null;
    }

    public function shouldIncludeNoDataGineeLogs(?string $mode): bool
    {
        return $mode === null || $mode === 'no-data-ginee';
    }

    private function buildIndexQuery(array $filters): Builder
    {
        $mode = $filters['mode'] ?? null;
        $orderLogActions = $this->getOrderLogActionsForMode($mode);
        $queries = [];

        if ($orderLogActions !== []) {
            $queries[] = $this->buildOrderLogsIndexSourceQuery($filters, $orderLogActions);
        }

        if ($this->shouldIncludeNoDataGineeLogs($mode)) {
            $queries[] = $this->buildNoDataGineeIndexSourceQuery($filters);
        }

        if (empty($queries)) {
            $queries[] = $this->buildEmptyIndexSourceQuery();
        }

        $sourceQuery = array_shift($queries);

        foreach ($queries as $query) {
            $sourceQuery->unionAll($query);
        }

        return DB::query()
            ->fromSub($sourceQuery, 'packing_logs_index')
            ->orderByDesc('created_at')
            ->orderByDesc('source_priority')
            ->orderByDesc('source_id');
    }

    private function buildOrderLogsIndexSourceQuery(array $filters, ?array $orderLogActions): Builder
    {
        $packingTotals = $this->buildPackingTotalsSubQuery();

        $query = DB::table('order_logs as ol')
            ->join('order as orders', 'orders.id', '=', 'ol.order_id')
            ->leftJoinSub($packingTotals, 'packing_totals', function ($join) {
                $join->on('packing_totals.order_id', '=', 'ol.order_id');
            })
            ->selectRaw(
                "1 as source_priority,
                'order_log' as source_type,
                ol.id as source_id,
                ol.order_id as order_id,
                ol.action as action,
                ol.performed_by as performed_by,
                ol.notes as notes,
                ol.created_at as created_at,
                orders.order_number as order_number,
                orders.tracking_number as tracking_number,
                orders.status as order_status,
                COALESCE(orders.total_amount, 0) as total_amount,
                COALESCE(orders.total_qty, 0) as total_qty,
                COALESCE(packing_totals.total_packed_qty, 0) as total_packed_qty,
                CASE WHEN ol.action = 'scan_validasi_random' THEN COALESCE(packing_totals.total_packed_qty, 0) ELSE COALESCE(orders.total_qty, 0) END as total_items,
                1 as has_detail,
                'Klik detail' as serial_preview"
            );

        return $this->applyOrderLogFilters($query, $filters, $orderLogActions);
    }

    private function buildNoDataGineeIndexSourceQuery(array $filters): Builder
    {
        $query = $this->buildNoDataGineeBaseQuery()
            ->selectRaw(
                "0 as source_priority,
                'no_data_ginee' as source_type,
                ndg.id as source_id,
                COALESCE(linked_orders.id, matched_orders.id) as order_id,
                'scan_no_data_ginee' as action,
                ndg.scanner_name as performed_by,
                ndg.notes as notes,
                ndg.created_at as created_at,
                COALESCE(linked_orders.order_number, matched_orders.order_number) as order_number,
                COALESCE(linked_orders.tracking_number, matched_orders.tracking_number, ndg.tracking_number) as tracking_number,
                COALESCE(linked_orders.status, matched_orders.status, 'NO DATA GINEE') as order_status,
                COALESCE(linked_orders.total_amount, matched_orders.total_amount, 0) as total_amount,
                COALESCE(linked_orders.total_qty, matched_orders.total_qty, 0) as total_qty,
                0 as total_packed_qty,
                COALESCE(linked_orders.total_qty, matched_orders.total_qty, 0) as total_items,
                0 as has_detail,
                '-' as serial_preview"
            );

        return $this->applyNoDataGineeFilters($query, $filters);
    }

    private function buildEmptyIndexSourceQuery(): Builder
    {
        return DB::table('order_logs as ol')
            ->selectRaw(
                "1 as source_priority,
                'order_log' as source_type,
                ol.id as source_id,
                ol.order_id as order_id,
                ol.action as action,
                ol.performed_by as performed_by,
                ol.notes as notes,
                ol.created_at as created_at,
                NULL as order_number,
                NULL as tracking_number,
                NULL as order_status,
                0 as total_amount,
                0 as total_qty,
                0 as total_packed_qty,
                0 as total_items,
                0 as has_detail,
                '-' as serial_preview"
            )
            ->whereRaw('1 = 0');
    }

    private function buildLatestOrderLogSummaryQuery(array $filters, ?array $orderLogActions): Builder
    {
        $filteredLogs = $this->applyOrderLogFilters(
            DB::table('order_logs as ol')
                ->join('order as orders', 'orders.id', '=', 'ol.order_id')
                ->select('ol.id', 'ol.order_id', 'ol.action', 'ol.performed_by'),
            $filters,
            $orderLogActions
        );

        $latestIds = DB::query()
            ->fromSub($filteredLogs, 'filtered_order_logs')
            ->selectRaw('MAX(id) as latest_log_id')
            ->groupBy('order_id');

        $packingTotals = $this->buildPackingTotalsSubQuery();

        return DB::table('order_logs as ol')
            ->joinSub($latestIds, 'latest_logs', function ($join) {
                $join->on('latest_logs.latest_log_id', '=', 'ol.id');
            })
            ->join('order as orders', 'orders.id', '=', 'ol.order_id')
            ->leftJoinSub($packingTotals, 'packing_totals', function ($join) {
                $join->on('packing_totals.order_id', '=', 'ol.order_id');
            })
            ->selectRaw(
                'ol.id, ol.order_id, ol.action, ol.performed_by, COALESCE(orders.total_amount, 0) as total_amount, COALESCE(orders.total_qty, 0) as total_qty, COALESCE(packing_totals.total_packed_qty, 0) as total_packed_qty'
            );
    }

    private function buildNoDataGineeSummaryBaseQuery(array $filters): Builder
    {
        return $this->applyNoDataGineeFilters($this->buildNoDataGineeBaseQuery(), $filters);
    }

    private function buildPackingTotalsSubQuery(): Builder
    {
        return DB::table('order_packing_results')
            ->selectRaw('order_id, COALESCE(SUM(scanned_qty), 0) as total_packed_qty')
            ->groupBy('order_id');
    }

    private function applyOrderLogFilters(Builder $query, array $filters, ?array $orderLogActions): Builder
    {
        if (!empty($filters['start_at']) && !empty($filters['end_at'])) {
            $query->whereBetween('ol.created_at', [$filters['start_at'], $filters['end_at']]);
        }

        if (!empty($filters['status'])) {
            $query->where('orders.status', $filters['status']);
        }

        if (!empty($filters['tracking_number'])) {
            $query->where('orders.tracking_number', 'LIKE', '%' . $filters['tracking_number'] . '%');
        }

        if (!empty($filters['performed_by'])) {
            $query->where('ol.performed_by', 'LIKE', '%' . $filters['performed_by'] . '%');
        }

        if ($orderLogActions !== null) {
            $query->whereIn('ol.action', $orderLogActions);
        }

        return $query;
    }

    private function applyNoDataGineeFilters(Builder $query, array $filters): Builder
    {
        if (!empty($filters['start_at']) && !empty($filters['end_at'])) {
            $query->whereBetween('ndg.created_at', [$filters['start_at'], $filters['end_at']]);
        }

        if (!empty($filters['status'])) {
            $status = $filters['status'];
            $query->where(function ($subQuery) use ($status) {
                $subQuery->where('linked_orders.status', $status)
                    ->orWhere('matched_orders.status', $status);
            });
        }

        if (!empty($filters['tracking_number'])) {
            $tracking = '%' . $filters['tracking_number'] . '%';
            $query->where(function ($subQuery) use ($tracking) {
                $subQuery->where('ndg.tracking_number', 'LIKE', $tracking)
                    ->orWhere('linked_orders.tracking_number', 'LIKE', $tracking)
                    ->orWhere('matched_orders.tracking_number', 'LIKE', $tracking);
            });
        }

        if (!empty($filters['performed_by'])) {
            $query->where('ndg.scanner_name', 'LIKE', '%' . $filters['performed_by'] . '%');
        }

        return $query;
    }

    private function buildNoDataGineeBaseQuery(): Builder
    {
        return DB::table('no_data_ginee_logs as ndg')
            ->leftJoin('order as linked_orders', 'linked_orders.id', '=', 'ndg.order_id')
            ->leftJoin('order as matched_orders', function ($join) {
                $join->whereNull('ndg.order_id')
                    ->whereRaw('TRIM(matched_orders.tracking_number) = TRIM(ndg.tracking_number)');
            });
    }

    private function transformIndexRow($row): array
    {
        return [
            'id' => $row->source_type . '-' . $row->source_id,
            'source_type' => $row->source_type,
            'source_id' => (int) $row->source_id,
            'source_priority' => (int) ($row->source_priority ?? 0),
            'action' => $row->action,
            'mode_label' => $this->getModeLabel($row->action),
            'performed_by' => $row->performed_by,
            'notes' => $row->notes,
            'created_at' => $row->created_at,
            'total_items' => (int) ($row->total_items ?? 0),
            'serial_preview' => $row->serial_preview,
            'has_detail' => (bool) $row->has_detail,
            'order' => [
                'id' => $row->order_id ? (int) $row->order_id : null,
                'order_number' => $row->order_number,
                'tracking_number' => $row->tracking_number,
                'status' => $row->order_status,
                'total_amount' => (float) ($row->total_amount ?? 0),
                'total_qty' => (int) ($row->total_qty ?? 0),
                'total_packed_qty' => (int) ($row->total_packed_qty ?? 0),
            ],
        ];
    }

    private function buildOrderLogDetail(int $sourceId): array
    {
        $log = OrderLog::select('id', 'order_id', 'action', 'performed_by', 'notes', 'created_at')
            ->findOrFail($sourceId);

        $relations = ['order:id,order_number,tracking_number,status,total_amount,total_qty'];

        if ($log->action === 'scan_validasi_random') {
            $relations[] = 'order.packingResults:id,order_id,order_item_id,line_type,status,original_sku,actual_sku,scanned_qty';
            $relations[] = 'order.packingResults.serials:id,order_packing_result_id,serial_number';
        } elseif ($log->action === 'scan_validasi_belum_barcode') {
            $relations[] = 'order.items:id,order_id,sku,quantity';
        } else {
            $relations[] = 'order.items:id,order_id,sku,quantity';
            $relations[] = 'order.items.serials:id,order_item_id,serial_number';
        }

        $log->load($relations);

        return [
            'id' => 'order_log-' . $log->id,
            'source_type' => 'order_log',
            'source_id' => $log->id,
            'action' => $log->action,
            'mode_label' => $this->getModeLabel($log->action),
            'performed_by' => $log->performed_by,
            'notes' => $log->notes,
            'created_at' => optional($log->created_at)->toDateTimeString(),
            'has_detail' => true,
            'order' => $this->mapOrderDetail($log->order),
            'rows' => $this->mapOrderLogDetailRows($log),
        ];
    }

    private function buildNoDataGineeDetail(int $sourceId): array
    {
        $log = NoDataGineeLog::with('order:id,order_number,tracking_number,status,total_amount,total_qty')
            ->findOrFail($sourceId);

        $order = $log->order ?: $this->findCurrentOrderByTrackingNumber($log->tracking_number);

        return [
            'id' => 'no_data_ginee-' . $log->id,
            'source_type' => 'no_data_ginee',
            'source_id' => $log->id,
            'action' => 'scan_no_data_ginee',
            'mode_label' => $this->getModeLabel('scan_no_data_ginee'),
            'performed_by' => $log->scanner_name,
            'notes' => $log->notes,
            'created_at' => optional($log->created_at)->toDateTimeString(),
            'has_detail' => false,
            'order' => $order ? $this->mapOrderDetail($order) : [
                'id' => null,
                'order_number' => null,
                'tracking_number' => $log->tracking_number,
                'status' => 'NO DATA GINEE',
                'total_amount' => 0,
                'total_qty' => 0,
            ],
            'rows' => [],
        ];
    }

    private function findCurrentOrderByTrackingNumber(?string $trackingNumber)
    {
        $normalized = $this->cleanString($trackingNumber);

        if ($normalized === null) {
            return null;
        }

        return \App\Models\Order::query()
            ->whereRaw('TRIM(tracking_number) = ?', [$normalized])
            ->first(['id', 'order_number', 'tracking_number', 'status', 'total_amount', 'total_qty']);
    }

    private function mapOrderDetail($order): array
    {
        if (!$order) {
            return [
                'id' => null,
                'order_number' => null,
                'tracking_number' => null,
                'status' => null,
                'total_amount' => 0,
                'total_qty' => 0,
            ];
        }

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'tracking_number' => $order->tracking_number,
            'status' => $order->status,
            'total_amount' => (float) ($order->total_amount ?? 0),
            'total_qty' => (int) ($order->total_qty ?? 0),
        ];
    }

    private function mapOrderLogDetailRows(OrderLog $log): array
    {
        if (!$log->order) {
            return [];
        }

        if ($log->action === 'scan_validasi_random') {
            return $log->order->packingResults
                ->flatMap(function ($item) {
                    return collect($item->serials)->map(function ($serial) use ($item) {
                        return [
                            'key' => $item->id . '-' . ($serial->id ?? $serial->serial_number),
                            'sku' => $item->actual_sku,
                            'originalSku' => $item->original_sku,
                            'quantity' => 1,
                            'status' => $item->status,
                            'serial_number' => $serial->serial_number,
                        ];
                    });
                })
                ->values()
                ->all();
        }

        if ($log->action === 'scan_validasi_belum_barcode') {
            return $log->order->items
                ->map(function ($item) use ($log) {
                    return [
                        'key' => $log->id . '-' . ($item->id ?? $item->sku),
                        'sku' => $item->sku,
                        'originalSku' => null,
                        'quantity' => (int) ($item->quantity ?? 0),
                        'status' => 'belum barcode',
                        'serial_number' => '-',
                    ];
                })
                ->values()
                ->all();
        }

        return $log->order->items
            ->flatMap(function ($item) {
                return collect($item->serials)->map(function ($serial) use ($item) {
                    return [
                        'key' => $item->sku . '-' . ($serial->id ?? $serial->serial_number),
                        'sku' => $item->sku,
                        'originalSku' => null,
                        'quantity' => 1,
                        'status' => 'sesuai',
                        'serial_number' => $serial->serial_number,
                    ];
                });
            })
            ->values()
            ->all();
    }

    private function cleanString($value): ?string
    {
        $cleaned = trim((string) $value);

        return $cleaned === '' ? null : $cleaned;
    }
}
