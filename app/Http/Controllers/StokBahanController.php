<?php

namespace App\Http\Controllers;

use App\Models\StokBahan;
use App\Models\PembelianBahanRol;
use App\Models\PembelianBahanWarna;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class StokBahanController extends Controller
{
    public function index()
    {
        $items = StokBahan::with(['pembelianBahan.bahan', 'pabrik', 'gudang', 'warna'])
            ->where(function ($query) {
                $query->where('status', 'tersedia')
                    ->orWhereNull('status');
            })
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
                    'status' => $s->status ?? 'tersedia',
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
            'status' => 'tersedia',
        ]);

        return response()->json(['message' => 'Stok bertambah', 'data' => $stok], 201);
    }

    public function getByBarcode($barcode)
    {
        try {
            $stokBahan = StokBahan::where('barcode', $barcode)
                ->with(['pembelianBahan.bahan', 'pabrik', 'gudang', 'warna'])
                ->first();

            if (!$stokBahan) {
                return response()->json([
                    'message' => 'Barcode tidak ditemukan di stok bahan'
                ], 404);
            }

            $data = [
                'id' => $stokBahan->id,
                'barcode' => $stokBahan->barcode,
                'nama_bahan' => optional(optional($stokBahan->pembelianBahan)->bahan)->nama_bahan,
                'warna' => $stokBahan->warna->warna ?? null,
                'berat' => $stokBahan->berat,
                'status' => $stokBahan->status ?? 'tersedia',
                'nama_pabrik' => $stokBahan->pabrik->nama_pabrik ?? null,
                'nama_gudang' => $stokBahan->gudang->nama_gudang ?? null,
                'gudang' => $stokBahan->gudang ? ['nama_gudang' => $stokBahan->gudang->nama_gudang] : null,
                'gudang_id' => $stokBahan->gudang_id,
                'hari_di_gudang' => Carbon::parse($stokBahan->scanned_at)->diffInDays(Carbon::now()),
                'scanned_at' => $stokBahan->scanned_at,
            ];

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error in getByBarcode: ' . $e->getMessage());
            return response()->json([
                'message' => 'Terjadi kesalahan saat mencari barcode',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function stokPerBahan(Request $request)
    {
        try {
            // Ambil parameter filter status dari request (utuh, sisa, atau null untuk semua)
            $statusFilter = $request->get('status'); // 'utuh', 'sisa', atau null

            $stokBahan = StokBahan::with(['pembelianBahan.bahan', 'pabrik', 'gudang', 'warna'])
                ->where(function ($query) {
                    $query->where('status', 'tersedia')
                        ->orWhereNull('status');
                });

            // Filter berdasarkan keterangan jika statusFilter ada
            if ($statusFilter && in_array(strtolower($statusFilter), ['utuh', 'sisa'])) {
                $stokBahan->whereHas('pembelianBahan', function ($query) use ($statusFilter) {
                    $query->where('keterangan', ucfirst(strtolower($statusFilter)));
                });
            }

            $stokBahan = $stokBahan->get();

            // Jika tidak ada data, return array kosong
            if ($stokBahan->isEmpty()) {
                return response()->json([]);
            }

            $stokPerBahan = $stokBahan
                ->groupBy(function ($item) {
                    return optional(optional($item->pembelianBahan)->bahan)->nama_bahan ?? 'Tidak Diketahui';
                })
                ->map(function ($items, $namaBahan) use ($statusFilter) {
                    // Filter detail berdasarkan statusFilter jika ada
                    $filteredItems = $items;
                    if ($statusFilter && in_array(strtolower($statusFilter), ['utuh', 'sisa'])) {
                        $filteredItems = $items->filter(function ($item) use ($statusFilter) {
                            $keterangan = $item->pembelianBahan->keterangan ?? null;
                            return strtolower($keterangan) === strtolower($statusFilter);
                        });
                    }

                    // Jika setelah filter tidak ada item, skip bahan ini
                    if ($filteredItems->isEmpty()) {
                        return null;
                    }

                    // Hitung total hanya dari filtered items
                    $totalBerat = $filteredItems->sum('berat');
                    $totalRol = $filteredItems->count();

                    // Ambil warna hanya dari filtered items
                    $warna = $filteredItems->pluck('warna.warna')->filter()->unique()->values();
                    $gudang = $filteredItems->pluck('gudang.nama_gudang')->filter()->unique()->values();
                    $pabrik = $filteredItems->pluck('pabrik.nama_pabrik')->filter()->unique()->values();

                    // Ambil status (keterangan), SKU, dan harga dari pembelian_bahan
                    $keterangan = $filteredItems->pluck('pembelianBahan.keterangan')->filter()->unique()->values();
                    $sku = $filteredItems->pluck('pembelianBahan.sku')->filter()->unique()->values();

                    // Hitung harga per roll dan total harga
                    // Harga hanya dari tabel master bahan (bahan.harga), bukan dari pembelian_bahan
                    $hargaPerRoll = 0;
                    $totalHarga = 0;

                    // Ambil harga dari master bahan (bahan.harga)
                    $hargaBahanMaster = optional(optional($filteredItems->first()->pembelianBahan)->bahan)->harga ?? 0;

                    if ($hargaBahanMaster && $totalBerat > 0) {
                        // Total harga = harga bahan master × total berat
                        $totalHarga = $hargaBahanMaster * $totalBerat;

                        // Harga per roll (rata-rata) = total harga / jumlah roll
                        if ($totalRol > 0) {
                            $hargaPerRoll = $totalHarga / $totalRol;
                        }
                    }

                    return [
                        'nama_bahan' => $namaBahan,
                        'total_berat' => round($totalBerat, 2),
                        'total_rol' => $totalRol,
                        'harga_per_roll' => round($hargaPerRoll, 2),
                        'total_harga' => round($totalHarga, 2),
                        'warna' => $warna,
                        'gudang' => $gudang,
                        'pabrik' => $pabrik,
                        'status' => $keterangan->first() ?? null, // Ambil status pertama (Utuh atau Sisa)
                        'sku' => $sku->first() ?? null, // Ambil SKU pertama
                        'detail' => $filteredItems->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'barcode' => $item->barcode,
                                'berat' => $item->berat,
                                'warna' => $item->warna->warna ?? null,
                                'nama_gudang' => $item->gudang->nama_gudang ?? null,
                                'nama_pabrik' => $item->pabrik->nama_pabrik ?? null,
                                'keterangan' => $item->pembelianBahan->keterangan ?? null,
                                'sku' => $item->pembelianBahan->sku ?? null,
                                'harga' => $item->pembelianBahan->harga ?? null,
                                'scanned_at' => $item->scanned_at,
                            ];
                        })->values(),
                    ];
                })
                ->filter() // Hapus null values (bahan yang tidak memiliki item setelah filter)
                ->values()
                ->sortBy('nama_bahan')
                ->values();

            return response()->json($stokPerBahan);
        } catch (\Exception $e) {
            Log::error('Error in stokPerBahan: ' . $e->getMessage());
            return response()->json([
                'error' => 'Terjadi kesalahan saat mengambil data stok per bahan',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get dashboard statistics untuk stok bahan
     * Mengembalikan statistik lengkap: total bahan, total roll, total berat, total harga, breakdown per status
     */
    public function getDashboardStats(Request $request)
    {
        try {
            // Ambil parameter filter status dari request (utuh, sisa, atau null untuk semua)
            $statusFilter = $request->get('status'); // 'utuh', 'sisa', atau null

            $stokBahan = StokBahan::with(['pembelianBahan.bahan', 'pabrik', 'gudang', 'warna'])
                ->where(function ($query) {
                    $query->where('status', 'tersedia')
                        ->orWhereNull('status');
                });

            // Filter berdasarkan keterangan jika statusFilter ada
            if ($statusFilter && in_array(strtolower($statusFilter), ['utuh', 'sisa'])) {
                $stokBahan->whereHas('pembelianBahan', function ($query) use ($statusFilter) {
                    $query->where('keterangan', ucfirst(strtolower($statusFilter)));
                });
            }

            $stokBahan = $stokBahan->get();

            // Total bahan (jumlah jenis bahan unik)
            $totalBahan = $stokBahan
                ->pluck('pembelianBahan.bahan.nama_bahan')
                ->filter()
                ->unique()
                ->count();

            // Total roll
            $totalRoll = $stokBahan->count();

            // Total berat - pisahkan berdasarkan satuan (kg dan yard)
            $totalBeratKg = 0;
            $totalBeratYard = 0;
            $totalHarga = 0;
            $bahanHargaMap = [];

            foreach ($stokBahan as $item) {
                $bahanId = optional(optional($item->pembelianBahan)->bahan)->id;
                $namaBahan = optional(optional($item->pembelianBahan)->bahan)->nama_bahan;
                $hargaBahan = optional(optional($item->pembelianBahan)->bahan)->harga ?? 0;
                $satuanBahan = optional(optional($item->pembelianBahan)->bahan)->satuan ?? null;
                $berat = $item->berat ?? 0;

                // Pisahkan berat berdasarkan satuan
                if (strtolower($satuanBahan) === 'kilogram' || strtolower($satuanBahan) === 'kg') {
                    $totalBeratKg += $berat;
                } elseif (strtolower($satuanBahan) === 'yard') {
                    $totalBeratYard += $berat;
                }

                if ($bahanId && $hargaBahan > 0) {
                    // Simpan harga bahan per ID untuk menghindari query berulang
                    if (!isset($bahanHargaMap[$bahanId])) {
                        $bahanHargaMap[$bahanId] = $hargaBahan;
                    }
                    $totalHarga += $bahanHargaMap[$bahanId] * $berat;
                }
            }

            // Breakdown per status (dari data yang sudah terfilter)
            $totalRollUtuh = $stokBahan->filter(function ($item) {
                $keterangan = $item->pembelianBahan->keterangan ?? null;
                return strtolower($keterangan) === 'utuh';
            })->count();

            $totalRollSisa = $stokBahan->filter(function ($item) {
                $keterangan = $item->pembelianBahan->keterangan ?? null;
                return strtolower($keterangan) === 'sisa';
            })->count();

            // Jika filter aktif, breakdown akan sesuai dengan filter
            // Misalnya jika filter "utuh", maka total_roll_utuh = total_roll dan total_roll_sisa = 0

            return response()->json([
                'total_bahan' => $totalBahan,
                'total_roll' => $totalRoll,
                'total_berat_kg' => round($totalBeratKg, 2),
                'total_berat_yard' => round($totalBeratYard, 2),
                'total_harga' => round($totalHarga, 2),
                'total_roll_utuh' => $totalRollUtuh,
                'total_roll_sisa' => $totalRollSisa,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getDashboardStats: ' . $e->getMessage());
            return response()->json([
                'error' => 'Terjadi kesalahan saat mengambil statistik dashboard',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get summary total roll per status (Semua, Utuh, Sisa)
     * Digunakan untuk menampilkan jumlah roll di tombol filter
     */
    public function getSummaryTotalRoll()
    {
        try {
            $stokBahan = StokBahan::with(['pembelianBahan'])
                ->where(function ($query) {
                    $query->where('status', 'tersedia')
                        ->orWhereNull('status');
                })
                ->get();

            // Total semua roll
            $totalSemua = $stokBahan->count();

            // Total roll utuh
            $totalUtuh = $stokBahan->filter(function ($item) {
                $keterangan = $item->pembelianBahan->keterangan ?? null;
                return strtolower($keterangan) === 'utuh';
            })->count();

            // Total roll sisa
            $totalSisa = $stokBahan->filter(function ($item) {
                $keterangan = $item->pembelianBahan->keterangan ?? null;
                return strtolower($keterangan) === 'sisa';
            })->count();

            return response()->json([
                'total_semua' => $totalSemua,
                'total_utuh' => $totalUtuh,
                'total_sisa' => $totalSisa,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getSummaryTotalRoll: ' . $e->getMessage());
            return response()->json([
                'error' => 'Terjadi kesalahan saat mengambil summary total roll',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get daftar warna dari pembelian bahan dengan informasi stok tersedia
     * Digunakan untuk dropdown warna di SPK Cutting
     * @param Request $request - bisa menerima parameter bahan_id untuk filter berdasarkan bahan
     */
    public function getWarnaDenganStok(Request $request)
    {
        try {
            $bahanId = $request->get('bahan_id');

            // Query untuk mendapatkan warna
            $query = PembelianBahanWarna::select('pembelian_bahan_warna.warna')
                ->distinct()
                ->whereNotNull('pembelian_bahan_warna.warna')
                ->where('pembelian_bahan_warna.warna', '!=', '');

            // Jika bahan_id diberikan, filter berdasarkan bahan
            if ($bahanId) {
                $query->join('pembelian_bahan', 'pembelian_bahan_warna.pembelian_bahan_id', '=', 'pembelian_bahan.id')
                    ->where('pembelian_bahan.bahan_id', $bahanId);
            }

            $semuaWarna = $query->get()
                ->pluck('warna')
                ->map(function ($w) { return strtoupper(trim($w)); })
                ->unique()
                ->values()
                ->toArray();

            if (empty($semuaWarna)) {
                return response()->json([]);
            }

            // Single optimized query: count stok per warna using LEFT JOIN + GROUP BY
            $stokPerWarna = \DB::table('stok_bahan')
                ->join('pembelian_bahan_warna', 'stok_bahan.pembelian_bahan_warna_id', '=', 'pembelian_bahan_warna.id')
                ->where(function ($q) {
                    $q->where('stok_bahan.status', 'tersedia')
                      ->orWhereNull('stok_bahan.status');
                });

            if ($bahanId) {
                $stokPerWarna->join('pembelian_bahan', 'stok_bahan.pembelian_bahan_id', '=', 'pembelian_bahan.id')
                    ->where('pembelian_bahan.bahan_id', $bahanId);
            }

            $stokCounts = $stokPerWarna
                ->select(\DB::raw('UPPER(TRIM(pembelian_bahan_warna.warna)) as warna_upper'), \DB::raw('COUNT(stok_bahan.id) as stok'))
                ->groupBy('warna_upper')
                ->pluck('stok', 'warna_upper')
                ->toArray();

            // Build result: all warna with their stock count (default 0 if not found)
            $warnaDenganStok = collect($semuaWarna)->map(function ($warna) use ($stokCounts) {
                return [
                    'warna' => $warna,
                    'stok' => $stokCounts[$warna] ?? 0,
                ];
            })
                ->sortBy('warna')
                ->values();

            return response()->json($warnaDenganStok);
        } catch (\Exception $e) {
            Log::error('Error in getWarnaDenganStok: ' . $e->getMessage());
            return response()->json([
                'error' => 'Terjadi kesalahan saat mengambil data warna',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
