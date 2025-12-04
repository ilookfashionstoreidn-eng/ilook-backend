<?php

namespace App\Http\Controllers;

use App\Models\PembelianBahan;
use App\Models\PembelianBahanWarna;
use App\Models\PembelianBahanRol;
use Illuminate\Http\Request;


class PembelianBahanController extends Controller
{

    public function index (){
        return response()->json(PembelianBahan::all());
    }
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'keterangan' => 'required|string',
                'gudang_id'  => 'required|exists:gudang,id',
                'pabrik_id'  => 'required|exists:pabrik,id',
                'tanggal_kirim' => 'required|date',
                'no_surat_jalan' => 'nullable|string',
                'foto_surat_jalan' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5000',

                'nama_bahan' => 'required|string',
                'gramasi' => 'required|integer',
                'satuan' => 'required|string',
                'lebar_kain' => 'required|integer',

                'warna' => 'required|array',
                'warna.*.nama' => 'required|string',
                'warna.*.jumlah_rol' => 'required|integer',
                'warna.*.rol' => 'required|array',
                'warna.*.rol.*' => 'required|numeric',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $e->errors(), // Menampilkan detail kolom mana yang error
            ], 422);
        }

        $data = $request->all();

        if ($request->hasFile('foto_surat_jalan')) {
            $data['foto_surat_jalan'] = $request->file('foto_surat_jalan')->store('surat_jalan', 'public');
        }

        $pembelianBahan = PembelianBahan::create($data);

        foreach ($request->warna as $warnaItem) {
            $warna = PembelianBahanWarna::create([
                'pembelian_bahan_id' => $pembelianBahan->id,
                'warna' => $warnaItem['nama'],
                'jumlah_rol' => $warnaItem['jumlah_rol'],
            ]);

            foreach ($warnaItem['rol'] as $berat) {
                PembelianBahanRol::create([
                    'pembelian_bahan_warna_id' => $warna->id,
                    'berat' => $berat,
                    'barcode' => 'BR-' . strtoupper(uniqid()),
                    'status' => 'tersedia'
                ]);
            }
        }

        return response()->json([
            'message' => 'Pembelian bahan berhasil disimpan',
            'data' => $pembelianBahan->load('warna.rol')
        ], 201);
    }

}