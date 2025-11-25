<?php

namespace App\Http\Controllers;


use App\Models\StokAksesoris;
use App\Models\PembelianB;
use App\Models\Aksesoris;
use Illuminate\Http\Request;

class StokAksesorisController extends Controller
{
    
    public function index()
    {
        $stok = StokAksesoris::all();
        return response()->json($stok);
    }

    public function showByPembelianB($id)
    {
        $stok = StokAksesoris::where('pembelian_aksesoris_b_id', $id)->get();
    
        return response()->json($stok);
    }
    
    public function cekBarcode($barcode)
{
    $stok = StokAksesoris::where('barcode', $barcode)->first();

    if (!$stok) {
        return response()->json([
            'status' => false,
            'message' => 'Barcode tidak ditemukan.'
        ], 404);
    }

    return response()->json([
        'status' => true,
        'aksesoris_id' => $stok->aksesoris_id
    ]);
}

}
