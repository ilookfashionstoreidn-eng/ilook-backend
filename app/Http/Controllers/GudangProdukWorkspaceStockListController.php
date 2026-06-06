<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class GudangProdukWorkspaceStockListController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureWorkspaceTablesReady();

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 50);

        $filteredRowsQuery = $this->buildFilteredRowsQuery($search);
        $summary = $this->buildSummary(clone $filteredRowsQuery);

        if ($summary['total_rows'] === 0) {
            return response()->json([
                'data' => [],
                'summary' => $summary,
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'last_page' => 1,
                ],
            ]);
        }

        $lastPage = max((int) ceil($summary['total_rows'] / $perPage), 1);
        $currentPage = min($page, $lastPage);

        $rows = $this->applyDefaultSort(clone $filteredRowsQuery)
            ->forPage($currentPage, $perPage)
            ->get();

        $data = $rows->map(function ($row) {
            $skuCode = trim((string) ($row->sku ?? ''));
            $productName = trim((string) ($row->product_name ?? ''));
            $warna = (string) ($row->warna ?? '');
            $ukuran = (string) ($row->ukuran ?? '');
            $updatedAt = !empty($row->updated_at)
                ? Carbon::parse($row->updated_at)->toISOString()
                : null;

            return [
                'id' => (int) $row->id,
                'layoutId' => $row->layout_uid,
                'layoutName' => $row->layout_name,
                'slotId' => $row->slot_id,
                'namaGudang' => $row->nama_gudang ?: $row->slot_id,
                'skuId' => (int) $row->sku_id,
                'sku' => $skuCode !== '' ? $skuCode : 'SKU tanpa kode',
                'skuLabel' => $this->buildSkuLabel($productName, $skuCode, $warna, $ukuran),
                'productName' => $productName,
                'qtyAwal' => (int) $row->qty_awal,
                'qtyMasuk' => (int) $row->qty_masuk,
                'qtyKeluar' => (int) $row->qty_keluar,
                'qtySisa' => (int) $row->qty_sisa,
                'updatedAt' => $updatedAt,
            ];
        })->values()->all();

        return response()->json([
            'data' => $data,
            'summary' => $summary,
            'pagination' => [
                'current_page' => $currentPage,
                'per_page' => $perPage,
                'total' => $summary['total_rows'],
                'last_page' => $lastPage,
            ],
        ]);
    }

    /**
     * GET /gudang-produk-workspace/list-stok-product/seri-detail
     *
     * Mengembalikan daftar kode seri yang MASIH TERSISA untuk kombinasi SKU + slot tertentu.
     * Kode seri diambil dari notes activity log tipe placement.
     * Karena log keluar tidak mencatat kode seri individual, diasumsikan seri yang
     * pertama masuk adalah yang pertama keluar (FIFO): seri paling akhir masih ada.
     */
    public function seriDetail(Request $request)
    {
        $this->ensureWorkspaceTablesReady();

        $validated = $request->validate([
            'sku_id'  => 'required|integer|min:1',
            'slot_id' => 'required|string|max:500',
        ]);

        $skuId  = (int) $validated['sku_id'];
        $slotId = (string) $validated['slot_id'];

        // Cek stok yang masih ada di gudang
        $stockEntry = DB::table('gudang_produk_stock_entries')
            ->where('sku_id', $skuId)
            ->where('slot_id', $slotId)
            ->where('qty', '>', 0)
            ->first(['qty']);

        $qtySisa = $stockEntry ? (int) $stockEntry->qty : 0;

        // Ambil semua activity log placement untuk SKU + slot ini (urut dari yang terlama)
        $logs = DB::table('gudang_produk_activity_logs')
            ->where('sku_id', $skuId)
            ->where('to_slot_id', $slotId)
            ->where('type', 'placement')
            ->whereNotNull('notes')
            ->orderBy('created_at', 'asc')
            ->get(['notes', 'created_at', 'qty']);

        // Kumpulkan semua kode seri berurutan (dari yang terlama ke terbaru)
        $seriList = [];

        foreach ($logs as $log) {
            $notes = (string) ($log->notes ?? '');

            // Format: "Scan produk masuk | Kode seri: AL-01.5"
            if (preg_match('/Kode seri:\s*(.+?)(?:\s*\|.*)?$/i', $notes, $matches)) {
                $rawSeri = trim($matches[1]);
                $parts = array_filter(array_map('trim', explode(',', $rawSeri)));
                foreach ($parts as $part) {
                    if ($part !== '') {
                        $seriList[] = $part;
                    }
                }
            } elseif (preg_match('/Seri:\s*(.+?)(?:\s*\|.*)?$/i', $notes, $matches)) {
                // Format stok opname: "Stok Opname | Seri: KODE1, KODE2"
                $rawSeri = trim($matches[1]);
                $parts = array_filter(array_map('trim', explode(',', $rawSeri)));
                foreach ($parts as $part) {
                    if ($part !== '') {
                        $seriList[] = $part;
                    }
                }
            }
        }

        // Deduplicate (preserve order, keep last occurrence)
        $seen = [];
        $uniqueSeri = [];
        foreach (array_reverse($seriList) as $kode) {
            if (!isset($seen[$kode])) {
                $seen[$kode] = true;
                $uniqueSeri[] = $kode;
            }
        }
        // Kembalikan ke urutan terbaru di atas (reverse lagi agar terbaru di atas)
        // $uniqueSeri sudah dalam urutan: terbaru dulu (hasil reverse dari array asc)

        $totalScanned = count($uniqueSeri);

        // Hanya tampilkan sejumlah qty_sisa (asumsi FIFO: yang terlama keluar duluan)
        // uniqueSeri saat ini: terbaru dulu → yang tersisa adalah terbaru sejumlah qtySisa
        $seriTersisa = $qtySisa > 0
            ? array_slice($uniqueSeri, 0, $qtySisa)
            : [];

        // Ambil info SKU
        $sku = DB::table('skus')->where('id', $skuId)->first(['sku']);
        $skuCode = $sku ? (string) $sku->sku : '-';

        return response()->json([
            'sku_id'         => $skuId,
            'sku'            => $skuCode,
            'slot_id'        => $slotId,
            'qty_sisa'       => $qtySisa,
            'total_scanned'  => $totalScanned,
            'total_seri'     => count($seriTersisa),
            'seri'           => $seriTersisa,
        ]);
    }


    private function buildFilteredRowsQuery(string $search): Builder
    {
        $query = DB::query()->fromSub($this->buildBaseRowsQuery(), 'stock_rows');

        return $this->applySearch($query, $search);
    }

    private function buildBaseRowsQuery(): Builder
    {
        $slotCodeExpression = $this->buildSlotCodeExpression('gse.slot_id');

        $outgoingQtyQuery = DB::table('gudang_produk_stock_entries as active_entries')
            ->leftJoin('gudang_produk_activity_logs as gal', function ($join) {
                $join->on('active_entries.sku_id', '=', 'gal.sku_id')
                    ->on('active_entries.slot_id', '=', 'gal.from_slot_id')
                    ->whereNotNull('gal.from_slot_id')
                    ->whereIn('gal.type', ['mutation', 'packing_out'])
                    ->whereColumn('gal.created_at', '>=', 'active_entries.created_at');
            })
            ->where('active_entries.qty', '>', 0)
            ->groupBy('active_entries.id')
            ->selectRaw('active_entries.id as stock_entry_id, COALESCE(SUM(gal.qty), 0) as qty_keluar');

        $earliestPlacementQuery = DB::table('gudang_produk_activity_logs as l1')
            ->joinSub(
                DB::table('gudang_produk_activity_logs')
                    ->where('type', 'placement')
                    ->groupBy(['sku_id', 'to_slot_id'])
                    ->selectRaw('sku_id, to_slot_id, MIN(id) as first_id'),
                'first_log',
                'first_log.first_id',
                '=',
                'l1.id'
            )
            ->select(['l1.sku_id', 'l1.to_slot_id', 'l1.qty as qty_awal']);

        return DB::table('gudang_produk_stock_entries as gse')
            ->join('gudang_produk_layouts as layouts', 'layouts.id', '=', 'gse.layout_id')
            ->join('skus as skus', 'skus.id', '=', 'gse.sku_id')
            ->leftJoin('produk_sku as produk_sku', 'produk_sku.sku', '=', 'skus.sku')
            ->leftJoin('produk as produk', 'produk.id', '=', 'produk_sku.produk_id')
            ->leftJoinSub($outgoingQtyQuery, 'outgoing_qty', function ($join) {
                $join->on('outgoing_qty.stock_entry_id', '=', 'gse.id');
            })
            ->leftJoinSub($earliestPlacementQuery, 'earliest_placement', function ($join) {
                $join->on('earliest_placement.sku_id', '=', 'gse.sku_id')
                    ->on('earliest_placement.to_slot_id', '=', 'gse.slot_id');
            })
            ->where('gse.qty', '>', 0)
            ->select([
                'gse.id',
                'layouts.uid as layout_uid',
                'layouts.name as layout_name',
                'gse.slot_id',
                'gse.sku_id',
                'skus.sku',
                'produk.nama_produk as product_name',
                'produk_sku.warna',
                'produk_sku.ukuran',
                'gse.updated_at',
            ])
            ->selectRaw("{$slotCodeExpression} as nama_gudang")
            ->selectRaw('gse.qty as qty_sisa')
            ->selectRaw('COALESCE(outgoing_qty.qty_keluar, 0) as qty_keluar')
            ->selectRaw('COALESCE(earliest_placement.qty_awal, gse.qty) as qty_awal')
            ->selectRaw('GREATEST(0, (gse.qty + COALESCE(outgoing_qty.qty_keluar, 0)) - COALESCE(earliest_placement.qty_awal, gse.qty)) as qty_masuk');
    }

    private function applySearch(Builder $query, string $search): Builder
    {
        if ($search === '') {
            return $query;
        }

        $searchTerm = '%' . addcslashes($search, '\\%_') . '%';

        return $query->where(function ($searchQuery) use ($searchTerm) {
            $searchQuery->where('sku', 'like', $searchTerm)
                ->orWhere('product_name', 'like', $searchTerm)
                ->orWhere('warna', 'like', $searchTerm)
                ->orWhere('ukuran', 'like', $searchTerm)
                ->orWhere('layout_name', 'like', $searchTerm)
                ->orWhere('nama_gudang', 'like', $searchTerm)
                ->orWhere('slot_id', 'like', $searchTerm);
        });
    }

    private function applyDefaultSort(Builder $query): Builder
    {
        return $query
            ->orderByRaw("CASE WHEN product_name IS NULL OR product_name = '' THEN 1 ELSE 0 END ASC")
            ->orderBy('product_name')
            ->orderBy('warna')
            ->orderBy('ukuran')
            ->orderBy('sku')
            ->orderBy('nama_gudang');
    }

    private function buildSummary(Builder $query): array
    {
        $aggregate = DB::query()
            ->fromSub($query, 'stock_summary')
            ->selectRaw('COUNT(*) as total_rows')
            ->selectRaw('COALESCE(SUM(qty_awal), 0) as total_qty_awal')
            ->selectRaw('COALESCE(SUM(qty_masuk), 0) as total_qty_masuk')
            ->selectRaw('COALESCE(SUM(qty_keluar), 0) as total_qty_keluar')
            ->selectRaw('COALESCE(SUM(qty_sisa), 0) as total_qty_sisa')
            ->selectRaw('COUNT(DISTINCT slot_id) as total_locations')
            ->first();

        return [
            'total_rows' => (int) ($aggregate->total_rows ?? 0),
            'total_qty_awal' => (int) ($aggregate->total_qty_awal ?? 0),
            'total_qty_masuk' => (int) ($aggregate->total_qty_masuk ?? 0),
            'total_qty_keluar' => (int) ($aggregate->total_qty_keluar ?? 0),
            'total_qty_sisa' => (int) ($aggregate->total_qty_sisa ?? 0),
            'total_locations' => (int) ($aggregate->total_locations ?? 0),
        ];
    }

    private function buildSkuLabel(string $productName, string $skuCode, string $warna, string $ukuran): string
    {
        $variant = trim(
            implode(' ', array_filter([
                strtoupper(trim($warna)),
                strtoupper(trim($ukuran)),
            ]))
        );

        $label = trim(
            implode(' - ', array_filter([
                $productName,
                $variant,
            ]))
        );

        if ($label !== '') {
            return $label;
        }

        return $skuCode !== '' ? $skuCode : 'SKU tanpa nama';
    }

    private function buildSlotCodeExpression(string $slotIdColumn): string
    {
        return "
            CASE
                WHEN {$slotIdColumn} LIKE '%__F%__B%__R%__ROW%'
                    THEN CONCAT(
                        'L',
                        SUBSTRING_INDEX(SUBSTRING_INDEX({$slotIdColumn}, '__B', 1), '__F', -1),
                        UPPER(SUBSTRING_INDEX(SUBSTRING_INDEX({$slotIdColumn}, '__R', 1), '__B', -1)),
                        LPAD(SUBSTRING_INDEX(SUBSTRING_INDEX({$slotIdColumn}, '__ROW', 1), '__R', -1), 2, '0'),
                        '/',
                        SUBSTRING_INDEX({$slotIdColumn}, '__ROW', -1)
                    )
                ELSE {$slotIdColumn}
            END
        ";
    }

    private function hasWorkspaceTables(): bool
    {
        return Schema::hasTable('gudang_produk_layouts')
            && Schema::hasTable('gudang_produk_layout_floors')
            && Schema::hasTable('gudang_produk_layout_blocks')
            && Schema::hasTable('gudang_produk_layout_racks')
            && Schema::hasTable('gudang_produk_slot_aliases')
            && Schema::hasTable('gudang_produk_stock_entries')
            && Schema::hasTable('gudang_produk_activity_logs');
    }

    private function ensureWorkspaceTablesReady(): void
    {
        if ($this->hasWorkspaceTables()) {
            return;
        }

        throw ValidationException::withMessages([
            'workspace' => ['Tabel Gudang Produk workspace belum siap. Jalankan migrasi backend terlebih dahulu.'],
        ]);
    }
}
