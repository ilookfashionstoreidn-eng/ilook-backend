<?php

namespace App\Http\Controllers;

use App\Models\StokGudangProduk;
use App\Models\ProdukSku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StokGudangProdukController extends Controller
{
    public function index()
    {
        $stok = StokGudangProduk::with('sku')
            ->orderBy('sku_id')
            ->get()
            ->map(function ($item) {
                $skuText = $item->sku->sku ?? null;
                $skuDisplay = $skuText; // Default ke SKU string
                $produkId = null;
                $produkNama = null;
                
                // Cari ProdukSku berdasarkan SKU string untuk mendapatkan format display
                if ($skuText) {
                    $produkSku = ProdukSku::where('sku', $skuText)
                        ->with('produk')
                        ->first();
                    
                    if ($produkSku) {
                        $produkId = $produkSku->produk_id;
                        $produkNama = strtoupper($produkSku->produk->nama_produk ?? '');
                        // Format: "NAMA PRODUK - WARNA UKURAN"
                        $warna = strtoupper($produkSku->warna ?? '');
                        $ukuran = strtoupper($produkSku->ukuran ?? '');
                        $skuDisplay = trim("{$produkNama} - {$warna} {$ukuran}");
                    }
                }
                
                return [
                    'sku_id'   => $item->sku_id,
                    'sku' => $skuText,
                    'sku_display' => $skuDisplay,
                    'produk_id' => $produkId,
                    'produk_nama' => $produkNama,
                    'qty'      => $item->qty,
                ];
            });

        // Group by produk_id
        $grouped = $stok->groupBy('produk_id')->map(function ($items, $produkId) {
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

        return response()->json([
            'data' => $grouped
        ]);
    }
}