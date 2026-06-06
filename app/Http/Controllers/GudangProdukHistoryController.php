<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class GudangProdukHistoryController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureRequiredTablesReady();

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:200',
            'movement_type' => 'nullable|string|in:in,out',
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 50);
        $movementType = $validated['movement_type'] ?? null;

        if ($startDate && $endDate && $startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $filteredRowsQuery = $this->applyFilters(
            DB::query()->fromSub($this->buildBaseRowsQuery(), 'history_rows'),
            $search,
            $startDate,
            $endDate,
            $movementType
        );

        $summaryRow = DB::query()
            ->fromSub((clone $filteredRowsQuery), 'history_summary')
            ->selectRaw('COUNT(*) as total_rows')
            ->selectRaw('COALESCE(SUM(qty), 0) as total_qty')
            ->selectRaw("COALESCE(SUM(CASE WHEN movement_type = 'in' THEN qty ELSE 0 END), 0) as total_qty_masuk")
            ->selectRaw("COALESCE(SUM(CASE WHEN movement_type = 'out' THEN qty ELSE 0 END), 0) as total_qty_keluar")
            ->selectRaw('COUNT(DISTINCT sku) as total_sku')
            ->selectRaw('COUNT(DISTINCT kode_seri) as total_seri')
            ->first();

        $totalRows = (int) ($summaryRow->total_rows ?? 0);

        if ($totalRows === 0) {
            return response()->json([
                'data' => [],
                'summary' => [
                    'total_rows' => 0,
                    'total_qty' => 0,
                    'total_qty_masuk' => 0,
                    'total_qty_keluar' => 0,
                    'total_sku' => 0,
                    'total_seri' => 0,
                ],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'last_page' => 1,
                ],
            ]);
        }

        $lastPage = max((int) ceil($totalRows / $perPage), 1);
        $currentPage = min($page, $lastPage);

        $rows = (clone $filteredRowsQuery)
            ->orderByDesc('happened_at')
            ->orderByDesc('id')
            ->forPage($currentPage, $perPage)
            ->get();

        $data = $rows->map(function ($row) {
            $happenedAt = !empty($row->happened_at)
                ? Carbon::parse($row->happened_at)->toISOString()
                : null;

            return [
                'id' => (string) $row->id,
                'movementType' => trim((string) ($row->movement_type ?? 'out')),
                'movementLabel' => trim((string) ($row->movement_label ?? 'Barang Keluar')),
                'sku' => trim((string) ($row->sku ?? '')),
                'qty' => (int) ($row->qty ?? 0),
                'kodeSeri' => trim((string) ($row->kode_seri ?? '')),
                'keluarPada' => $happenedAt,
                'happenedAt' => $happenedAt,
                'sourceLabel' => trim((string) ($row->source_label ?? '')),
            ];
        })->values()->all();

        return response()->json([
            'data' => $data,
            'summary' => [
                'total_rows' => $totalRows,
                'total_qty' => (int) ($summaryRow->total_qty ?? 0),
                'total_qty_masuk' => (int) ($summaryRow->total_qty_masuk ?? 0),
                'total_qty_keluar' => (int) ($summaryRow->total_qty_keluar ?? 0),
                'total_sku' => (int) ($summaryRow->total_sku ?? 0),
                'total_seri' => (int) ($summaryRow->total_seri ?? 0),
            ],
            'pagination' => [
                'current_page' => $currentPage,
                'per_page' => $perPage,
                'total' => $totalRows,
                'last_page' => $lastPage,
            ],
        ]);
    }

    private function buildBaseRowsQuery(): Builder
    {
        $normalRows = DB::table('order_item_serials as serials')
            ->join('order_items as items', 'items.id', '=', 'serials.order_item_id')
            ->join('order as orders', 'orders.id', '=', 'items.order_id')
            ->where('orders.is_packed', 1)
            ->whereNotNull('serials.serial_number')
            ->selectRaw("CONCAT('normal-', serials.id) as id")
            ->selectRaw("'out' as movement_type")
            ->selectRaw("'Barang Keluar' as movement_label")
            ->selectRaw('items.sku as sku')
            ->selectRaw('1 as qty')
            ->selectRaw('serials.serial_number as kode_seri')
            ->selectRaw('serials.created_at as happened_at')
            ->selectRaw("'Packing Normal' as source_label");

        $packedRows = DB::table('order_packing_result_serials as serials')
            ->join('order_packing_results as results', 'results.id', '=', 'serials.order_packing_result_id')
            ->join('order as orders', 'orders.id', '=', 'results.order_id')
            ->where('orders.is_packed', 1)
            ->whereNotNull('serials.serial_number')
            ->whereNotNull('results.actual_sku')
            ->selectRaw("CONCAT('packing-', serials.id) as id")
            ->selectRaw("'out' as movement_type")
            ->selectRaw("'Barang Keluar' as movement_label")
            ->selectRaw('results.actual_sku as sku')
            ->selectRaw('1 as qty')
            ->selectRaw('serials.serial_number as kode_seri')
            ->selectRaw('serials.created_at as happened_at')
            ->selectRaw("'Packing Result' as source_label");

        $activityRows = DB::table('gudang_produk_activity_logs as logs')
            ->join('skus as skus', 'skus.id', '=', 'logs.sku_id')
            ->where(function ($query) {
                $query->where('logs.type', 'placement')
                    ->orWhere(function ($mutationQuery) {
                        $mutationQuery->where('logs.type', 'mutation')
                            ->whereNull('logs.to_slot_id');
                    });
            })
            ->selectRaw("CONCAT('activity-', logs.id) as id")
            ->selectRaw("CASE WHEN logs.type = 'placement' THEN 'in' ELSE 'out' END as movement_type")
            ->selectRaw("CASE WHEN logs.type = 'placement' THEN 'Barang Masuk' ELSE 'Barang Keluar' END as movement_label")
            ->selectRaw('skus.sku as sku')
            ->selectRaw('logs.qty as qty')
            ->selectRaw('logs.notes as kode_seri')
            ->selectRaw('logs.created_at as happened_at')
            ->selectRaw("
                CASE
                    WHEN logs.type = 'placement' AND logs.notes LIKE 'Stok Opname%' THEN 'Stok Opname Masuk'
                    WHEN logs.type = 'mutation' AND logs.notes LIKE 'Stok Opname%' THEN 'Stok Opname Keluar'
                    WHEN logs.type = 'placement' THEN 'Barang Masuk Gudang'
                    ELSE 'Mutasi/Koreksi Keluar'
                END as source_label
            ");

        return $normalRows
            ->unionAll($packedRows)
            ->unionAll($activityRows);
    }

    private function applyFilters(
        Builder $query,
        string $search,
        ?string $startDate,
        ?string $endDate,
        ?string $movementType = null
    ): Builder {
        if ($search !== '') {
            $searchTerm = '%' . addcslashes($search, '\\%_') . '%';

            $query->where(function ($searchQuery) use ($searchTerm) {
                $searchQuery->where('sku', 'like', $searchTerm)
                    ->orWhere('kode_seri', 'like', $searchTerm)
                    ->orWhere('movement_label', 'like', $searchTerm)
                    ->orWhere('source_label', 'like', $searchTerm);
            });
        }

        if ($startDate) {
            $query->whereDate('happened_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('happened_at', '<=', $endDate);
        }

        if ($movementType) {
            $query->where('movement_type', $movementType);
        }

        return $query;
    }

    public function mutations(Request $request)
    {
        $this->ensureRequiredTablesReady();

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:200',
            'status' => 'nullable|string|in:pending,executed,cancelled',
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 50);
        $status = $validated['status'] ?? 'executed';

        if ($startDate && $endDate && $startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        // Fetch layouts and aliases lookup maps to resolve slot codes/names
        $layoutsMap = DB::table('gudang_produk_layouts')
            ->pluck('name', 'uid')
            ->all();

        $aliasesMap = DB::table('gudang_produk_slot_aliases')
            ->pluck('alias', 'slot_id')
            ->all();

        if ($status === 'pending' || $status === 'cancelled') {
            $query = DB::table('gudang_produk_mutation_sessions as sessions')
                ->join('skus as skus', 'skus.id', '=', 'sessions.sku_id')
                ->leftJoin('users as creators', 'creators.id', '=', 'sessions.created_by')
                ->where('sessions.status', $status)
                ->select([
                    'sessions.id',
                    'sessions.status',
                    'sessions.from_slot_id',
                    DB::raw('NULL as to_slot_id'),
                    'sessions.barcodes',
                    'sessions.notes',
                    'sessions.created_at',
                    'skus.sku as sku_code',
                    'creators.name as creator_name'
                ]);

            if ($search !== '') {
                $searchTerm = '%' . addcslashes($search, '\\%_') . '%';
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('skus.sku', 'like', $searchTerm)
                      ->orWhere('sessions.from_slot_id', 'like', $searchTerm)
                      ->orWhere('sessions.notes', 'like', $searchTerm);
                });
            }

            if ($startDate) {
                $query->whereDate('sessions.created_at', '>=', $startDate);
            }
            if ($endDate) {
                $query->whereDate('sessions.created_at', '<=', $endDate);
            }

            $totalRows = $query->count();
            $lastPage = max((int) ceil($totalRows / $perPage), 1);
            $currentPage = min($page, $lastPage);

            $rows = $query->orderByDesc('sessions.created_at')
                ->orderByDesc('sessions.id')
                ->forPage($currentPage, $perPage)
                ->get();

            $data = $rows->map(function ($row) use ($layoutsMap, $aliasesMap) {
                $barcodes = json_decode($row->barcodes, true) ?? [];
                $qty = count($barcodes);

                return [
                    'id' => (string) $row->id,
                    'status' => $row->status,
                    'sku' => $row->sku_code,
                    'qty' => $qty,
                    'from_slot' => $this->resolveSlotLabel($row->from_slot_id, $layoutsMap, $aliasesMap),
                    'to_slot' => null,
                    'barcodes' => $barcodes,
                    'notes' => $row->notes,
                    'creator_name' => $row->creator_name ?? 'System',
                    'created_at' => Carbon::parse($row->created_at)->toISOString(),
                ];
            });

            // Calculate overall total qty using JSON_LENGTH
            $summaryTotalQtyQuery = DB::table('gudang_produk_mutation_sessions as sessions')
                ->join('skus as skus', 'skus.id', '=', 'sessions.sku_id')
                ->where('sessions.status', $status);

            if ($search !== '') {
                $searchTerm = '%' . addcslashes($search, '\\%_') . '%';
                $summaryTotalQtyQuery->where(function ($q) use ($searchTerm) {
                    $q->where('skus.sku', 'like', $searchTerm)
                      ->orWhere('sessions.from_slot_id', 'like', $searchTerm)
                      ->orWhere('sessions.notes', 'like', $searchTerm);
                });
            }
            if ($startDate) {
                $summaryTotalQtyQuery->whereDate('sessions.created_at', '>=', $startDate);
            }
            if ($endDate) {
                $summaryTotalQtyQuery->whereDate('sessions.created_at', '<=', $endDate);
            }

            $totalQty = (int) $summaryTotalQtyQuery->sum(DB::raw('JSON_LENGTH(sessions.barcodes)'));

            return response()->json([
                'data' => $data,
                'summary' => [
                    'total_rows' => $totalRows,
                    'total_qty' => $totalQty,
                ],
                'pagination' => [
                    'current_page' => $currentPage,
                    'per_page' => $perPage,
                    'total' => $totalRows,
                    'last_page' => $lastPage,
                ],
            ]);
        } else {
            $query = DB::table('gudang_produk_activity_logs as logs')
                ->join('skus as skus', 'skus.id', '=', 'logs.sku_id')
                ->leftJoin('users as creators', 'creators.id', '=', 'logs.created_by')
                ->where('logs.type', 'mutation')
                ->select([
                    'logs.id',
                    DB::raw("'executed' as status"),
                    'logs.from_slot_id',
                    'logs.to_slot_id',
                    'logs.qty',
                    'logs.notes',
                    'logs.created_at',
                    'skus.sku as sku_code',
                    'creators.name as creator_name'
                ]);

            if ($search !== '') {
                $searchTerm = '%' . addcslashes($search, '\\%_') . '%';
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('skus.sku', 'like', $searchTerm)
                      ->orWhere('logs.from_slot_id', 'like', $searchTerm)
                      ->orWhere('logs.to_slot_id', 'like', $searchTerm)
                      ->orWhere('logs.notes', 'like', $searchTerm);
                });
            }

            if ($startDate) {
                $query->whereDate('logs.created_at', '>=', $startDate);
            }
            if ($endDate) {
                $query->whereDate('logs.created_at', '<=', $endDate);
            }

            $totalRows = $query->count();
            $lastPage = max((int) ceil($totalRows / $perPage), 1);
            $currentPage = min($page, $lastPage);

            $rows = $query->orderByDesc('logs.created_at')
                ->orderByDesc('logs.id')
                ->forPage($currentPage, $perPage)
                ->get();

            $data = $rows->map(function ($row) use ($layoutsMap, $aliasesMap) {
                return [
                    'id' => (string) $row->id,
                    'status' => 'executed',
                    'sku' => $row->sku_code,
                    'qty' => (int) $row->qty,
                    'from_slot' => $this->resolveSlotLabel($row->from_slot_id, $layoutsMap, $aliasesMap),
                    'to_slot' => $this->resolveSlotLabel($row->to_slot_id, $layoutsMap, $aliasesMap),
                    'notes' => $row->notes,
                    'creator_name' => $row->creator_name ?? 'System',
                    'created_at' => Carbon::parse($row->created_at)->toISOString(),
                ];
            });

            // Calculate overall total qty
            $summaryTotalQtyQuery = DB::table('gudang_produk_activity_logs as logs')
                ->join('skus as skus', 'skus.id', '=', 'logs.sku_id')
                ->where('logs.type', 'mutation');

            if ($search !== '') {
                $searchTerm = '%' . addcslashes($search, '\\%_') . '%';
                $summaryTotalQtyQuery->where(function ($q) use ($searchTerm) {
                    $q->where('skus.sku', 'like', $searchTerm)
                      ->orWhere('logs.from_slot_id', 'like', $searchTerm)
                      ->orWhere('logs.to_slot_id', 'like', $searchTerm)
                      ->orWhere('logs.notes', 'like', $searchTerm);
                });
            }
            if ($startDate) {
                $summaryTotalQtyQuery->whereDate('logs.created_at', '>=', $startDate);
            }
            if ($endDate) {
                $summaryTotalQtyQuery->whereDate('logs.created_at', '<=', $endDate);
            }

            $totalQty = (int) $summaryTotalQtyQuery->sum('logs.qty');

            return response()->json([
                'data' => $data,
                'summary' => [
                    'total_rows' => $totalRows,
                    'total_qty' => $totalQty,
                ],
                'pagination' => [
                    'current_page' => $currentPage,
                    'per_page' => $perPage,
                    'total' => $totalRows,
                    'last_page' => $lastPage,
                ],
            ]);
        }
    }

    private function resolveSlotLabel(?string $slotId, array $layoutsMap, array $aliasesMap): ?string
    {
        if (empty($slotId)) {
            return null;
        }

        // Parse standard format layout_xxx__Fxxx__Bxxx__Rxxx__ROWxxx
        if (preg_match('/^(.+?)__F(\d+)__B(.+?)__R(\d+)__ROW(\d+)$/', $slotId, $matches)) {
            $layoutUid = $matches[1];
            $floor = (int) $matches[2];
            $block = strtoupper($matches[3]);
            $rack = (int) $matches[4];
            $row = (int) $matches[5];

            $layoutName = $layoutsMap[$layoutUid] ?? $layoutUid;
            $slotCode = sprintf('L%s%s%s/%s', $floor, $block, str_pad((string)$rack, 2, '0', STR_PAD_LEFT), $row);
            
            $alias = $aliasesMap[$slotId] ?? null;
            $slotName = $alias ?: $slotCode;

            return "{$layoutName} - {$slotName}";
        }

        // Fallback checks for alias / layout maps even if layout ID format is custom
        $alias = $aliasesMap[$slotId] ?? null;
        if ($alias) {
            return $alias;
        }

        return $slotId;
    }

    private function ensureRequiredTablesReady(): void
    {
        $requiredTables = [
            'order',
            'order_items',
            'order_item_serials',
            'order_packing_results',
            'order_packing_result_serials',
            'skus',
            'gudang_produk_activity_logs',
            'gudang_produk_mutation_sessions',
            'gudang_produk_layouts',
            'gudang_produk_slot_aliases',
        ];

        foreach ($requiredTables as $table) {
            if (!Schema::hasTable($table)) {
                throw ValidationException::withMessages([
                    'workspace' => ['Tabel history gudang produk belum siap. Jalankan migrasi backend terlebih dahulu.'],
                ]);
            }
        }
    }
}
