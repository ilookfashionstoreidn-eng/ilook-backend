<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SpkCuttingDistribusi;

class SpkCuttingDistribusiController extends Controller
{
    public function index()
    {
        $data = SpkCuttingDistribusi::with([
            'spkCutting.produk:id,nama_produk'
        ])->orderBy('created_at', 'desc')->get();

        return response()->json([
            'data' => $data
        ]);
    }

    public function show($id)
    {
        $distribusi = SpkCuttingDistribusi::with([
            'spkCutting.produk',
            'detail',
        ])->findOrFail($id);

        // Format data untuk preview
        $produk = $distribusi->spkCutting->produk ?? null;
        $warna = $distribusi->detail->map(function ($d) {
            return [
                'nama_warna' => $d->warna,
                'qty' => $d->jumlah_produk,
            ];
        });

        return response()->json([
            'id' => $distribusi->id,
            'kode_seri' => $distribusi->kode_seri,
            'nomor_seri' => $distribusi->kode_seri, // kode_seri bisa digunakan sebagai nomor_seri
            'nama_produk' => $produk?->nama_produk,
            'kategori_produk' => $produk?->kategori_produk,
            'gambar_produk' => $produk?->gambar_produk,
            'jumlah_produk' => $distribusi->jumlah_produk,
            'warna' => $warna,
        ]);
    }
}
