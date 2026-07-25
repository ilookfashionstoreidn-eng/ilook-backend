<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Gudang;
use App\Models\PembelianBahan;
use App\Models\StokBahan;

class GudangController extends Controller
{
     public function index()
    {
        $gudang = Gudang::all();
        return response()->json($gudang);
    }
     public function store(Request $request)
    {
        $request->validate([
            'nama_gudang' => 'required|string|max:255',
            'alamat' => 'nullable|string',
            'pic' => 'nullable|string|max:255',
        ]);

        $gudang = Gudang::create($request->all());
        return response()->json($gudang, 201);
    }
     public function show($id)
    {
        $gudang = Gudang::findOrFail($id);
        return response()->json($gudang);
    }

    public function update(Request $request, $id)
    {
        $gudang = Gudang::findOrFail($id);

        $request->validate([
            'nama_gudang' => 'required|string|max:255',
            'alamat' => 'nullable|string',
            'pic' => 'nullable|string|max:255',
        ]);

        $gudang->update($request->all());
        return response()->json($gudang);
    }

    public function destroy($id)
    {
        $gudang = Gudang::findOrFail($id);

        // pembelian_bahan dan stok_bahan punya FK ke gudang dengan onDelete('cascade'),
        // jadi menghapus gudang yang masih punya data ini akan diam-diam ikut menghapus
        // seluruh riwayat pembelian & stok fisik yang terkait. Cek dulu supaya tidak
        // ada penghapusan massal yang tidak disengaja.
        $hasPembelian = PembelianBahan::where('gudang_id', $id)->exists();
        $hasStok = StokBahan::where('gudang_id', $id)->exists();

        if ($hasPembelian || $hasStok) {
            return response()->json([
                'message' => 'Gudang ini masih memiliki data pembelian bahan dan/atau stok bahan. Pindahkan atau hapus data tersebut terlebih dahulu sebelum menghapus gudang ini.',
            ], 422);
        }

        $gudang->delete();

        return response()->json(['message' => 'Data gudang berhasil dihapus']);
    }
}
