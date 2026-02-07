<?php

namespace App\Http\Controllers;

use App\Models\StokGudangProduk;
use App\Models\ProdukSku;
use App\Models\GudangProdukDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StokGudangProdukController extends Controller
{
    public function index(Request $request)
    {
        // ✅ OPTIMASI: Support pagination untuk mengurangi response size
        $perPage = $request->get('per_page', 100); // Default 100 produk per page
        $page = $request->get('page', 1);
        
        // ✅ OPTIMASI: Ambil semua stok sekaligus dengan eager loading
        $stok = StokGudangProduk::with('sku')
            ->orderBy('sku_id')
            ->get();
        
        // ✅ OPTIMASI: Ambil semua ProdukSku sekaligus (1 query saja, bukan N query)
        $skuStrings = $stok->pluck('sku.sku')->filter()->unique()->values();
        $skuIds = $stok->pluck('sku_id')->unique()->values();
        
        // Jika tidak ada SKU, return empty
        if ($skuStrings->isEmpty()) {
            return response()->json([
                'data' => []
            ]);
        }
        
        // Ambil semua ProdukSku dengan produk dalam 1 query
        $produkSkus = ProdukSku::whereIn('sku', $skuStrings)
            ->with('produk')
            ->get()
            ->keyBy('sku'); // Index by SKU untuk lookup cepat O(1)

        // ✅ AMBIL DATA RAK DAN FOTO DARI GUDANG_PRODUK_DETAIL
        // Mengambil foto terakhir dan semua rak yang terkait dengan SKU tersebut
        $detailInfo = GudangProdukDetail::whereIn('sku_id', $skuIds)
            ->whereNotNull('sku_rak')
            ->orWhereNotNull('foto')
            ->orderBy('id', 'desc') // Ambil yang terbaru
            ->get()
            ->groupBy('sku_id');
        
        // ✅ OPTIMASI: Map data dengan lookup dari collection (tidak ada query tambahan)
        $mappedStok = $stok->map(function ($item) use ($produkSkus, $detailInfo) {
            $skuText = $item->sku->sku ?? null;
            $skuDisplay = $skuText; // Default ke SKU string
            $produkId = null;
            $produkNama = null;
            $gambarProduk = null;
            
            // Lookup data produk
            if ($skuText && isset($produkSkus[$skuText])) {
                $produkSku = $produkSkus[$skuText];
                $produkId = $produkSku->produk_id;
                $produkNama = strtoupper($produkSku->produk->nama_produk ?? '');
                $gambarProduk = $produkSku->produk->gambar_produk ?? null; // Fallback gambar produk
                
                // Format: "NAMA PRODUK - WARNA UKURAN"
                $warna = strtoupper($produkSku->warna ?? '');
                $ukuran = strtoupper($produkSku->ukuran ?? '');
                $skuDisplay = trim("{$produkNama} - {$warna} {$ukuran}");
            }

            // Lookup rak dan foto khusus SKU
            $skuRaks = [];
            $skuFoto = null;
            
            if (isset($detailInfo[$item->sku_id])) {
                $details = $detailInfo[$item->sku_id];
                
                // Ambil unique raks dengan total qty per rak
                $skuRaks = $details->whereNotNull('sku_rak')
                    ->groupBy('sku_rak')
                    ->map(function ($rows) {
                        return [
                            'rak' => $rows->first()->sku_rak,
                            'qty' => $rows->sum('qty_acuan') // Sum qty_acuan per rak
                        ];
                    })
                    ->values()
                    ->all();
                
                // Ambil foto terbaru yang tidak null
                $lastFoto = $details->whereNotNull('foto')->first();
                if ($lastFoto) {
                    $skuFoto = $lastFoto->foto;
                }
            }
            
            return [
                'sku_id' => $item->sku_id,
                'sku' => $skuText,
                'sku_display' => $skuDisplay,
                'produk_id' => $produkId,
                'produk_nama' => $produkNama,
                'qty' => $item->qty,
                'raks' => $skuRaks,
                'foto' => $skuFoto,
                'gambar_produk' => $gambarProduk, // Fallback
            ];
        });

        // Group by produk_id
        $grouped = $mappedStok->groupBy('produk_id')->map(function ($items, $produkId) {
            $firstItem = $items->first();
            $totalQty = $items->sum('qty');
            
            // Kumpulkan semua rak unik dalam produk ini dan jumlahkan qty-nya
            $allRaksMap = [];
            foreach ($items as $item) {
                if (!empty($item['raks'])) {
                    foreach ($item['raks'] as $r) {
                        $rakName = $r['rak'];
                        if (!isset($allRaksMap[$rakName])) {
                            $allRaksMap[$rakName] = 0;
                        }
                        $allRaksMap[$rakName] += $r['qty'];
                    }
                }
            }
            
            // Convert to array of objects
            $allRaks = [];
            foreach ($allRaksMap as $rak => $qty) {
                $allRaks[] = ['rak' => $rak, 'qty' => $qty];
            }
            
            return [
                'produk_id' => $produkId,
                'produk_nama' => $firstItem['produk_nama'] ?? 'Produk Lainnya',
                'total_qty' => $totalQty,
                'all_raks' => $allRaks, // Semua rak untuk produk ini dengan total qty
                'skus' => $items->map(function ($item) {
                    return [
                        'sku_id' => $item['sku_id'],
                        'sku' => $item['sku'],
                        'sku_display' => $item['sku_display'],
                        'qty' => $item['qty'],
                        'raks' => $item['raks'],
                        'foto' => $item['foto'],
                        'gambar_produk' => $item['gambar_produk'],
                    ];
                })->values()->all(),
            ];
        })->values();

        // ✅ HITUNG SUMMARY GLOBAL (Sebelum Filter)
        $globalTotalQty = $grouped->sum('total_qty');
        // Ambil daftar nama rak unik untuk filter dropdown
        $globalAllRaks = $grouped->pluck('all_raks')->flatten(1)->pluck('rak')->unique()->values()->all();
        $globalTotalRaks = count($globalAllRaks);

        // ✅ FILTERING
        // Filter by Rak
        if ($request->has('rak') && $request->rak != '') {
            $rakFilter = $request->rak;
            $grouped = $grouped->filter(function ($item) use ($rakFilter) {
                // Cek apakah ada rak yang namanya sesuai
                return collect($item['all_raks'])->contains('rak', $rakFilter);
            });
        }

        // Filter by Min Qty
        if ($request->has('min_qty') && $request->min_qty != '') {
            $minQty = (int) $request->min_qty;
            $grouped = $grouped->filter(function ($item) use ($minQty) {
                return $item['total_qty'] >= $minQty;
            });
        }

        // Filter by Max Qty
        if ($request->has('max_qty') && $request->max_qty != '') {
            $maxQty = (int) $request->max_qty;
            $grouped = $grouped->filter(function ($item) use ($maxQty) {
                return $item['total_qty'] <= $maxQty;
            });
        }

        // ✅ SORTING
        $sortBy = $request->get('sort_by', 'produk_nama'); // Default sort by produk_nama
        $sortDir = $request->get('sort_dir', 'asc'); // Default asc

        if ($sortBy == 'total_qty') {
            $grouped = $sortDir == 'desc' 
                ? $grouped->sortByDesc('total_qty') 
                : $grouped->sortBy('total_qty');
        } else {
            // Default produk_nama
            $grouped = $sortDir == 'desc' 
                ? $grouped->sortByDesc('produk_nama') 
                : $grouped->sortBy('produk_nama');
        }
        
        $grouped = $grouped->values();
        
        // ✅ OPTIMASI: Pagination untuk mengurangi response size
        $total = $grouped->count();
        $sliced = $grouped->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'data' => $sliced,
            'summary' => [
                'total_products' => $grouped->count(), // Total setelah filter
                'total_qty' => $globalTotalQty, // Total qty global (sebelum filter biasanya, tapi ini konteks gudang mungkin mau yang difilter? Kita pakai global biar user tahu kapasitas total)
                'filtered_qty' => $grouped->sum('total_qty'), // Qty hasil filter
                'total_raks_terisi' => $globalTotalRaks,
                'all_raks_list' => $globalAllRaks, // Untuk dropdown filter
            ],
            'pagination' => [
                'current_page' => (int) $page,
                'per_page' => (int) $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
            ]
        ]);
    }
}