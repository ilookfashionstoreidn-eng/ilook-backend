<?php

namespace App\Http\Controllers;

use App\Models\StokGudangProduk;
use App\Models\ProdukSku;
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
        
        // ✅ OPTIMASI: Map data dengan lookup dari collection (tidak ada query tambahan)
        $mappedStok = $stok->map(function ($item) use ($produkSkus) {
            $skuText = $item->sku->sku ?? null;
            $skuDisplay = $skuText; // Default ke SKU string
            $produkId = null;
            $produkNama = null;
            
            // Lookup dari collection (sangat cepat, tidak ada query)
            if ($skuText && isset($produkSkus[$skuText])) {
                $produkSku = $produkSkus[$skuText];
                $produkId = $produkSku->produk_id;
                $produkNama = strtoupper($produkSku->produk->nama_produk ?? '');
                // Format: "NAMA PRODUK - WARNA UKURAN"
                $warna = strtoupper($produkSku->warna ?? '');
                $ukuran = strtoupper($produkSku->ukuran ?? '');
                $skuDisplay = trim("{$produkNama} - {$warna} {$ukuran}");
            }
            
            return [
                'sku_id' => $item->sku_id,
                'sku' => $skuText,
                'sku_display' => $skuDisplay,
                'produk_id' => $produkId,
                'produk_nama' => $produkNama,
                'qty' => $item->qty,
            ];
        });

        // Group by produk_id
        $grouped = $mappedStok->groupBy('produk_id')->map(function ($items, $produkId) {
            $firstItem = $items->first();
            $totalQty = $items->sum('qty');
            
            return [
                'produk_id' => $produkId,
                'produk_nama' => $firstItem['produk_nama'] ?? 'Produk Lainnya',
                'total_qty' => $totalQty,
                'skus' => $items->map(function ($item) {
                    return [
                        'sku_id' => $item['sku_id'],
                        'sku' => $item['sku'],
                        'sku_display' => $item['sku_display'],
                        'qty' => $item['qty'],
                    ];
                })->values()->all(),
            ];
        })->values();

        // Sort by produk_nama
        $grouped = $grouped->sortBy('produk_nama')->values();
        
        // ✅ OPTIMASI: Pagination untuk mengurangi response size
        $total = $grouped->count();
        $grouped = $grouped->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'data' => $grouped,
            'pagination' => [
                'current_page' => (int) $page,
                'per_page' => (int) $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
            ]
        ]);
    }
}