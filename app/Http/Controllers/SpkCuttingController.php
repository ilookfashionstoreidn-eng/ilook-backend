<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Produk;
use App\Models\SpkCutting;
use App\Models\SpkCuttingBagian;
use App\Models\SpkCuttingBahan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SpkCuttingController extends Controller
{
    public function index()
    {
        $data = SpkCutting::with([
            'produk:id,nama_produk',
            'bagian.bahan.bahan',
            'tukangCutting:id,nama_tukang_cutting',
        ])->get();

        return response()->json($data);
    }


    public function show($id)
    {
        $spk = SpkCutting::with('produk.markeranProduk', 'bagian.bahan.bahan')->find($id);

        if (!$spk) {
            return response()->json(['message' => 'SPK Cutting tidak ditemukan'], 404);
        }

        return response()->json(['data' => $spk]);
    }


    public function store(Request $request)
    {
        try {
            // Validasi data
            $validated = $request->validate([
                'id_spk_cutting' => 'required|string',
                'produk_id' => 'required|exists:produk,id',
                'tanggal_batas_kirim' => 'required|date',
                'harga_jasa' => 'required|numeric|min:0',
                'satuan_harga' => 'required|in:Lusin,Pcs',
                'keterangan' => 'nullable|string',
                'bagian' => 'required|array',
                'bagian.*.nama_bagian' => 'required|string',
                'bagian.*.bahan' => 'required|array',
                'bagian.*.bahan.*.bahan_id' => 'required|exists:bahan,id',
                'bagian.*.bahan.*.warna' => 'nullable|string|max:255',
                'bagian.*.bahan.*.berat' => 'nullable|numeric|min:0',
                'bagian.*.bahan.*.qty' => 'required|numeric|min:1',
                'tukang_cutting_id' => 'required|exists:tukang_cutting,id',
            ]);

            $validated['harga_per_pcs'] = $validated['satuan_harga'] === 'Lusin'
                ? $validated['harga_jasa'] / 12
                : $validated['harga_jasa'];

            $validated['status_cutting'] = 'in progress';

            DB::beginTransaction();

            $spk = SpkCutting::create($validated);

            foreach ($request->bagian as $bagianData) {
                $bagian = SpkCuttingBagian::create([
                    'spk_cutting_id' => $spk->id,
                    'nama_bagian' => $bagianData['nama_bagian'],
                ]);

                foreach ($bagianData['bahan'] as $bahanData) {
                    SpkCuttingBahan::create([
                        'spk_cutting_bagian_id' => $bagian->id,
                        'bahan_id' => $bahanData['bahan_id'],
                        'warna' => $bahanData['warna'] ?? null,
                        'berat' => $bahanData['berat'] ?? null,
                        'qty' => $bahanData['qty'],
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'SPK Cutting lengkap berhasil ditambahkan.',
                'data' => $spk->load('bagian.bahan')
            ], 201);
        } catch (ValidationException $e) {

            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyimpan data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
