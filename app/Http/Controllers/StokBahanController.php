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

    public function stokPerBahan()
    {
        try {
            $stokBahan = StokBahan::with(['pembelianBahan.bahan', 'pabrik', 'gudang', 'warna'])
                ->where(function ($query) {
                    $query->where('status', 'tersedia')
                        ->orWhereNull('status');
                })
                ->get();

            // Jika tidak ada data, return array kosong
            if ($stokBahan->isEmpty()) {
                return response()->json([]);
            }

            $stokPerBahan = $stokBahan
                ->groupBy(function ($item) {
                    return optional(optional($item->pembelianBahan)->bahan)->nama_bahan ?? 'Tidak Diketahui';
                })
                ->map(function ($items, $namaBahan) {
                    $totalBerat = $items->sum('berat');
                    $totalRol = $items->count();
                    $warna = $items->pluck('warna.warna')->filter()->unique()->values();
                    $gudang = $items->pluck('gudang.nama_gudang')->filter()->unique()->values();
                    $pabrik = $items->pluck('pabrik.nama_pabrik')->filter()->unique()->values();

                    // Ambil status (keterangan) dan SKU dari pembelian_bahan
                    $keterangan = $items->pluck('pembelianBahan.keterangan')->filter()->unique()->values();
                    $sku = $items->pluck('pembelianBahan.sku')->filter()->unique()->values();

                    return [
                        'nama_bahan' => $namaBahan,
                        'total_berat' => round($totalBerat, 2),
                        'total_rol' => $totalRol,
                        'warna' => $warna,
                        'gudang' => $gudang,
                        'pabrik' => $pabrik,
                        'status' => $keterangan->first() ?? null, // Ambil status pertama (Utuh atau Sisa)
                        'sku' => $sku->first() ?? null, // Ambil SKU pertama
                        'detail' => $items->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'barcode' => $item->barcode,
                                'berat' => $item->berat,
                                'warna' => $item->warna->warna ?? null,
                                'nama_gudang' => $item->gudang->nama_gudang ?? null,
                                'nama_pabrik' => $item->pabrik->nama_pabrik ?? null,
                                'keterangan' => $item->pembelianBahan->keterangan ?? null,
                                'sku' => $item->pembelianBahan->sku ?? null,
                                'scanned_at' => $item->scanned_at,
                            ];
                        }),
                    ];
                })
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
                ->unique()
                ->values();

            // Hitung stok tersedia untuk setiap warna
            $warnaDenganStok = $semuaWarna->map(function ($warna) use ($bahanId) {
                $stokQuery = StokBahan::whereHas('warna', function ($query) use ($warna) {
                    $query->where('warna', $warna);
                })
                    ->where(function ($query) {
                        $query->where('status', 'tersedia')
                            ->orWhereNull('status');
                    });

                // Jika bahan_id diberikan, filter stok berdasarkan bahan juga
                if ($bahanId) {
                    $stokQuery->whereHas('pembelianBahan', function ($query) use ($bahanId) {
                        $query->where('bahan_id', $bahanId);
                    });
                }

                $stokTersedia = $stokQuery->count();

                return [
                    'warna' => $warna,
                    'stok' => $stokTersedia,
                ];
            })
                ->sortBy('warna')
                ->values();

            // Pastikan "Lainnya" selalu ada di list dengan stok 999 (selalu bisa dipilih)
            $hasLainnya = $warnaDenganStok->contains(function ($item) {
                return $item['warna'] === 'Lainnya';
            });
            if (!$hasLainnya) {
                $warnaDenganStok->push([
                    'warna' => 'Lainnya',
                    'stok' => 999,
                ]);
            }

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
