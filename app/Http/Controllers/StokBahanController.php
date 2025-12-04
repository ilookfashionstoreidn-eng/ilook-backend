<?php

namespace App\Http\Controllers;

use App\Models\StokBahan;
use App\Models\PembelianBahanRol;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StokBahanController extends Controller
{
    public function index()
    {
        $items = StokBahan::with(['pembelianBahan.bahan', 'pabrik', 'gudang', 'warna'])
            ->orderByDesc('scanned_at')
            ->get()
            ->map(function ($s) {
                return [
                    'id' => $s->id,
                    'nama_bahan' => optional(optional($s->pembelianBahan)->bahan)->nama_bahan,
                    'nama_pabrik' => $s->pabrik->nama_pabrik ?? null,
                    'nama_gudang' => $s->gudang->nama_gudang ?? null,
                    'warna' => $s->warna->warna ?? null,
                    'barcode' => $s->barcode,
                    'berat' => $s->berat,
                    'hari_di_gudang' => Carbon::parse($s->scanned_at)->diffInDays(Carbon::now()),
                    'scanned_at' => $s->scanned_at,
                ];
            });

        return response()->json($items);
    }

    public function scan(Request $request)
    {
        $validated = $request->validate([
            'barcode' => 'required|string',
        ]);

        $barcode = $validated['barcode'];

        $existing = StokBahan::where('barcode', $barcode)->first();
        if ($existing) {
            return response()->json(['message' => 'Sudah tercatat di stok', 'data' => $existing], 200);
        }

        $rol = PembelianBahanRol::where('barcode', $barcode)->with('warna.pembelianBahan')->first();
        if (!$rol) {
            return response()->json(['message' => 'Barcode tidak ditemukan'], 404);
        }

        $warna = $rol->warna;
        $pb = $warna->pembelianBahan;

        $stok = StokBahan::create([
            'pembelian_bahan_id' => $pb->id,
            'pembelian_bahan_warna_id' => $warna->id,
            'pembelian_bahan_rol_id' => $rol->id,
            'gudang_id' => $pb->gudang_id,
            'pabrik_id' => $pb->pabrik_id,
            'barcode' => $barcode,
            'berat' => $rol->berat,
            'scanned_at' => Carbon::now(),
        ]);

        return response()->json(['message' => 'Stok bertambah', 'data' => $stok], 201);
    }
}
