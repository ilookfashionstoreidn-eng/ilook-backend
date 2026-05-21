<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SpkCuttingDistribusi;

class SpkCuttingDistribusiController extends Controller
{
    public function index()
    {
        $data = SpkCuttingDistribusi::with([
            'spkCutting:id,id_spk_cutting,produk_id,product_list_id,tukang_cutting_id,tanggal_batas_kirim,status_cutting',
            'spkCutting.produk:id,nama_produk',
            'spkCutting.productList:id,product,product_group',
            'spkCutting.tukangCutting:id,nama_tukang_cutting',
            'detail.productListSku:id,product,sku_name,product_colour,product_size',
        ])->orderBy('created_at', 'desc')->get();

        return response()->json([
            'data' => $data
        ]);
    }

    public function show($id)
    {
        $distribusi = SpkCuttingDistribusi::with([
            'spkCutting.produk',
            'spkCutting.productList:id,product,product_group',
            'detail.produkSku.produk',
            'detail.productListSku:id,product,sku_name,product_colour,product_size',
        ])->findOrFail($id);

        // Format data untuk preview
        $produk = $distribusi->spkCutting->produk ?? null;
        $warna = $distribusi->detail->map(function ($d) {
            return [
                'nama_warna' => $d->warna,
                'qty' => $d->jumlah_produk,
            ];
        });

        // Ambil SKU unik dari detail distribusi
        $skus = $distribusi->detail
            ->map(function ($d) {
                if ($d->productListSku) {
                    return [
                        'id' => $d->productListSku->id,
                        'product_list_id' => $d->productListSku->id,
                        'sku' => $d->productListSku->sku_name,
                        'sku_name' => $d->productListSku->sku_name,
                        'nama_produk' => $d->productListSku->product,
                        'warna' => $d->productListSku->product_colour,
                        'ukuran' => $d->productListSku->product_size,
                        'display' => trim($d->productListSku->sku_name . ' - ' . $d->productListSku->product_colour . ' ' . $d->productListSku->product_size),
                    ];
                }

                $sku = $d->produkSku;
                if ($sku) {
                    $namaProduk = ($sku->produk->nama_produk ?? '');
                    $warna = ($sku->warna ?? '');
                    $ukuran = ($sku->ukuran ?? '');
                    $displayText = trim(strtoupper($namaProduk . ' - ' . $warna . ' ' . $ukuran));
                    return [
                        'id' => $sku->id,
                        'sku' => $sku->sku,
                        'nama_produk' => $namaProduk,
                        'warna' => $warna,
                        'ukuran' => $ukuran,
                        'display' => $displayText,
                    ];
                }
                return null;
            })
            ->filter()
            ->unique('id')
            ->values();

        return response()->json([
            'id' => $distribusi->id,
            'kode_seri' => $distribusi->kode_seri,
            'nomor_seri' => $distribusi->kode_seri, // kode_seri bisa digunakan sebagai nomor_seri
            'nama_produk' => $distribusi->spkCutting->productList->product ?? $produk?->nama_produk,
            'kategori_produk' => $produk?->kategori_produk,
            'gambar_produk' => $produk?->gambar_produk,
            'jumlah_produk' => $distribusi->jumlah_produk,
            'warna' => $warna,
            'skus' => $skus,
        ]);
    }
}
