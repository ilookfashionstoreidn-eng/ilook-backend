<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MarkeranProduk;
use App\Models\Produk;

class MarkeranProdukController extends Controller
{
  public function index()
{
    $data = MarkeranProduk::with('produk')->get()->groupBy('produk_id')->map(function ($group) {
        $produk = $group->first()->produk;

        return [
            'produk_id' => $produk->id,
            'nama_produk' => $produk->nama_produk,
            'komponen' => $group->map(function ($item) {
                return [
                    'id' => $item->id,
                    'nama_komponen' => $item->nama_komponen,
                    'total_panjang' => $item->total_panjang,
                    'jumlah_hasil' => $item->jumlah_hasil,
                    'berat_per_pcs' => $item->berat_per_pcs,
                ];
            })->values()
        ];
    })->values();

    return response()->json($data);
}

  public function store(Request $request)
    {
        $validatedHeader = $request->validate([
            'produk_id' => 'required|exists:produk,id',
            'komponen' => 'required|array|min:1',
            'komponen.*.nama_komponen' => 'required|string|max:255',
            'komponen.*.total_panjang' => 'required|numeric|min:0',
            'komponen.*.jumlah_hasil' => 'required|integer|min:1',
        ]);

        foreach ($validatedHeader['komponen'] as $item) {
            $berat_per_pcs = $item['total_panjang'] / $item['jumlah_hasil'];

            MarkeranProduk::create([
                'produk_id' => $validatedHeader['produk_id'],
                'nama_komponen' => $item['nama_komponen'],
                'total_panjang' => $item['total_panjang'],
                'jumlah_hasil' => $item['jumlah_hasil'],
                'berat_per_pcs' => $berat_per_pcs,
            ]);
        }

        return response()->json(['message' => 'Semua markeran berhasil disimpan'], 201);
    }

}
