<?php

namespace App\Http\Controllers;

use App\Models\SpkBahan;
use App\Models\SpkBahanWarna;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class SpkBahanController extends Controller
{
    public function index()
    {
        $data = SpkBahan::with([
                'pabrik',
                'bahan',
                'warna'
            ])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }


     public function store(Request $request)
    {
        $validated = $request->validate([
            'pabrik_id' => 'required|integer',
            'bahan_id' => 'required|integer',
            'jenis_pembayaran' => 'required|string',
            'tanggal_pembayaran' => 'required|date',

            // detail warna
            'warna' => 'required|array|min:1',
            'warna.*.warna' => 'required|string',
            'warna.*.jumlah_rol' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();

        try {
            // 1. Simpan header SPK Bahan (jumlah sementara 0)
            $spkBahan = SpkBahan::create([
                'pabrik_id' => $validated['pabrik_id'],
                'bahan_id' => $validated['bahan_id'],
                'jumlah' => 0, // akan diupdate
                'jenis_pembayaran' => $validated['jenis_pembayaran'],
                'tanggal_pembayaran' => $validated['tanggal_pembayaran'],
                'status' => 'proses'
            ]);

            $totalJumlah = 0;

            // 2. Simpan detail warna
            foreach ($validated['warna'] as $item) {
                SpkBahanWarna::create([
                    'spk_bahan_id' => $spkBahan->id,
                    'warna' => $item['warna'],
                    'jumlah_rol' => $item['jumlah_rol'],
                ]);

                $totalJumlah += $item['jumlah_rol'];
            }

            // 3. Update total jumlah rol ke header
            $spkBahan->update([
                'jumlah' => $totalJumlah
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'SPK Bahan berhasil disimpan',
                'data' => $spkBahan->load('warna')
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan SPK Bahan',                                                   
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
