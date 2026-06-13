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
                'produk:id,nama_produk',
                'productList:id,product'
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

            // Ambil data barcode yang sudah di-scan untuk SPK ini
            $scannedItems = StokBahanKeluar::where('spk_cutting_id', $spkCutting->id)
                ->with(['spkCutting', 'spkCuttingBahan.bahan', 'stokBahan.pembelianBahan.bahan', 'stokBahan.warna'])
                ->get();

            // Hitung progress scan per bahan
            $scanProgress = [];
            foreach ($scannedItems as $item) {
                $bahanId = $item->spk_cutting_bahan_id;
                if ($bahanId) {
                    if (!isset($scanProgress[$bahanId])) {
                        $scanProgress[$bahanId] = 0;
                    }
                    $scanProgress[$bahanId]++;
                }
            }

            return response()->json([
                'spk_cutting' => [
                    'id' => $spkCutting->id,
                    'id_spk_cutting' => $spkCutting->id_spk_cutting,
                    'nama_produk' => $spkCutting->produk->nama_produk ?? $spkCutting->productList->product ?? null,
                    'mode' => $spkCutting->mode ?? 'biasa',
                ],
                'bahan_detail' => $bahanDetail,
                'scanned_items' => $scannedItems,
                'scan_progress' => $scanProgress
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
                'spk_cutting_bahan_id' => 'nullable|exists:spk_cutting_bahan,id',
                'barcode' => 'required|string',
                'berat_keluar' => 'nullable|numeric|min:0.01',
            ]);

            $spkCuttingId = $validated['spk_cutting_id'];
            $spkCuttingBahanId = $validated['spk_cutting_bahan_id'] ?? null;
            $barcode = $validated['barcode'];
            $beratKeluar = isset($validated['berat_keluar']) ? floatval($validated['berat_keluar']) : null;

            // Cek barcode di stok_bahan
            $stokBahan = StokBahan::where('barcode', $barcode)
                ->with(['pembelianBahan.bahan', 'warna'])
                ->first();

            // Cari SPK Cutting
            $spkCutting = SpkCutting::findOrFail($spkCuttingId);
            $isPotongKecil = ($spkCutting->mode === 'potong_kecil');

            if (!$spkCuttingBahanId) {
                if (!$isPotongKecil) {
                    return response()->json([
                        'message' => 'Bahan wajib dipilih untuk SPK Cutting biasa',
                        'valid' => false
                    ], 422);
                }

                $warnaScanned = null;
                if ($stokBahan) {
                    $warnaScanned = $stokBahan->warna->warna ?? null;
                } else {
                    $rol = PembelianBahanRol::where('barcode', $barcode)
                        ->with('warna')
                        ->first();
                    if ($rol) {
                        $warnaScanned = $rol->warna->warna ?? null;
                    }
                }

                // Ambil daftar spk_cutting_bahan untuk SPK ini
                $spkBahanRows = \App\Models\SpkCuttingBahan::whereHas('bagian', function ($query) use ($spkCuttingId) {
                    $query->where('spk_cutting_id', $spkCuttingId);
                })->get();

                if ($spkBahanRows->isEmpty()) {
                    return response()->json([
                        'message' => 'Tidak ada baris bahan virtual pada SPK ini',
                        'valid' => false
                    ], 422);
                }

                // Coba cari kecocokan warna
                $matchedRow = null;
                if ($warnaScanned) {
                    foreach ($spkBahanRows as $row) {
                        if (strcasecmp(trim($row->warna), trim($warnaScanned)) === 0) {
                            $matchedRow = $row;
                            break;
                        }
                    }
                }

                // Jika tidak ketemu kecocokan warna, ambil baris pertama yang belum komplit (atau baris pertama)
                if (!$matchedRow) {
                    foreach ($spkBahanRows as $row) {
                        $scanCount = StokBahanKeluar::where('spk_cutting_id', $spkCuttingId)
                            ->where('spk_cutting_bahan_id', $row->id)
                            ->count();
                        if ($scanCount < ($row->qty ?? 0)) {
                            $matchedRow = $row;
                            break;
                        }
                    }
                }

                if (!$matchedRow) {
                    $matchedRow = $spkBahanRows->first();
                }

                $spkCuttingBahanId = $matchedRow->id;
                $spkCuttingBahan = $matchedRow;
            } else {
                // Ambil detail SPK Cutting Bahan dengan relasi
                $spkCuttingBahan = \App\Models\SpkCuttingBahan::with(['bahan', 'bagian.spkCutting'])
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
            }

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
                if (!$isPotongKecil && $spkCuttingBahan->bahan_id && $bahanIdRol != $spkCuttingBahan->bahan_id) {
                    return response()->json([
                        'message' => 'Bahan tidak sesuai dengan SPK Cutting. Diharapkan: ' . ($spkCuttingBahan->bahan->nama_bahan ?? 'Tidak diketahui'),
                        'valid' => false,
                        'expected_bahan_id' => $spkCuttingBahan->bahan_id,
                        'actual_bahan_id' => $bahanIdRol,
                    ], 422);
                }

                // Validasi warna dengan case-insensitive comparison
                if (!$isPotongKecil && $spkCuttingBahan->warna && $warnaRol && strcasecmp(trim($warnaRol), trim($spkCuttingBahan->warna)) !== 0) {
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
            if (!$isPotongKecil && $spkCuttingBahan->bahan_id && $bahanIdStok != $spkCuttingBahan->bahan_id) {
                return response()->json([
                    'message' => 'Bahan tidak sesuai dengan SPK Cutting. Diharapkan: ' . ($spkCuttingBahan->bahan->nama_bahan ?? 'Tidak diketahui'),
                    'valid' => false,
                    'expected_bahan_id' => $spkCuttingBahan->bahan_id,
                    'actual_bahan_id' => $bahanIdStok,
                ], 422);
            }

            // Validasi warna dengan case-insensitive comparison
            if (!$isPotongKecil && $spkCuttingBahan->warna && $warnaStok && strcasecmp(trim($warnaStok), trim($spkCuttingBahan->warna)) !== 0) {
                return response()->json([
                    'message' => 'Warna tidak sesuai dengan SPK Cutting. Diharapkan: ' . $spkCuttingBahan->warna . ', Ditemukan: ' . $warnaStok,
                    'valid' => false,
                    'expected_warna' => $spkCuttingBahan->warna,
                    'actual_warna' => $warnaStok,
                ], 422);
            }

            // Cek apakah barcode sudah pernah di-scan untuk SPK Cutting ini
            // Tapi jika statusnya masih "tersedia" (karena pecah stok), kita bolehkan scan lagi.
            $existingBarcode = StokBahanKeluar::where('spk_cutting_id', $spkCuttingId)
                ->where('barcode', $barcode)
                ->first();

            if ($existingBarcode && $stokBahan->status !== 'tersedia') {
                return response()->json([
                    'message' => 'Barcode sudah pernah di-scan untuk SPK Cutting ini',
                    'valid' => false,
                    'data' => $existingBarcode
                ], 422);
            }

            // Validasi QTY (ROL) - Hitung jumlah scan yang sudah ada untuk spk_cutting_bahan_id ini
            $jumlahScanSekarang = StokBahanKeluar::where('spk_cutting_id', $spkCuttingId)
                ->where('spk_cutting_bahan_id', $spkCuttingBahanId)
                ->count();

            $qtyRequired = $spkCuttingBahan->qty ?? 0;

            if ($jumlahScanSekarang >= $qtyRequired) {
                $warnaInfo = $spkCuttingBahan->warna ? " dengan warna " . $spkCuttingBahan->warna : "";
                $namaBahan = $spkCuttingBahan->bahan->nama_bahan ?? "Bahan";
                return response()->json([
                    'message' => "QTY (ROL) untuk {$namaBahan}{$warnaInfo} sudah terpenuhi. Sudah di-scan {$jumlahScanSekarang} dari {$qtyRequired} roll yang diperlukan.",
                    'valid' => false,
                    'current_count' => $jumlahScanSekarang,
                    'required_qty' => $qtyRequired,
                    'is_complete' => true
                ], 422);
            }

            // Cek status stok bahan
            if ($stokBahan->status === 'tidak tersedia') {
                return response()->json([
                    'message' => 'Bahan dengan barcode ini sudah tidak tersedia',
                    'valid' => false
                ], 422);
            }

            // Validasi berat potong (tidak boleh melebihi berat saat ini)
            if ($beratKeluar !== null && $beratKeluar > $stokBahan->berat) {
                return response()->json([
                    'message' => "Berat yang dipotong ({$beratKeluar} kg) melebihi berat stok roll ({$stokBahan->berat} kg)",
                    'valid' => false
                ], 422);
            }

            // Validasi berhasil, simpan ke stok_bahan_keluar
            DB::beginTransaction();
            try {
                $beratFinal = $beratKeluar !== null ? $beratKeluar : $stokBahan->berat;

                $stokBahanKeluar = StokBahanKeluar::create([
                    'spk_cutting_id' => $spkCuttingId,
                    'spk_cutting_bahan_id' => $spkCuttingBahanId,
                    'stok_bahan_id' => $stokBahan->id,
                    'barcode' => $barcode,
                    'berat' => $beratFinal,
                    'scanned_at' => Carbon::now(),
                ]);

                // Update status & berat stok_bahan
                if ($beratKeluar !== null && $stokBahan->berat > $beratKeluar) {
                    // Masih ada sisa stok, kurangi beratnya dan status tetap tersedia
                    $stokBahan->update([
                        'berat' => $stokBahan->berat - $beratKeluar
                    ]);
                } else {
                    // Habis atau pas, update status menjadi tidak tersedia
                    $stokBahan->update([
                        'status' => 'tidak tersedia'
                    ]);
                }

                DB::commit();

                // Hitung ulang jumlah scan setelah insert
                $jumlahScanSetelahInsert = StokBahanKeluar::where('spk_cutting_id', $spkCuttingId)
                    ->where('spk_cutting_bahan_id', $spkCuttingBahanId)
                    ->count();

                $isComplete = $jumlahScanSetelahInsert >= $qtyRequired;
                $message = $isComplete
                    ? "Barcode berhasil divalidasi dan disimpan. QTY sudah lengkap ({$jumlahScanSetelahInsert}/{$qtyRequired})"
                    : "Barcode berhasil divalidasi dan disimpan ({$jumlahScanSetelahInsert}/{$qtyRequired})";

                return response()->json([
                    'message' => $message,
                    'valid' => true,
                    'is_complete' => $isComplete,
                    'current_count' => $jumlahScanSetelahInsert,
                    'required_qty' => $qtyRequired,
                    'data' => $stokBahanKeluar->load(['spkCutting', 'spkCuttingBahan.bahan', 'stokBahan.pembelianBahan.bahan', 'stokBahan.warna'])
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
                'spkCutting.productList',
                'spkCuttingBahan.bahan',
                'spkCuttingBahan.bagian',
                'stokBahan.pembelianBahan.bahan',
                'stokBahan.warna'
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

            // Filter Tanggal (Server-side)
            if ($request->has('tanggal_mulai') && !empty($request->tanggal_mulai)) {
                $query->whereDate('scanned_at', '>=', $request->tanggal_mulai);
            }

            if ($request->has('tanggal_akhir') && !empty($request->tanggal_akhir)) {
                $query->whereDate('scanned_at', '<=', $request->tanggal_akhir);
            }

            // gunakan scanned_at (timestamp saat discan) sebagai tanggal utama
            $perPage = $request->input('per_page', 20);
            $data = $query->orderByDesc('scanned_at')->orderByDesc('created_at')->paginate($perPage);

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
