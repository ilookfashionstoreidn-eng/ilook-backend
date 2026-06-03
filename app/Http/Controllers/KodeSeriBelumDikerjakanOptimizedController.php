<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KodeSeriBelumDikerjakanOptimizedController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:10|max:200',
            'search' => 'nullable|string|max:120',
            'type' => 'nullable|in:all,cutting,jasa',
            'potong' => 'nullable|in:all,sudah,belum',
            'sort_by' => 'nullable|in:deadline,kode_seri,nama_produk,jumlah,type,process_count',
            'sort_direction' => 'nullable|in:asc,desc',
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 50);
        $search = trim((string) ($validated['search'] ?? ''));
        $typeFilter = $validated['type'] ?? 'all';
        $potongFilter = $validated['potong'] ?? 'all';
        $sortBy = $validated['sort_by'] ?? 'deadline';
        $sortDirection = $validated['sort_direction'] ?? 'asc';

        $statistics = $this->normalizeAggregate(
            $this->aggregateSeries(
                $this->buildSeriesQuery($this->buildPreferredRowsQuery())
            )
        );

        $searchScopedSeriesQuery = $this->applySearch(
            $this->buildSeriesQuery($this->buildPreferredRowsQuery()),
            $search
        );

        // Apply potong filter on a clone of searchScopedSeriesQuery
        $potongScopedSeriesQuery = clone $searchScopedSeriesQuery;
        if ($potongFilter !== 'all') {
            if ($potongFilter === 'sudah') {
                $potongScopedSeriesQuery->whereNotNull('hasil_cutting_id');
            } else if ($potongFilter === 'belum') {
                $potongScopedSeriesQuery->whereNull('hasil_cutting_id');
            }
        }

        // Process line filter counts are calculated from the potong-filtered query
        $filterCounts = $this->buildFilterCounts(
            $this->normalizeAggregate(
                $this->aggregateSeries(clone $potongScopedSeriesQuery)
            )
        );

        // Calculate potong filter counts based on the type-filtered query
        $typeScopedSeriesQuery = clone $searchScopedSeriesQuery;
        if ($typeFilter !== 'all') {
            $typeScopedSeriesQuery->where('preferred_type', $typeFilter);
        }

        $potongCounts = $this->buildPotongCounts(
            $this->normalizeAggregate(
                $this->aggregateSeries(clone $typeScopedSeriesQuery)
            )
        );

        // Final query filters by both type and potong
        $filteredSeriesQuery = clone $potongScopedSeriesQuery;
        if ($typeFilter !== 'all') {
            $filteredSeriesQuery->where('preferred_type', $typeFilter);
        }

        $querySummary = $this->normalizeAggregate(
            $this->aggregateSeries(clone $filteredSeriesQuery)
        );

        $paginator = $this->applySort(clone $filteredSeriesQuery, $sortBy, $sortDirection)
            ->paginate($perPage, ['*'], 'page', $page);

        $seriesCollection = collect($paginator->items());
        $seriesCodes = $seriesCollection->pluck('kode_seri')->values()->all();

        $detailRows = collect();
        if (!empty($seriesCodes)) {
            $detailRows = $this->buildPreferredRowsQuery()
                ->whereIn('base_rows.kode_seri', $seriesCodes)
                ->orderBy('base_rows.kode_seri')
                ->orderByRaw("CASE WHEN base_rows.type = 'jasa' THEN 0 ELSE 1 END")
                ->orderBy('base_rows.id_distribusi')
                ->orderBy('base_rows.id_jasa')
                ->get()
                ->groupBy('kode_seri');
        }

        $today = Carbon::today();

        $data = $seriesCollection->map(function ($item) use ($detailRows, $today) {
            $deadline = $item->deadline;
            $isOverDeadline = $deadline ? Carbon::parse($deadline)->lt($today) : false;

            $distribusiList = $detailRows
                ->get($item->kode_seri, collect())
                ->map(function ($row) {
                    return [
                        'id_distribusi' => $row->id_distribusi ? (int) $row->id_distribusi : null,
                        'id_jasa' => $row->id_jasa ? (int) $row->id_jasa : null,
                        'type' => $row->type,
                        'deadline' => $row->deadline,
                        'jumlah_qty' => (int) $row->jumlah_qty,
                    ];
                })
                ->values()
                ->all();

            return [
                'kode_seri' => $item->kode_seri,
                'nama_produk' => $item->nama_produk ?? 'Produk Tidak Diketahui',
                'product_size' => $item->product_size ?? null,
                'product_colour' => $item->product_colour ?? null,
                'created_at' => $item->created_at,
                'deadline' => $deadline,
                'jumlah' => (int) $item->jumlah,
                'process_count' => (int) $item->process_count,
                'preferred_type' => $item->preferred_type,
                'is_over_deadline' => $isOverDeadline,
                'distribusi_list' => $distribusiList,
                'hasil_cutting_id' => $item->hasil_cutting_id ? (int) $item->hasil_cutting_id : null,
            ];
        })->values();

        return response()->json([
            'data' => $data,
            'statistics' => $statistics,
            'query_summary' => $querySummary,
            'filter_counts' => $filterCounts,
            'potong_counts' => $potongCounts,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'search' => $search,
                'type_filter' => $typeFilter,
                'potong_filter' => $potongFilter,
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection,
            ],
        ], 200);
    }

    private function buildPreferredRowsQuery(): Builder
    {
        $preferredTypeQuery = DB::query()
            ->fromSub($this->buildBaseRowsQuery(), 'base_rows')
            ->select('kode_seri')
            ->selectRaw("
                CASE
                    WHEN SUM(CASE WHEN type = 'jasa' THEN 1 ELSE 0 END) > 0
                        THEN 'jasa'
                    ELSE 'cutting'
                END as preferred_type
            ")
            ->groupBy('kode_seri');

        return DB::query()
            ->fromSub($this->buildBaseRowsQuery(), 'base_rows')
            ->joinSub($preferredTypeQuery, 'preferred_rows', function ($join) {
                $join->on('base_rows.kode_seri', '=', 'preferred_rows.kode_seri');
            })
            ->whereColumn('base_rows.type', 'preferred_rows.preferred_type')
            ->select('base_rows.*', 'preferred_rows.preferred_type');
    }

    private function buildBaseRowsQuery(): Builder
    {
        $cuttingRows = DB::table('spk_cutting_distribusi as distribusi')
            ->join('spk_cutting as cutting', 'cutting.id', '=', 'distribusi.spk_cutting_id')
            ->leftJoin('product_lists as pl', 'pl.id', '=', 'cutting.product_list_id')
            ->whereNotNull('distribusi.kode_seri')
            ->where('distribusi.kode_seri', '!=', '')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('spk_cmt as spk_cmt_cutting')
                    ->join('spk_cutting_distribusi as distribusi_cmt', function ($join) {
                        $join->on('distribusi_cmt.id', '=', 'spk_cmt_cutting.source_id')
                            ->where('spk_cmt_cutting.source_type', '=', 'cutting');
                    })
                    ->whereColumn('distribusi_cmt.kode_seri', 'distribusi.kode_seri');
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('spk_cmt as spk_cmt_jasa')
                    ->join('spk_jasa as jasa_cmt', function ($join) {
                        $join->on('jasa_cmt.id', '=', 'spk_cmt_jasa.source_id')
                            ->where('spk_cmt_jasa.source_type', '=', 'jasa');
                    })
                    ->join('spk_cutting_distribusi as distribusi_jasa_cmt', 'distribusi_jasa_cmt.id', '=', 'jasa_cmt.spk_cutting_distribusi_id')
                    ->whereColumn('distribusi_jasa_cmt.kode_seri', 'distribusi.kode_seri');
            })
            ->selectRaw("
                distribusi.kode_seri as kode_seri,
                COALESCE(pl.product_group, pl.product, 'Produk Tidak Diketahui') as nama_produk,
                COALESCE(
                    (
                        SELECT pl_detail.product_size 
                        FROM spk_cutting_distribusi_detail detail 
                        JOIN product_lists pl_detail ON pl_detail.id = detail.product_list_id 
                        WHERE detail.spk_cutting_distribusi_id = distribusi.id 
                        LIMIT 1
                    ),
                    pl.product_size
                ) as product_size,
                pl.product_colour as product_colour,
                cutting.tanggal_batas_kirim as deadline,
                cutting.created_at as created_at,
                COALESCE(distribusi.jumlah_produk, 0) as jumlah_qty,
                'cutting' as type,
                distribusi.id as id_distribusi,
                NULL as id_jasa,
                distribusi.hasil_cutting_id as hasil_cutting_id
            ");

        $jasaRows = DB::table('spk_jasa as jasa')
            ->join('spk_cutting_distribusi as distribusi', 'distribusi.id', '=', 'jasa.spk_cutting_distribusi_id')
            ->join('spk_cutting as cutting', 'cutting.id', '=', 'distribusi.spk_cutting_id')
            ->leftJoin('product_lists as pl', 'pl.id', '=', 'cutting.product_list_id')
            ->whereNotNull('distribusi.kode_seri')
            ->where('distribusi.kode_seri', '!=', '')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('spk_cmt as spk_cmt_cutting')
                    ->join('spk_cutting_distribusi as distribusi_cmt', function ($join) {
                        $join->on('distribusi_cmt.id', '=', 'spk_cmt_cutting.source_id')
                            ->where('spk_cmt_cutting.source_type', '=', 'cutting');
                    })
                    ->whereColumn('distribusi_cmt.kode_seri', 'distribusi.kode_seri');
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('spk_cmt as spk_cmt_jasa')
                    ->join('spk_jasa as jasa_cmt', function ($join) {
                        $join->on('jasa_cmt.id', '=', 'spk_cmt_jasa.source_id')
                            ->where('spk_cmt_jasa.source_type', '=', 'jasa');
                    })
                    ->join('spk_cutting_distribusi as distribusi_jasa_cmt', 'distribusi_jasa_cmt.id', '=', 'jasa_cmt.spk_cutting_distribusi_id')
                    ->whereColumn('distribusi_jasa_cmt.kode_seri', 'distribusi.kode_seri');
            })
            ->selectRaw("
                distribusi.kode_seri as kode_seri,
                COALESCE(pl.product_group, pl.product, 'Produk Tidak Diketahui') as nama_produk,
                COALESCE(
                    (
                        SELECT pl_detail.product_size 
                        FROM spk_cutting_distribusi_detail detail 
                        JOIN product_lists pl_detail ON pl_detail.id = detail.product_list_id 
                        WHERE detail.spk_cutting_distribusi_id = distribusi.id 
                        LIMIT 1
                    ),
                    pl.product_size
                ) as product_size,
                pl.product_colour as product_colour,
                cutting.tanggal_batas_kirim as deadline,
                cutting.created_at as created_at,
                COALESCE(jasa.jumlah, 0) as jumlah_qty,
                'jasa' as type,
                distribusi.id as id_distribusi,
                jasa.id as id_jasa,
                distribusi.hasil_cutting_id as hasil_cutting_id
            ");

        return $cuttingRows->unionAll($jasaRows);
    }

    private function buildSeriesQuery(Builder $rowsQuery): Builder
    {
        return DB::query()
            ->fromSub($rowsQuery, 'series_rows')
            ->select('kode_seri')
            ->selectRaw('MAX(nama_produk) as nama_produk')
            ->selectRaw('MAX(product_size) as product_size')
            ->selectRaw('MAX(product_colour) as product_colour')
            ->selectRaw('MIN(deadline) as deadline')
            ->selectRaw('MIN(created_at) as created_at')
            ->selectRaw('SUM(jumlah_qty) as jumlah')
            ->selectRaw('COUNT(*) as process_count')
            ->selectRaw('MAX(preferred_type) as preferred_type')
            ->selectRaw('MAX(hasil_cutting_id) as hasil_cutting_id')
            ->groupBy('kode_seri');
    }

    private function applySearch(Builder $query, string $search): Builder
    {
        if ($search === '') {
            return $query;
        }

        $searchTerm = '%' . addcslashes($search, '\\%_') . '%';

        return $query->where(function ($searchQuery) use ($searchTerm) {
            $searchQuery->where('kode_seri', 'like', $searchTerm)
                ->orWhere('nama_produk', 'like', $searchTerm);
        });
    }

    private function applySort(Builder $query, string $sortBy, string $sortDirection): Builder
    {
        $orderedQuery = DB::query()->fromSub($query, 'series_result');

        if ($sortBy === 'deadline') {
            return $orderedQuery
                ->orderByRaw('CASE WHEN deadline IS NULL THEN 1 ELSE 0 END ASC')
                ->orderBy('deadline', $sortDirection)
                ->orderBy('kode_seri', 'asc');
        }

        $columnMap = [
            'kode_seri' => 'kode_seri',
            'nama_produk' => 'nama_produk',
            'jumlah' => 'jumlah',
            'type' => 'preferred_type',
            'process_count' => 'process_count',
        ];

        $column = $columnMap[$sortBy] ?? 'kode_seri';

        return $orderedQuery
            ->orderBy($column, $sortDirection)
            ->orderByRaw('CASE WHEN deadline IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('deadline', 'asc')
            ->orderBy('kode_seri', 'asc');
    }

    private function aggregateSeries(Builder $query): object
    {
        return DB::query()
            ->fromSub($query, 'series_summary')
            ->selectRaw('COUNT(*) as jumlah_spk')
            ->selectRaw('COUNT(*) as jumlah_produk')
            ->selectRaw('COALESCE(SUM(jumlah), 0) as jumlah_qty')
            ->selectRaw("
                COALESCE(SUM(
                    CASE
                        WHEN deadline IS NOT NULL AND deadline < CURDATE() THEN 1
                        ELSE 0
                    END
                ), 0) as jumlah_over_deadline
            ")
            ->selectRaw("
                COALESCE(SUM(
                    CASE
                        WHEN deadline IS NOT NULL 
                             AND deadline >= CURDATE() 
                             AND deadline <= DATE_ADD(CURDATE(), INTERVAL 10 DAY) THEN 1
                        ELSE 0
                    END
                ), 0) as jumlah_warning_deadline
            ")
            ->selectRaw("
                COALESCE(SUM(
                    CASE
                        WHEN deadline IS NULL OR deadline >= CURDATE() THEN 1
                        ELSE 0
                    END
                ), 0) as jumlah_belum_deadline
            ")
            ->selectRaw("
                COALESCE(SUM(
                    CASE
                        WHEN preferred_type = 'cutting' THEN 1
                        ELSE 0
                    END
                ), 0) as count_cutting
            ")
            ->selectRaw("
                COALESCE(SUM(
                    CASE
                        WHEN preferred_type = 'jasa' THEN 1
                        ELSE 0
                    END
                ), 0) as count_jasa
            ")
            ->selectRaw("
                COALESCE(SUM(
                    CASE
                        WHEN hasil_cutting_id IS NOT NULL THEN 1
                        ELSE 0
                    END
                ), 0) as count_sudah_potong
            ")
            ->selectRaw("
                COALESCE(SUM(
                    CASE
                        WHEN hasil_cutting_id IS NULL THEN 1
                        ELSE 0
                    END
                ), 0) as count_belum_potong
            ")
            ->selectRaw('COALESCE(SUM(process_count), 0) as process_lines')
            ->first();
    }

    private function normalizeAggregate(?object $aggregate): array
    {
        return [
            'jumlah_spk' => (int) ($aggregate->jumlah_spk ?? 0),
            'jumlah_produk' => (int) ($aggregate->jumlah_produk ?? 0),
            'jumlah_qty' => (int) ($aggregate->jumlah_qty ?? 0),
            'jumlah_over_deadline' => (int) ($aggregate->jumlah_over_deadline ?? 0),
            'jumlah_warning_deadline' => (int) ($aggregate->jumlah_warning_deadline ?? 0),
            'jumlah_belum_deadline' => (int) ($aggregate->jumlah_belum_deadline ?? 0),
            'count_cutting' => (int) ($aggregate->count_cutting ?? 0),
            'count_jasa' => (int) ($aggregate->count_jasa ?? 0),
            'process_lines' => (int) ($aggregate->process_lines ?? 0),
            'count_sudah_potong' => (int) ($aggregate->count_sudah_potong ?? 0),
            'count_belum_potong' => (int) ($aggregate->count_belum_potong ?? 0),
        ];
    }

    private function buildFilterCounts(array $aggregate): array
    {
        return [
            'all' => $aggregate['jumlah_spk'] ?? 0,
            'cutting' => $aggregate['count_cutting'] ?? 0,
            'jasa' => $aggregate['count_jasa'] ?? 0,
        ];
    }

    private function buildPotongCounts(array $aggregate): array
    {
        return [
            'all' => $aggregate['jumlah_spk'] ?? 0,
            'belum' => $aggregate['count_belum_potong'] ?? 0,
            'sudah' => $aggregate['count_sudah_potong'] ?? 0,
        ];
    }
}
