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
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 50);

        if ($startDate && $endDate && $startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $filteredRowsQuery = $this->applyFilters(
            DB::query()->fromSub($this->buildBaseRowsQuery(), 'history_rows'),
            $search,
            $startDate,
            $endDate
        );

        $summaryRow = DB::query()
            ->fromSub((clone $filteredRowsQuery), 'history_summary')
            ->selectRaw('COUNT(*) as total_rows')
            ->selectRaw('COALESCE(SUM(qty), 0) as total_qty')
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
            ->orderByDesc('keluar_pada')
            ->orderByDesc('id')
            ->forPage($currentPage, $perPage)
            ->get();

        $data = $rows->map(function ($row) {
            return [
                'id' => (string) $row->id,
                'sku' => trim((string) ($row->sku ?? '')),
                'qty' => (int) ($row->qty ?? 0),
                'kodeSeri' => trim((string) ($row->kode_seri ?? '')),
                'keluarPada' => !empty($row->keluar_pada)
                    ? Carbon::parse($row->keluar_pada)->toISOString()
                    : null,
                'sourceLabel' => trim((string) ($row->source_label ?? '')),
            ];
        })->values()->all();

        return response()->json([
            'data' => $data,
            'summary' => [
                'total_rows' => $totalRows,
                'total_qty' => (int) ($summaryRow->total_qty ?? 0),
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
            ->selectRaw('items.sku as sku')
            ->selectRaw('1 as qty')
            ->selectRaw('serials.serial_number as kode_seri')
            ->selectRaw('serials.created_at as keluar_pada')
            ->selectRaw("'Packing Normal' as source_label");

        $packedRows = DB::table('order_packing_result_serials as serials')
            ->join('order_packing_results as results', 'results.id', '=', 'serials.order_packing_result_id')
            ->join('order as orders', 'orders.id', '=', 'results.order_id')
            ->where('orders.is_packed', 1)
            ->whereNotNull('serials.serial_number')
            ->whereNotNull('results.actual_sku')
            ->selectRaw("CONCAT('packing-', serials.id) as id")
            ->selectRaw('results.actual_sku as sku')
            ->selectRaw('1 as qty')
            ->selectRaw('serials.serial_number as kode_seri')
            ->selectRaw('serials.created_at as keluar_pada')
            ->selectRaw("'Packing Result' as source_label");

        return $normalRows->unionAll($packedRows);
    }

    private function applyFilters(
        Builder $query,
        string $search,
        ?string $startDate,
        ?string $endDate
    ): Builder {
        if ($search !== '') {
            $searchTerm = '%' . addcslashes($search, '\\%_') . '%';

            $query->where(function ($searchQuery) use ($searchTerm) {
                $searchQuery->where('sku', 'like', $searchTerm)
                    ->orWhere('kode_seri', 'like', $searchTerm)
                    ->orWhere('source_label', 'like', $searchTerm);
            });
        }

        if ($startDate) {
            $query->whereDate('keluar_pada', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('keluar_pada', '<=', $endDate);
        }

        return $query;
    }

    private function ensureRequiredTablesReady(): void
    {
        $requiredTables = [
            'order',
            'order_items',
            'order_item_serials',
            'order_packing_results',
            'order_packing_result_serials',
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
