<?php

namespace App\Http\Controllers;

use App\Models\Sku;
use App\Models\ProdukSku;
use Illuminate\Http\Request;

class SkuController extends Controller
{

 public function index(Request $request)
    {
        $produkId = $request->query('produk_id');
        
        if ($produkId) {
            // Ambil SKU berdasarkan produk_id
            $produkSkus = ProdukSku::where('produk_id', $produkId)
                ->with('produk')
                ->get();
            
            // Cari Sku berdasarkan ProdukSku.sku
            $skus = collect();
            foreach ($produkSkus as $produkSku) {
                $sku = Sku::where('sku', $produkSku->sku)
                    ->where('is_active', true)
                    ->first();
                
                if ($sku) {
                    // Format: "NAMA PRODUK - WARNA UKURAN"
                    $namaProduk = strtoupper($produkSku->produk->nama_produk ?? '');
                    $warna = strtoupper($produkSku->warna ?? '');
                    $ukuran = strtoupper($produkSku->ukuran ?? '');
                    $displayText = trim("{$namaProduk} - {$warna} {$ukuran}");
                    
                    $skus->push([
                        'id' => $sku->id,
                        'sku' => $sku->sku,
                        'display_text' => $displayText,
                        'produk_id' => $produkSku->produk_id,
                        'warna' => $produkSku->warna,
                        'ukuran' => $produkSku->ukuran,
                    ]);
                }
            }
            
            return response()->json([
                'data' => $skus->values()
            ]);
        }
        
        $query = Sku::query();
        return response()->json([
            'data' => $query->orderBy('sku')->get()
        ]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:255|unique:skus,sku',
        ]);

        $sku = Sku::create([
            'sku'       => $validated['sku'],
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'SKU berhasil ditambahkan',
            'data'    => $sku,
        ], 201);
    }
    public function update(Request $request, $id)
    {
        $sku = Sku::findOrFail($id);

        $validated = $request->validate([
            'sku'       => 'sometimes|string|max:255|unique:skus,sku,' . $sku->id,
            'is_active' => 'sometimes|boolean',
        ]);

        $sku->update($validated);

        return response()->json([
            'message' => 'SKU berhasil diperbarui',
            'data'    => $sku,
        ]);
    }
}
