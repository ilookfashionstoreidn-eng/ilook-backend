<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AksesorisStok;

class ScanBarcodeController extends Controller
{
    // Mengecek apakah barcode sudah pernah dipakai
    public function scan(Request $request)
    {
        $barcode = $request->barcode;

        // Cari barcode di tabel stok
        $stok = AksesorisStok::where('barcode', $barcode)->first();

        if (!$stok) {
            return response()->json([
                'message' => 'Barcode tidak ditemukan di stok'
            ], 404);
        }

        // Kalau statusnya sudah terpakai, balikan 409
        if ($stok->status === 'terpakai') {
            return response()->json([
                'message' => 'Barcode sudah pernah dipakai'
            ], 409);
        }

        return response()->json([
            'message' => 'Barcode tersedia'
        ], 200);
    }

    // Mengecek barcode untuk validasi aksesoris pesanan
    public function cekBarcode($barcode)
    {
        $stok = AksesorisStok::where('barcode', $barcode)->first();

        if (!$stok) {
            return response()->json([
                'message' => 'Barcode tidak ditemukan'
            ], 404);
        }

        // balikan data aksesoris_id untuk dicocokkan di frontend
        return response()->json([
            'aksesoris_id' => $stok->aksesoris_id,
            'status'       => $stok->status
        ]);
    }
}