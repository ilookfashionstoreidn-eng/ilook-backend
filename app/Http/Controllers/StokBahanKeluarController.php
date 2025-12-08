<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StokBahanKeluar;
use App\Models\SpkCutting;
use App\Models\StokBahan;
use App\Models\PembelianBahanRol;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class StokBahanKeluarController extends Controller
{
    /**
     * Get detail SPK Cutting dengan bahan-bahannya
     * Menerima: id (integer), id_spk_cutting (string), atau barcode (string)
     */
    public function getSpkCuttingDetail($spkCuttingId)
    {
        try {
            // Cari berdasarkan id (integer), id_spk_cutting (string), atau barcode
            $spkCutting = SpkCutting::with([
                'bagian.bahan.bahan',
                'produk:id,nama_produk'
            ]);

            // Jika input adalah angka, cari berdasarkan id
            if (is_numeric($spkCuttingId)) {
                $spkCutting = $spkCutting->where('id', $spkCuttingId);
            }
            // Jika input dimulai dengan "SPKC-", cari berdasarkan barcode
            else if (strpos($spkCuttingId, 'SPKC-') === 0) {
                $spkCutting = $spkCutting->where('barcode', $spkCuttingId);
            }
            // Jika tidak, cari berdasarkan id_spk_cutting
            else {
                $spkCutting = $spkCutting->where('id_spk_cutting', $spkCuttingId);
            }

            $spkCutting = $spkCutting->first();

            if (!$spkCutting) {
                return response()->json([
                    'message' => 'SPK Cutting tidak ditemukan'
                ], 404);
            }

            // Format data bahan per bagian
            $bahanDetail = [];
            foreach ($spkCutting->bagian as $bagian) {
                foreach ($bagian->bahan as $bahan) {
                    $bahanDetail[] = [
                        'spk_cutting_bahan_id' => $bahan->id,
                        'spk_cutting_bagian_id' => $bagian->id,
                        'nama_bagian' => $bagian->nama_bagian,
                        'bahan_id' => $bahan->bahan_id,
                        'nama_bahan' => $bahan->bahan->nama_bahan ?? null,
                        'warna' => $bahan->warna,
                        'qty' => $bahan->qty,
                        'berat' => $bahan->berat,
                    ];
                }
            }

            return response()->json([
                'spk_cutting' => [
                    'id' => $spkCutting->id,
                    'id_spk_cutting' => $spkCutting->id_spk_cutting,
                    'nama_produk' => $spkCutting->produk->nama_produk ?? null,
                ],
                'bahan_detail' => $bahanDetail
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getSpkCuttingDetail: ' . $e->getMessage());
            return response()->json([
                'message' => 'Terjadi kesalahan saat mengambil detail SPK Cutting',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validasi dan scan barcode untuk stok bahan keluar
     */
    public function scanBarcode(Request $request)
    {
        try {
            $validated = $request->validate([
                'spk_cutting_id' => 'required|exists:spk_cutting,id',
                'spk_cutting_bahan_id' => 'required|exists:spk_cutting_bahan,id',
                'barcode' => 'required|string',
            ]);

            $spkCuttingId = $validated['spk_cutting_id'];
            $spkCuttingBahanId = $validated['spk_cutting_bahan_id'];
            $barcode = $validated['barcode'];

            // Ambil detail SPK Cutting Bahan dengan relasi
            $spkCuttingBahan = \App\Models\SpkCuttingBahan::with(['bahan', 'bagian'])
                ->where('id', $spkCuttingBahanId)
                ->whereHas('bagian', function ($query) use ($spkCuttingId) {
                    $query->where('spk_cutting_id', $spkCuttingId);
                })
                ->first();

            if (!$spkCuttingBahan) {
                return response()->json([
                    'message' => 'Bahan tidak ditemukan di SPK Cutting ini',
                    'valid' => false
                ], 404);
            }

            // Cek barcode di stok_bahan
            $stokBahan = StokBahan::where('barcode', $barcode)
                ->with(['pembelianBahan.bahan', 'warna'])
                ->first();

            if (!$stokBahan) {
                // Cek di pembelian_bahan_rol jika belum masuk stok
                $rol = PembelianBahanRol::where('barcode', $barcode)
                    ->with('warna.pembelianBahan.bahan')
                    ->first();

                if (!$rol) {
                    return response()->json([
                        'message' => 'Barcode tidak ditemukan',
                        'valid' => false
                    ], 404);
                }

                // Validasi bahan_id dan warna dari rol
                $pembelianBahan = $rol->warna->pembelianBahan;
                $bahanIdRol = $pembelianBahan->bahan_id;
                $warnaRol = $rol->warna->warna ?? null;

                // Validasi dengan SPK Cutting Bahan
                if ($bahanIdRol != $spkCuttingBahan->bahan_id) {
                    return response()->json([
                        'message' => 'Bahan tidak sesuai dengan SPK Cutting. Diharapkan: ' . ($spkCuttingBahan->bahan->nama_bahan ?? 'Tidak diketahui'),
                        'valid' => false,
                        'expected_bahan_id' => $spkCuttingBahan->bahan_id,
                        'actual_bahan_id' => $bahanIdRol,
                    ], 422);
                }

                // Validasi warna dengan case-insensitive comparison
                if ($spkCuttingBahan->warna && $warnaRol && strcasecmp(trim($warnaRol), trim($spkCuttingBahan->warna)) !== 0) {
                    return response()->json([
                        'message' => 'Warna tidak sesuai dengan SPK Cutting. Diharapkan: ' . $spkCuttingBahan->warna . ', Ditemukan: ' . $warnaRol,
                        'valid' => false,
                        'expected_warna' => $spkCuttingBahan->warna,
                        'actual_warna' => $warnaRol,
                    ], 422);
                }

                // Barcode valid, simpan ke stok_bahan_keluar
                // Tapi karena belum ada di stok_bahan, kita perlu buat dulu atau skip
                return response()->json([
                    'message' => 'Barcode ditemukan di pembelian bahan, tapi belum masuk stok. Silakan scan masuk stok terlebih dahulu.',
                    'valid' => false,
                    'data' => [
                        'barcode' => $barcode,
                        'bahan_id' => $bahanIdRol,
                        'warna' => $warnaRol,
                    ]
                ], 422);
            }

            // Validasi bahan_id dan warna dari stok_bahan
            $pembelianBahan = $stokBahan->pembelianBahan;
            $bahanIdStok = $pembelianBahan->bahan_id ?? null;
            $warnaStok = $stokBahan->warna->warna ?? null;

            // Validasi dengan SPK Cutting Bahan
            if ($bahanIdStok != $spkCuttingBahan->bahan_id) {
                return response()->json([
                    'message' => 'Bahan tidak sesuai dengan SPK Cutting. Diharapkan: ' . ($spkCuttingBahan->bahan->nama_bahan ?? 'Tidak diketahui'),
                    'valid' => false,
                    'expected_bahan_id' => $spkCuttingBahan->bahan_id,
                    'actual_bahan_id' => $bahanIdStok,
                ], 422);
            }

            // Validasi warna dengan case-insensitive comparison
            if ($spkCuttingBahan->warna && $warnaStok && strcasecmp(trim($warnaStok), trim($spkCuttingBahan->warna)) !== 0) {
                return response()->json([
                    'message' => 'Warna tidak sesuai dengan SPK Cutting. Diharapkan: ' . $spkCuttingBahan->warna . ', Ditemukan: ' . $warnaStok,
                    'valid' => false,
                    'expected_warna' => $spkCuttingBahan->warna,
                    'actual_warna' => $warnaStok,
                ], 422);
            }

            // Cek apakah sudah pernah di-scan untuk SPK Cutting ini
            $existing = StokBahanKeluar::where('spk_cutting_id', $spkCuttingId)
                ->where('barcode', $barcode)
                ->first();

            if ($existing) {
                return response()->json([
                    'message' => 'Barcode sudah pernah di-scan untuk SPK Cutting ini',
                    'valid' => false,
                    'data' => $existing
                ], 422);
            }

            // Cek status stok bahan
            if ($stokBahan->status === 'tidak tersedia') {
                return response()->json([
                    'message' => 'Bahan dengan barcode ini sudah tidak tersedia',
                    'valid' => false
                ], 422);
            }

            // Validasi berhasil, simpan ke stok_bahan_keluar
            DB::beginTransaction();
            try {
                $stokBahanKeluar = StokBahanKeluar::create([
                    'spk_cutting_id' => $spkCuttingId,
                    'spk_cutting_bahan_id' => $spkCuttingBahanId,
                    'stok_bahan_id' => $stokBahan->id,
                    'barcode' => $barcode,
                    'berat' => $stokBahan->berat,
                    'scanned_at' => Carbon::now(),
                ]);

                // Update status stok_bahan menjadi tidak tersedia
                $stokBahan->update(['status' => 'tidak tersedia']);

                DB::commit();

                return response()->json([
                    'message' => 'Barcode berhasil divalidasi dan disimpan',
                    'valid' => true,
                    'data' => $stokBahanKeluar->load(['spkCutting', 'spkCuttingBahan.bahan', 'stokBahan'])
                ], 201);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in scanBarcode: ' . $e->getMessage());
            return response()->json([
                'message' => 'Terjadi kesalahan saat memvalidasi barcode',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get list stok bahan keluar
     */
    public function index(Request $request)
    {
        try {
            $query = StokBahanKeluar::with([
                'spkCutting.produk',
                'spkCuttingBahan.bahan',
                'spkCuttingBahan.bagian',
                'stokBahan.pembelianBahan.bahan'
            ]);

            if ($request->has('spk_cutting_id') && $request->spk_cutting_id) {
                $spkCuttingId = $request->spk_cutting_id;
                // Cari berdasarkan id (integer) atau id_spk_cutting (string)
                if (is_numeric($spkCuttingId)) {
                    $query->where('spk_cutting_id', $spkCuttingId);
                } else {
                    // Cari berdasarkan id_spk_cutting
                    $query->whereHas('spkCutting', function ($q) use ($spkCuttingId) {
                        $q->where('id_spk_cutting', $spkCuttingId);
                    });
                }
            }

            // gunakan scanned_at (timestamp saat discan) sebagai tanggal utama
            $data = $query->orderByDesc('scanned_at')->orderByDesc('created_at')->paginate(20);

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error in index: ' . $e->getMessage());
            return response()->json([
                'message' => 'Terjadi kesalahan saat mengambil data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
