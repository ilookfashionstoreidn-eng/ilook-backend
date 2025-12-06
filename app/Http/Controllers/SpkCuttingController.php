<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Produk;
use App\Models\SpkCutting;
use App\Models\SpkCuttingBagian;
use App\Models\SpkCuttingBahan;
use App\Models\TukangCutting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SpkCuttingController extends Controller
{
    /**
     * Generate nomor seri SPK berdasarkan nama tukang cutting
     * Format: [Inisial 2 huruf]-[Nomor urut]
     * Contoh: Nurul -> NR-01, NR-02, dst
     */
    private function generateSpkNumber($tukangCuttingId)
    {
        // Ambil data tukang cutting
        $tukangCutting = TukangCutting::find($tukangCuttingId);

        if (!$tukangCutting) {
            throw new \Exception('Tukang cutting tidak ditemukan');
        }

        // Ambil inisial dari nama (2 huruf pertama, uppercase)
        $nama = strtoupper(trim($tukangCutting->nama_tukang_cutting));
        $words = explode(' ', $nama);

        if (count($words) >= 2) {
            // Jika ada 2 kata atau lebih, ambil huruf pertama dari 2 kata pertama
            $inisial = substr($words[0], 0, 1) . substr($words[1], 0, 1);
        } else {
            // Jika hanya 1 kata, ambil 2 huruf pertama
            $inisial = substr($nama, 0, 2);
        }

        // Cari nomor urut terakhir untuk tukang cutting ini
        $lastSpk = SpkCutting::where('tukang_cutting_id', $tukangCuttingId)
            ->where('id_spk_cutting', 'like', $inisial . '-%')
            ->orderByRaw('CAST(SUBSTRING_INDEX(id_spk_cutting, "-", -1) AS UNSIGNED) DESC')
            ->first();

        // Tentukan nomor urut berikutnya
        if ($lastSpk) {
            // Extract nomor dari format "XX-YY"
            $parts = explode('-', $lastSpk->id_spk_cutting);
            $lastNumber = (int) end($parts);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        // Format nomor dengan leading zero (2 digit)
        $spkNumber = $inisial . '-' . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);

        return $spkNumber;
    }

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

    /**
     * Generate nomor seri SPK berdasarkan tukang cutting ID
     * Endpoint untuk frontend mendapatkan nomor seri sebelum submit
     */
    public function getGeneratedSpkNumber(Request $request)
    {
        try {
            $request->validate([
                'tukang_cutting_id' => 'required|exists:tukang_cutting,id',
            ]);

            $spkNumber = $this->generateSpkNumber($request->tukang_cutting_id);

            return response()->json([
                'id_spk_cutting' => $spkNumber
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal generate nomor seri',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function store(Request $request)
    {
        try {
            // Validasi data (id_spk_cutting tidak required karena akan di-generate)
            $validated = $request->validate([
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

            // Generate nomor seri SPK otomatis berdasarkan tukang cutting
            $validated['id_spk_cutting'] = $this->generateSpkNumber($validated['tukang_cutting_id']);

            // Validasi unik untuk nomor seri (jika masih ada duplikasi)
            $exists = SpkCutting::where('id_spk_cutting', $validated['id_spk_cutting'])->exists();
            if ($exists) {
                // Jika masih ada duplikasi, generate ulang dengan nomor yang lebih tinggi
                $tukangCutting = TukangCutting::find($validated['tukang_cutting_id']);
                $nama = strtoupper(trim($tukangCutting->nama_tukang_cutting));
                $words = explode(' ', $nama);
                $inisial = count($words) >= 2
                    ? substr($words[0], 0, 1) . substr($words[1], 0, 1)
                    : substr($nama, 0, 2);

                $lastSpk = SpkCutting::where('tukang_cutting_id', $validated['tukang_cutting_id'])
                    ->where('id_spk_cutting', 'like', $inisial . '-%')
                    ->orderByRaw('CAST(SUBSTRING_INDEX(id_spk_cutting, "-", -1) AS UNSIGNED) DESC')
                    ->first();

                $nextNumber = $lastSpk ? ((int) explode('-', $lastSpk->id_spk_cutting)[1]) + 1 : 1;
                $validated['id_spk_cutting'] = $inisial . '-' . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);
            }

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
