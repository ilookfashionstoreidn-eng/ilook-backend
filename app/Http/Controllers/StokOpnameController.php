<?php

namespace App\Http\Controllers;

use App\Models\GudangProdukActivityLog;
use App\Models\GudangProdukLayout;
use App\Models\GudangProdukWorkspaceStockEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class StokOpnameController extends Controller
{
    /**
     * GET /gudang-produk-workspace/opname/products
     *
     * Mengembalikan daftar produk yang memiliki SKU aktif di gudang.
     * Jika query param `all=1` dikirim, kembalikan semua produk (termasuk yang belum ada di gudang).
     */
    public function products(Request $request)
    {
        $this->ensureWorkspaceTablesReady();

        $search = trim((string) ($request->query('search', '')));
        $showAll = $request->boolean('all');

        if ($showAll) {
            // Semua produk yang memiliki SKU aktif
            $query = DB::table('produk as p')
                ->join('produk_sku as ps', 'ps.produk_id', '=', 'p.id')
                ->join('skus as s', 's.sku', '=', 'ps.sku')
                ->where('s.is_active', true)
                ->select([
                    'p.id',
                    'p.nama_produk',
                ])
                ->distinct();
        } else {
            // Hanya produk yang memiliki stok di gudang
            $query = DB::table('produk as p')
                ->join('produk_sku as ps', 'ps.produk_id', '=', 'p.id')
                ->join('skus as s', 's.sku', '=', 'ps.sku')
                ->join('gudang_produk_stock_entries as gse', 'gse.sku_id', '=', 's.id')
                ->where('s.is_active', true)
                ->where('gse.qty', '>', 0)
                ->select([
                    'p.id',
                    'p.nama_produk',
                ])
                ->distinct();
        }

        if ($search !== '') {
            $searchTerm = '%' . addcslashes($search, '\\%_') . '%';
            $query->where('p.nama_produk', 'like', $searchTerm);
        }

        $products = $query->orderBy('p.nama_produk')->get();

        return response()->json([
            'data' => $products->map(function ($product) {
                return [
                    'id'   => (int) $product->id,
                    'name' => $product->nama_produk,
                ];
            })->values()->all(),
        ]);
    }

    /**
     * GET /gudang-produk-workspace/opname/products/{produkId}/skus
     *
     * Mengembalikan semua SKU dari produk beserta stok di setiap slot gudang.
     */
    public function skus(Request $request, int $produkId)
    {
        $this->ensureWorkspaceTablesReady();

        $slotCodeExpression = $this->buildSlotCodeExpression('gse.slot_id');

        $rows = DB::table('produk_sku as ps')
            ->join('skus as s', 's.sku', '=', 'ps.sku')
            ->join('gudang_produk_stock_entries as gse', 'gse.sku_id', '=', 's.id')
            ->join('gudang_produk_layouts as layouts', 'layouts.id', '=', 'gse.layout_id')
            ->where('ps.produk_id', $produkId)
            ->where('s.is_active', true)
            ->where('gse.qty', '>', 0)
            ->select([
                'gse.id as entry_id',
                'gse.sku_id',
                's.sku as sku_code',
                'ps.warna',
                'ps.ukuran',
                'gse.slot_id',
                'layouts.id as layout_db_id',
                'layouts.uid as layout_id',
                'layouts.name as layout_name',
                'gse.qty',
                'gse.updated_at',
            ])
            ->selectRaw("{$slotCodeExpression} as slot_code")
            ->orderBy('s.sku')
            ->orderBy('layouts.name')
            ->orderBy('gse.slot_id')
            ->get();

        return response()->json([
            'data' => $rows->map(function ($row) {
                $warna  = strtoupper(trim((string) ($row->warna ?? '')));
                $ukuran = strtoupper(trim((string) ($row->ukuran ?? '')));
                $variant = trim($warna . ' ' . $ukuran);
                $label = $row->sku_code;
                if ($variant !== '') {
                    $label = $row->sku_code;
                }

                return [
                    'entryId'    => (int) $row->entry_id,
                    'skuId'      => (int) $row->sku_id,
                    'skuCode'    => $row->sku_code,
                    'warna'      => $row->warna,
                    'ukuran'     => $row->ukuran,
                    'variant'    => $variant,
                    'slotId'     => $row->slot_id,
                    'slotCode'   => $row->slot_code ?: $row->slot_id,
                    'layoutId'   => $row->layout_id,
                    'layoutName' => $row->layout_name,
                    'qtyGudang'  => (int) $row->qty,
                    'updatedAt'  => $row->updated_at,
                ];
            })->values()->all(),
        ]);
    }

    /**
     * POST /gudang-produk-workspace/opname/commit
     *
     * Mengganti qty di gudang_produk_stock_entries sesuai jumlah seri yang di-scan.
     * Juga mencatat log ke gudang_produk_activity_logs dengan catatan Stok Opname.
     *
     * Body:
     *   skuId       : integer (required)
     *   slotId      : string  (required)
     *   layoutId    : string  (required, uid layout)
     *   scannedQty  : integer (required, >= 0)
     *   scannedSeries: array  (optional, daftar nomor seri yang di-scan)
     *   notes       : string  (optional)
     */
    public function commit(Request $request)
    {
        $this->ensureWorkspaceTablesReady();

        $validated = $request->validate([
            'skuId'         => 'required|integer|exists:skus,id',
            'slotId'        => 'required|string|max:500',
            'layoutId'      => 'required|string|max:255',
            'scannedQty'    => 'required|integer|min:0',
            'scannedSeries' => 'nullable|array',
            'scannedSeries.*' => 'nullable|string|max:500',
            'notes'         => 'nullable|string|max:1000',
        ]);

        $layout = GudangProdukLayout::where('uid', $validated['layoutId'])->first();
        if (!$layout) {
            throw ValidationException::withMessages([
                'layoutId' => ['Layout tidak ditemukan.'],
            ]);
        }

        $scannedQty   = (int) $validated['scannedQty'];
        $skuId        = (int) $validated['skuId'];
        $slotId       = (string) $validated['slotId'];
        $scannedSeries = array_filter(
            array_map('trim', (array) ($validated['scannedSeries'] ?? [])),
            fn ($s) => $s !== ''
        );
        $seriesNote = count($scannedSeries) > 0
            ? ' | Seri: ' . implode(', ', $scannedSeries)
            : '';
        $customNotes = trim((string) ($validated['notes'] ?? ''));
        $notes = 'Stok Opname'
            . ($customNotes !== '' ? ': ' . $customNotes : '')
            . $seriesNote;

        $entry = null;
        $oldQty = 0;

        DB::transaction(function () use (
            $layout,
            $skuId,
            $slotId,
            $scannedQty,
            $notes,
            &$entry,
            &$oldQty
        ) {
            // Cari atau buat entry
            $entry = GudangProdukWorkspaceStockEntry::where('layout_id', $layout->id)
                ->where('slot_id', $slotId)
                ->where('sku_id', $skuId)
                ->lockForUpdate()
                ->first();

            if ($entry) {
                $oldQty = (int) $entry->qty;
                $entry->qty = $scannedQty;
                $entry->updated_by = auth()->id();

                if ($scannedQty <= 0) {
                    $entry->delete();
                    $entry = null;
                } else {
                    $entry->save();
                }
            } else {
                $oldQty = 0;
                if ($scannedQty > 0) {
                    $entry = GudangProdukWorkspaceStockEntry::create([
                        'layout_id'  => $layout->id,
                        'slot_id'    => $slotId,
                        'sku_id'     => $skuId,
                        'qty'        => $scannedQty,
                        'updated_by' => auth()->id(),
                    ]);
                }
            }

            $difference = $scannedQty - $oldQty;

            if ($difference > 0) {
                GudangProdukActivityLog::create([
                    'type'         => 'placement',
                    'sku_id'       => $skuId,
                    'from_slot_id' => null,
                    'to_slot_id'   => $slotId,
                    'qty'          => $difference,
                    'notes'        => $notes . sprintf(' | Selisih masuk: +%d pcs [Sistem: %d, Fisik: %d]', $difference, $oldQty, $scannedQty),
                    'created_by'   => auth()->id(),
                ]);
            } elseif ($difference < 0) {
                $outgoingQty = abs($difference);

                GudangProdukActivityLog::create([
                    'type'         => 'mutation',
                    'sku_id'       => $skuId,
                    'from_slot_id' => $slotId,
                    'to_slot_id'   => null,
                    'qty'          => $outgoingQty,
                    'notes'        => $notes . sprintf(' | Selisih keluar: -%d pcs [Sistem: %d, Fisik: %d]', $outgoingQty, $oldQty, $scannedQty),
                    'created_by'   => auth()->id(),
                ]);
            }
        });

        return response()->json([
            'message'    => 'Stok opname berhasil disimpan.',
            'data'       => [
                'skuId'      => $skuId,
                'slotId'     => $slotId,
                'layoutId'   => $layout->uid,
                'oldQty'     => $oldQty,
                'newQty'     => $scannedQty,
                'entryId'    => $entry?->id,
                'updatedAt'  => optional($entry?->updated_at)->toISOString(),
            ],
        ]);
    }

    public function history(Request $request)
    {
        $this->ensureWorkspaceTablesReady();

        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 50);
        $search = trim((string) $request->query('search', ''));
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $query = DB::table('gudang_produk_activity_logs as logs')
            ->join('users', 'users.id', '=', 'logs.created_by')
            ->join('skus', 'skus.id', '=', 'logs.sku_id')
            ->leftJoin('produk_sku', 'produk_sku.sku', '=', 'skus.sku')
            ->leftJoin('produk', 'produk.id', '=', 'produk_sku.produk_id')
            ->where('logs.notes', 'like', 'Stok Opname%')
            ->select([
                'logs.id',
                'logs.created_at',
                'users.name as pic',
                'logs.to_slot_id',
                'logs.from_slot_id',
                'logs.qty',
                'logs.type',
                'logs.notes',
                'logs.sku_id',
                'skus.sku as sku_code',
                'produk.nama_produk',
            ]);

        if (!empty($startDate)) {
            $query->where('logs.created_at', '>=', $startDate . ' 00:00:00');
        }
        if (!empty($endDate)) {
            $query->where('logs.created_at', '<=', $endDate . ' 23:59:59');
        }

        if ($search !== '') {
            $searchTerm = '%' . addcslashes($search, '\\%_') . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('users.name', 'like', $searchTerm)
                  ->orWhere('skus.sku', 'like', $searchTerm)
                  ->orWhere('produk.nama_produk', 'like', $searchTerm)
                  ->orWhere('logs.notes', 'like', $searchTerm);
            });
        }

        $totalRows = $query->count();
        $items = $query->orderByDesc('logs.created_at')
                       ->orderByDesc('logs.id')
                       ->forPage($page, $perPage)
                       ->get();

        $skuIds = [];
        $slotIds = [];
        foreach ($items as $item) {
            $sId = $item->to_slot_id ?: $item->from_slot_id;
            if ($sId) {
                $skuIds[] = $item->sku_id;
                $slotIds[] = $sId;
            }
        }
        $skuIds = array_unique($skuIds);
        $slotIds = array_unique($slotIds);

        $currentStocks = collect();
        if (!empty($skuIds) && !empty($slotIds)) {
            $currentStocks = DB::table('gudang_produk_workspace_stock_entries')
                ->whereIn('sku_id', $skuIds)
                ->whereIn('slot_id', $slotIds)
                ->get(['sku_id', 'slot_id', 'qty'])
                ->keyBy(function ($row) {
                    return $row->sku_id . '_' . $row->slot_id;
                });
        }

        $layoutsMap = DB::table('gudang_produk_layouts')->pluck('name', 'uid')->all();
        $aliasesMap = DB::table('gudang_produk_slot_aliases')->pluck('alias', 'slot_id')->all();

        $data = $items->map(function ($row) use ($layoutsMap, $aliasesMap, $currentStocks) {
            $isMasuk = $row->type === 'placement';
            $selisih = $isMasuk ? (int) $row->qty : -((int) $row->qty);
            $slotId = $row->to_slot_id ?: $row->from_slot_id;

            $locationLabel = $slotId;
            if (!empty($slotId)) {
                $alias = $aliasesMap[$slotId] ?? null;
                if ($alias) {
                    $locationLabel = $alias;
                } else if (preg_match('/^(.+?)__F\d+__B(.+?)__R(\d+)__ROW(\d+)$/', $slotId, $matches)) {
                    $layoutId = $matches[1];
                    $layoutName = $layoutsMap[$layoutId] ?? $layoutId;
                    $locationLabel = $layoutName . ' - ' . $matches[2] . '/R' . $matches[3] . '/ROW' . $matches[4];
                } else {
                    $layoutId = explode('__', $slotId)[0] ?? '';
                    $layoutName = $layoutsMap[$layoutId] ?? $layoutId;
                    $locationLabel = trim($layoutName . ' - ' . $slotId, ' -');
                }
            }

            $sistemQty = 0;
            $fisikQty = $selisih; // Fallback for old data where fisik/sistem wasn't recorded
            $cleanNotes = $row->notes;

            if (preg_match('/\[Sistem: (\d+), Fisik: (\d+)\]/', $row->notes, $match)) {
                $sistemQty = (int) $match[1];
                $fisikQty = (int) $match[2];
                // Remove the tags from the notes displayed to the user
                $cleanNotes = trim(str_replace($match[0], '', $row->notes));
            }

            $currentStockQty = 0;
            if (!empty($slotId)) {
                $stockKey = $row->sku_id . '_' . $slotId;
                if ($currentStocks->has($stockKey)) {
                    $currentStockQty = (int) $currentStocks->get($stockKey)->qty;
                }
            }

            return [
                'id' => (int) $row->id,
                'opname_number' => 'OPN-' . \Carbon\Carbon::parse($row->created_at)->format('Ymd') . '-' . str_pad($row->id, 4, '0', STR_PAD_LEFT),
                'tanggal' => \Carbon\Carbon::parse($row->created_at)->toISOString(),
                'pic' => $row->pic ?: 'Sistem',
                'lokasi' => $locationLabel,
                'sku' => $row->sku_code,
                'total_sku' => 1,
                'total_qty_sistem' => $sistemQty,
                'total_qty_fisik' => $fisikQty,
                'selisih' => $selisih,
                'stok_saat_ini' => $currentStockQty,
                'status' => 'Selesai',
                'notes' => $cleanNotes,
            ];
        })->values()->all();

        return response()->json([
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $totalRows,
                'last_page' => max((int) ceil($totalRows / $perPage), 1),
            ],
        ]);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

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
