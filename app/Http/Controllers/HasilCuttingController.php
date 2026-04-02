<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SpkCutting;
use App\Models\StokBahanKeluar;
use App\Models\SpkCuttingDistribusi;
use App\Models\SpkCuttingDistribusiDetail;
use App\Models\HasilCutting;
use App\Models\TukangCutting;
use App\Models\HasilCuttingBahan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\SpkCuttingStatusLog;


class HasilCuttingController extends Controller
{
    /**
     * Get list data hasil cutting (untuk index)
     */
    public function index(Request $request)
    {
        try {
            // Batasi per_page agar query tetap stabil untuk data besar
            $perPage = max(1, min((int) $request->input('per_page', 7), 100));

            $query = HasilCutting::query()
                ->select([
                    'id',
                    'spk_cutting_id',
                    'total_produk',
                    'total_bayar',
                    'created_at',
                ])
                ->withSum('bahan as bahan_sum_jumlah_produk', 'jumlah_produk')
                ->withSum('bahan as bahan_sum_hasil', 'hasil')
                ->with([
                'spkCutting:id,id_spk_cutting,produk_id,harga_jasa,satuan_harga,harga_per_pcs,tukang_cutting_id',
                'spkCutting.produk:id,nama_produk',
                'spkCutting.tukangCutting:id,nama_tukang_cutting',
            ]);

            // Filter berdasarkan tukang cutting jika ada (gunakan ID agar query lebih efisien)
            $tukangCuttingId = null;
            $invalidTukangFilter = false;
            if ($request->filled('tukang_cutting')) {
                $tukangNama = trim((string) $request->input('tukang_cutting'));
                $tukangCuttingId = TukangCutting::where('nama_tukang_cutting', $tukangNama)->value('id');

                if ($tukangCuttingId) {
                    $query->whereHas('spkCutting', function ($q) use ($tukangCuttingId) {
                        $q->where('tukang_cutting_id', $tukangCuttingId);
                    });
                } else {
                    // Nama tukang tidak valid -> paksa hasil kosong tanpa scan tabel besar
                    $invalidTukangFilter = true;
                    $query->whereRaw('1 = 0');
                }
            }

            // Filter berdasarkan keyword pencarian (SPK, Produk, Tukang Cutting)
            if ($request->filled('search')) {
                $searchTerm = trim((string) $request->input('search'));
                if ($searchTerm !== '') {
                    $query->whereHas('spkCutting', function ($q) use ($searchTerm) {
                        $q->where('id_spk_cutting', 'like', "{$searchTerm}%")
                            ->orWhere('id_spk_cutting', 'like', "%{$searchTerm}%")
                            ->orWhereHas('produk', function ($p) use ($searchTerm) {
                                $p->where('nama_produk', 'like', "%{$searchTerm}%");
                            })
                            ->orWhereHas('tukangCutting', function ($t) use ($searchTerm) {
                                $t->where('nama_tukang_cutting', 'like', "%{$searchTerm}%");
                            });
                    });
                }
            }

            // Filter berdasarkan periode minggu jika ada
            // Prioritas: jika weekly_start dan weekly_end ada, gunakan itu (jangan gunakan daily_date)
            if ($request->filled('weekly_start') && $request->filled('weekly_end')) {
                $weeklyStart = Carbon::parse($request->input('weekly_start'))->startOfDay();
                $weeklyEnd = Carbon::parse($request->input('weekly_end'))->endOfDay();
                $query->whereBetween('created_at', [$weeklyStart, $weeklyEnd]);
            } elseif ($request->filled('daily_date')) {
                // Hanya gunakan daily_date jika weekly_start/weekly_end tidak ada
                $query->whereDate('created_at', $request->input('daily_date'));
            }

            $hasilCutting = $query->orderByDesc('created_at')->paginate($perPage);

            // Format data untuk response
            $formattedData = $hasilCutting->map(function ($item) {
                // Ambil total produk dari kolom utama; fallback ke agregat bahan (tanpa load relasi penuh)
                $totalProduk = (int) ($item->total_produk ?? $item->bahan_sum_jumlah_produk ?? $item->bahan_sum_hasil ?? 0);

                // Hitung total bayar hanya untuk response (read-only, tanpa update DB)
                $totalBayar = (float) ($item->total_bayar ?? 0);
                if ($totalBayar <= 0 && $item->spkCutting) {
                    $spkCutting = $item->spkCutting;
                    $hargaPerPcs = (float) ($spkCutting->harga_per_pcs ?? 0);

                    if ($hargaPerPcs <= 0 && !empty($spkCutting->harga_jasa)) {
                        $satuanHarga = $spkCutting->satuan_harga ?? 'Pcs';
                        $hargaPerPcs = $satuanHarga === 'Lusin'
                            ? (float) $spkCutting->harga_jasa / 12
                            : (float) $spkCutting->harga_jasa;
                    }

                    $totalBayar = $hargaPerPcs * $totalProduk;
                }

                return [
                    'id' => $item->id,
                    'spk_cutting_id' => $item->spk_cutting_id,
                    'id_spk_cutting' => $item->spkCutting->id_spk_cutting ?? null,
                    'nama_produk' => $item->spkCutting->produk->nama_produk ?? null,
                    'tukang_cutting_id' => $item->spkCutting->tukang_cutting_id ?? null,
                    'nama_tukang_cutting' => $item->spkCutting->tukangCutting->nama_tukang_cutting ?? null,
                    'total_produk' => $totalProduk,
                    'total_bayar' => $totalBayar,
                    'created_at' => $item->created_at,
                ];
            });

            // ==========================
            // Stat target mingguan & harian (dengan custom period)
            // ==========================
            // Gunakan filter yang sama dengan query utama untuk konsistensi
            $weeklyStartInput = $request->input('weekly_start');
            $weeklyEndInput = $request->input('weekly_end');
            $dailyDateInput = $request->input('daily_date');

            // Tentukan periode untuk statistik berdasarkan filter yang digunakan di query utama
            if ($weeklyStartInput && $weeklyEndInput) {
                // Jika ada filter mingguan, gunakan itu untuk statistik juga
                $startOfWeek = Carbon::parse($weeklyStartInput)->startOfDay();
                $endOfWeek = Carbon::parse($weeklyEndInput)->endOfDay();
            } else {
                // Default: Senin–Sabtu (startOfWeek sampai startOfWeek + 5 hari)
                $startOfWeek = Carbon::today()->startOfWeek(); // Senin
                $endOfWeek = $startOfWeek->copy()->addDays(5)->endOfDay(); // Sabtu
            }

            // Daily: gunakan daily_date jika ada, atau default hari ini
            $today = $dailyDateInput ? Carbon::parse($dailyDateInput)->startOfDay() : Carbon::today();

            $weeklyTarget = 50000;
            $dailyTarget = 7143;

            // Hitung statistik dengan satu query agregasi (lebih hemat dibanding 2 query terpisah)
            if ($invalidTukangFilter) {
                $weeklyTotal = 0;
                $dailyTotal = 0;
            } else {
                $statsQuery = HasilCutting::query();

                if ($tukangCuttingId) {
                    $statsQuery->whereHas('spkCutting', function ($q) use ($tukangCuttingId) {
                        $q->where('tukang_cutting_id', $tukangCuttingId);
                    });
                }

                $statsRow = $statsQuery
                    ->selectRaw(
                        "COALESCE(SUM(CASE WHEN created_at BETWEEN ? AND ? THEN total_produk ELSE 0 END), 0) AS weekly_total",
                        [$startOfWeek->toDateTimeString(), $endOfWeek->toDateTimeString()]
                    )
                    ->selectRaw(
                        "COALESCE(SUM(CASE WHEN DATE(created_at) = ? THEN total_produk ELSE 0 END), 0) AS daily_total",
                        [$today->toDateString()]
                    )
                    ->first();

                $weeklyTotal = (int) ($statsRow->weekly_total ?? 0);
                $dailyTotal = (int) ($statsRow->daily_total ?? 0);
            }

            $weeklyRemaining = max(0, $weeklyTarget - $weeklyTotal);
            $dailyRemaining = max(0, $dailyTarget - $dailyTotal);

            return response()->json([
                'data' => $formattedData,
                'current_page' => $hasilCutting->currentPage(),
                'last_page' => $hasilCutting->lastPage(),
                'total' => $hasilCutting->total(),
                'stats' => [
                    'weekly_target' => $weeklyTarget,
                    'weekly_total' => $weeklyTotal,
                    'weekly_remaining' => $weeklyRemaining,
                    'daily_target' => $dailyTarget,
                    'daily_total' => $dailyTotal,
                    'daily_remaining' => $dailyRemaining,
                    'week_start' => $startOfWeek->toDateString(),
                    'week_end' => $endOfWeek->toDateString(),
                    'today' => $today->toDateString(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error in HasilCuttingController@index: ' . $e->getMessage());
            return response()->json([
                'message' => 'Terjadi kesalahan saat mengambil data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detail SPK Cutting dengan berat dari stok_bahan_keluar (untuk form input)
     */
    public function getSpkCuttingDetail(Request $request)
    {
        try {
            $spkCuttingId = $request->input('spk_cutting_id');

            if (!$spkCuttingId) {
                return response()->json([
                    'message' => 'SPK Cutting ID diperlukan'
                ], 400);
            }

            // Ambil detail SPK Cutting dengan relasi
            $spkCutting = SpkCutting::with([
                'bagian.bahan.bahan',
                'produk:id,nama_produk',
                'skus.produk:id,nama_produk'
            ])->find($spkCuttingId);

            if (!$spkCutting) {
                Log::warning("SPK Cutting tidak ditemukan dengan ID: " . $spkCuttingId);
                Log::warning("Mencoba mencari dengan id_spk_cutting...");

                // Coba cari dengan id_spk_cutting jika tidak ditemukan dengan ID
                $spkCutting = SpkCutting::with([
                    'bagian.bahan.bahan',
                    'produk:id,nama_produk',
                    'skus.produk:id,nama_produk'
                ])->where('id_spk_cutting', $spkCuttingId)->first();

                if (!$spkCutting) {
                    Log::warning("SPK Cutting juga tidak ditemukan dengan id_spk_cutting: " . $spkCuttingId);
                    return response()->json([
                        'message' => 'SPK Cutting tidak ditemukan',
                        'spk_cutting_id' => $spkCuttingId
                    ], 404);
                }

                Log::info("SPK Cutting ditemukan dengan id_spk_cutting: " . $spkCutting->id);
            }

            Log::info("SPK Cutting ditemukan: " . $spkCutting->id . ", Jumlah bagian: " . $spkCutting->bagian->count());

            // Ambil data berat dari stok_bahan_keluar yang sudah di-scan
            $stokBahanKeluar = StokBahanKeluar::where('spk_cutting_id', $spkCuttingId)
                ->with([
                    'spkCuttingBahan.bagian',
                    'spkCuttingBahan.bahan'
                ])
                ->get();

            Log::info("Jumlah stok_bahan_keluar: " . $stokBahanKeluar->count());

            // Kelompokkan berat berdasarkan spk_cutting_bahan_id
            $beratPerBahan = [];
            foreach ($stokBahanKeluar as $item) {
                $bahanId = $item->spk_cutting_bahan_id;
                if (!isset($beratPerBahan[$bahanId])) {
                    $beratPerBahan[$bahanId] = 0;
                }
                $beratPerBahan[$bahanId] += $item->berat ?? 0;
            }

            // Format data untuk response
            $detailData = [];

            // Jika SPK Cutting memiliki bagian dan bahan, gunakan data dari relasi
            if ($spkCutting->bagian->count() > 0) {
                foreach ($spkCutting->bagian as $bagian) {
                    Log::info("Memproses bagian: " . $bagian->id . " - " . $bagian->nama_bagian . ", Jumlah bahan: " . $bagian->bahan->count());
                    foreach ($bagian->bahan as $bahan) {
                        $beratScanned = $beratPerBahan[$bahan->id] ?? 0;

                        $detailData[] = [
                            'spk_cutting_bahan_id' => $bahan->id,
                            'spk_cutting_bagian_id' => $bagian->id,
                            'nama_bagian' => $bagian->nama_bagian,
                            'bahan_id' => $bahan->bahan_id,
                            'nama_bahan' => $bahan->bahan->nama_bahan ?? null,
                            'warna' => $bahan->warna,
                            'qty' => $bahan->qty,
                            'berat_spk' => $bahan->berat, // Berat dari SPK Cutting (rencana)
                            'berat_scanned' => round($beratScanned, 2), // Berat yang sudah di-scan keluar
                        ];
                    }
                }
            } else {
                // Jika tidak ada bagian/bahan di relasi, ambil dari stok_bahan_keluar
                Log::info("SPK Cutting tidak memiliki bagian/bahan di relasi, mengambil dari stok_bahan_keluar");
                Log::info("Jumlah stok_bahan_keluar ditemukan: " . $stokBahanKeluar->count());

                // Ambil data unik dari stok_bahan_keluar berdasarkan spk_cutting_bahan_id
                $uniqueData = [];
                foreach ($stokBahanKeluar as $item) {
                    if (!$item->spk_cutting_bahan_id) {
                        Log::warning("Item stok_bahan_keluar tidak memiliki spk_cutting_bahan_id: " . $item->id);
                        continue; // Skip jika tidak ada spk_cutting_bahan_id
                    }

                    $bahanId = $item->spk_cutting_bahan_id;

                    if (!isset($uniqueData[$bahanId])) {
                        $spkBahan = $item->spkCuttingBahan;

                        if (!$spkBahan) {
                            Log::warning("spkCuttingBahan tidak ditemukan untuk spk_cutting_bahan_id: " . $bahanId);
                            // Tetap buat data meskipun relasi tidak ada
                            $uniqueData[$bahanId] = [
                                'spk_cutting_bahan_id' => $bahanId,
                                'spk_cutting_bagian_id' => null,
                                'nama_bagian' => null,
                                'bahan_id' => null,
                                'nama_bahan' => null,
                                'warna' => null,
                                'qty' => null,
                                'berat_spk' => 0,
                                'berat_scanned' => 0,
                            ];
                        } else {
                            $bagian = $spkBahan->bagian;
                            $bahan = $spkBahan->bahan;

                            $uniqueData[$bahanId] = [
                                'spk_cutting_bahan_id' => $bahanId,
                                'spk_cutting_bagian_id' => $spkBahan->spk_cutting_bagian_id ?? null,
                                'nama_bagian' => $bagian->nama_bagian ?? null,
                                'bahan_id' => $spkBahan->bahan_id ?? null,
                                'nama_bahan' => $bahan->nama_bahan ?? null,
                                'warna' => $spkBahan->warna ?? null,
                                'qty' => $spkBahan->qty ?? null,
                                'berat_spk' => $spkBahan->berat ?? 0,
                                'berat_scanned' => 0,
                            ];
                        }
                    }

                    // Tambahkan berat
                    $uniqueData[$bahanId]['berat_scanned'] += $item->berat ?? 0;
                }

                // Konversi ke array dan bulatkan berat
                foreach ($uniqueData as $data) {
                    $data['berat_scanned'] = round($data['berat_scanned'], 2);
                    $detailData[] = $data;
                }

                Log::info("Data dari stok_bahan_keluar: " . count($detailData) . " item");
            }

            // Jika masih kosong setelah semua usaha, coba ambil langsung dari stok_bahan_keluar tanpa relasi
            if (count($detailData) == 0 && $stokBahanKeluar->count() > 0) {
                Log::info("Detail masih kosong, mencoba mengambil langsung dari stok_bahan_keluar tanpa relasi");

                // Ambil data langsung dari stok_bahan_keluar dan join dengan tabel terkait
                $stokBahanKeluarRaw = DB::table('stok_bahan_keluar')
                    ->leftJoin('spk_cutting_bahan', 'stok_bahan_keluar.spk_cutting_bahan_id', '=', 'spk_cutting_bahan.id')
                    ->leftJoin('spk_cutting_bagian', 'spk_cutting_bahan.spk_cutting_bagian_id', '=', 'spk_cutting_bagian.id')
                    ->leftJoin('bahan', 'spk_cutting_bahan.bahan_id', '=', 'bahan.id')
                    ->where('stok_bahan_keluar.spk_cutting_id', $spkCuttingId)
                    ->select(
                        'stok_bahan_keluar.spk_cutting_bahan_id',
                        'spk_cutting_bahan.spk_cutting_bagian_id',
                        'spk_cutting_bagian.nama_bagian',
                        'spk_cutting_bahan.bahan_id',
                        'bahan.nama_bahan',
                        'spk_cutting_bahan.warna',
                        'spk_cutting_bahan.qty',
                        'spk_cutting_bahan.berat as berat_spk',
                        DB::raw('SUM(stok_bahan_keluar.berat) as berat_scanned')
                    )
                    ->groupBy(
                        'stok_bahan_keluar.spk_cutting_bahan_id',
                        'spk_cutting_bahan.spk_cutting_bagian_id',
                        'spk_cutting_bagian.nama_bagian',
                        'spk_cutting_bahan.bahan_id',
                        'bahan.nama_bahan',
                        'spk_cutting_bahan.warna',
                        'spk_cutting_bahan.qty',
                        'spk_cutting_bahan.berat'
                    )
                    ->get();

                foreach ($stokBahanKeluarRaw as $row) {
                    $detailData[] = [
                        'spk_cutting_bahan_id' => $row->spk_cutting_bahan_id,
                        'spk_cutting_bagian_id' => $row->spk_cutting_bagian_id,
                        'nama_bagian' => $row->nama_bagian,
                        'bahan_id' => $row->bahan_id,
                        'nama_bahan' => $row->nama_bahan,
                        'warna' => $row->warna,
                        'qty' => $row->qty,
                        'berat_spk' => $row->berat_spk ?? 0,
                        'berat_scanned' => round($row->berat_scanned ?? 0, 2),
                    ];
                }

                Log::info("Data dari query raw: " . count($detailData) . " item");
            }

            Log::info("Total detail data: " . count($detailData));

            // Pastikan selalu mengembalikan response, meskipun detail kosong
            return response()->json([
                'spk_cutting' => [
                    'id' => $spkCutting->id,
                    'id_spk_cutting' => $spkCutting->id_spk_cutting,
                    'nama_produk' => $spkCutting->produk->nama_produk ?? null,
                ],
                'skus' => $spkCutting->skus->map(function ($sku) {
                    return [
                        'id' => $sku->id,
                        'sku' => $sku->sku,
                        'warna' => $sku->warna,
                        'ukuran' => $sku->ukuran,
                        'nama_produk' => $sku->produk->nama_produk ?? null,
                    ];
                })->toArray(),
                'detail' => $detailData
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error in HasilCuttingController@getSpkCuttingDetail: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'Terjadi kesalahan saat mengambil data',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get detail hasil cutting by ID
     */
    public function show($id)
    {
        try {
            // Load relasi dasar terlebih dahulu
            $hasilCutting = HasilCutting::with([
                'spkCutting:id,id_spk_cutting,produk_id,harga_jasa,satuan_harga,harga_per_pcs',
                'spkCutting.produk:id,nama_produk',
                'bahan.spkCuttingBahan.bagian',
                'bahan.spkCuttingBahan.bahan'
            ])->find($id);

            if (!$hasilCutting) {
                return response()->json([
                    'message' => 'Data hasil cutting tidak ditemukan'
                ], 404);
            }

            // Load distribusi secara terpisah dengan error handling
            $distribusi = [];
            try {
                // Coba load relasi distribusi dengan detail
                if (method_exists($hasilCutting, 'distribusi')) {
                    $hasilCutting->load('distribusi.detail');
                    if ($hasilCutting->distribusi && $hasilCutting->distribusi->count() > 0) {
                        $distribusi = $hasilCutting->distribusi->map(function ($dist) {
                            return [
                                'id' => $dist->id,
                                'kode_seri' => $dist->kode_seri,
                                'jumlah_produk' => $dist->jumlah_produk,
                                'status' => $dist->status,
                                'detail' => $dist->detail->map(function ($d) {
                                    return [
                                        'id' => $d->id,
                                        'warna' => $d->warna,
                                        'jumlah_produk' => $d->jumlah_produk,
                                    ];
                                })->toArray(),
                            ];
                        })->toArray();
                    }
                } else {
                    // Jika relasi belum ada, coba query langsung
                    $distribusiData = \App\Models\SpkCuttingDistribusi::with('detail')
                        ->where('hasil_cutting_id', $hasilCutting->id)
                        ->get();
                    if ($distribusiData && $distribusiData->count() > 0) {
                        $distribusi = $distribusiData->map(function ($dist) {
                            return [
                                'id' => $dist->id,
                                'kode_seri' => $dist->kode_seri,
                                'jumlah_produk' => $dist->jumlah_produk,
                                'status' => $dist->status,
                                'detail' => $dist->detail->map(function ($d) {
                                    return [
                                        'id' => $d->id,
                                        'warna' => $d->warna,
                                        'jumlah_produk' => $d->jumlah_produk,
                                        'produk_sku_id' => $d->produk_sku_id,
                                    ];
                                })->toArray(),
                            ];
                        })->toArray();
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Gagal load distribusi untuk hasil_cutting ID: ' . $id . ' - ' . $e->getMessage());
                $distribusi = [];
            }

            // Pastikan data_acuan selalu berupa array
            $dataAcuan = $hasilCutting->data_acuan;
            if (!is_array($dataAcuan)) {
                $dataAcuan = is_string($dataAcuan) ? json_decode($dataAcuan, true) : [];
                if (!is_array($dataAcuan)) {
                    $dataAcuan = [];
                }
            }

            // Pastikan status_perbandingan_agregat selalu berupa array
            $statusPerbandinganAgregat = $hasilCutting->status_perbandingan_agregat;
            if (!is_array($statusPerbandinganAgregat)) {
                $statusPerbandinganAgregat = is_string($statusPerbandinganAgregat) ? json_decode($statusPerbandinganAgregat, true) : [];
                if (!is_array($statusPerbandinganAgregat)) {
                    $statusPerbandinganAgregat = [];
                }
            }

            // Hitung total_bayar jika masih 0 atau null
            $totalBayar = $hasilCutting->total_bayar ?? 0;
            if (($totalBayar == 0 || $totalBayar == null) && $hasilCutting->spkCutting) {
                $spkCutting = $hasilCutting->spkCutting;
                $hargaPerPcs = $spkCutting->harga_per_pcs ?? 0;

                // Jika harga_per_pcs masih 0, hitung dari harga_jasa dan satuan_harga
                if ($hargaPerPcs == 0 && $spkCutting->harga_jasa) {
                    $satuanHarga = $spkCutting->satuan_harga ?? 'Pcs';
                    $hargaPerPcs = $satuanHarga === 'Lusin'
                        ? $spkCutting->harga_jasa / 12
                        : $spkCutting->harga_jasa;

                    // Update harga_per_pcs di spk_cutting jika masih 0
                    if ($spkCutting->harga_per_pcs == 0 || $spkCutting->harga_per_pcs == null) {
                        $spkCutting->update(['harga_per_pcs' => $hargaPerPcs]);
                    }
                }

                $totalProduk = $hasilCutting->total_produk ?? 0;
                $totalBayar = $hargaPerPcs * $totalProduk;

                Log::info('Menghitung ulang total_bayar untuk hasil_cutting ID: ' . $hasilCutting->id, [
                    'harga_per_pcs' => $hargaPerPcs,
                    'total_produk' => $totalProduk,
                    'total_bayar' => $totalBayar,
                    'harga_jasa' => $spkCutting->harga_jasa ?? null,
                    'satuan_harga' => $spkCutting->satuan_harga ?? null
                ]);

                // Update database jika total_bayar masih 0 atau null
                if ($hasilCutting->total_bayar == null || $hasilCutting->total_bayar == 0) {
                    $hasilCutting->update(['total_bayar' => $totalBayar]);
                }
            }

            return response()->json([
                'id' => $hasilCutting->id,
                'spk_cutting_id' => $hasilCutting->spk_cutting_id,
                'id_spk_cutting' => $hasilCutting->spkCutting->id_spk_cutting ?? null,
                'nama_produk' => $hasilCutting->spkCutting->produk->nama_produk ?? null,
                'nama_bagian' => $hasilCutting->nama_bagian,
                'nama_bahan' => $hasilCutting->nama_bahan,
                'warna' => $hasilCutting->warna,
                'qty' => $hasilCutting->qty,
                'total_produk' => $hasilCutting->total_produk,
                'total_bayar' => $totalBayar,
                'data_acuan' => $dataAcuan,
                'status_perbandingan_agregat' => $statusPerbandinganAgregat,
                'distribusi_seri' => collect($distribusi)->map(function ($dist) {
                    return [
                        'id' => $dist['id'] ?? null,
                        'kode_seri' => $dist['kode_seri'] ?? null,
                        'jumlah_produk' => $dist['jumlah_produk'] ?? 0,
                        'status' => $dist['status'] ?? 'draft',
                        'detail' => $dist['detail'] ?? [],
                    ];
                })->toArray(),
                'bahan' => $hasilCutting->bahan->map(function ($bahan) {
                    $spkBahan = $bahan->spkCuttingBahan;
                    return [
                        'id' => $bahan->id,
                        'spk_cutting_bahan_id' => $bahan->spk_cutting_bahan_id,
                        'spk_cutting_bagian_id' => $bahan->spk_cutting_bagian_id,
                        'produk_sku_id' => $bahan->produk_sku_id,
                        'nama_bagian' => $spkBahan && $spkBahan->bagian ? $spkBahan->bagian->nama_bagian : null,
                        'nama_bahan' => $spkBahan && $spkBahan->bahan ? $spkBahan->bahan->nama_bahan : null,
                        'warna' => $spkBahan ? $spkBahan->warna : null,
                        'qty' => $spkBahan ? $spkBahan->qty : null,
                        'jumlah_lembar' => $bahan->jumlah_lembar,
                        'jumlah_produk' => $bahan->jumlah_produk,
                        'berat' => $bahan->berat,
                        'berat_per_produk' => $bahan->berat_per_produk,
                        'hasil' => $bahan->hasil,
                        'total_produk' => $bahan->total_produk ?? $bahan->hasil,
                    ];
                }),
                'created_at' => $hasilCutting->created_at,
                'updated_at' => $hasilCutting->updated_at,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in HasilCuttingController@show: ' . $e->getMessage());
            return response()->json([
                'message' => 'Terjadi kesalahan saat mengambil data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        Log::info('REQUEST HEADER', $request->headers->all());
        Log::info('REQUEST ALL', $request->all());

        try {
            $validated = $request->validate([
                'spk_cutting_id' => 'required|exists:spk_cutting,id',

                'data_hasil' => 'required|array',
                'data_hasil.*.spk_cutting_bahan_id' => 'required|exists:spk_cutting_bahan,id',
                'data_hasil.*.spk_cutting_bagian_id' => 'required|exists:spk_cutting_bagian,id',
                'data_hasil.*.nama_bagian' => 'nullable|string',
                'data_hasil.*.nama_bahan' => 'nullable|string',
                'data_hasil.*.warna' => 'nullable|string',
                'data_hasil.*.qty' => 'nullable|numeric|min:0',
                'data_hasil.*.jumlah_lembar' => 'required|numeric|min:0',
                'data_hasil.*.jumlah_produk' => 'required|numeric|min:0',
                'data_hasil.*.total_produk' => 'required|numeric|min:0',
                'data_hasil.*.berat_total' => 'required|numeric|min:0',
                'data_hasil.*.berat_per_produk' => 'required|numeric|min:0',
                'data_hasil.*.produk_sku_id' => 'nullable|exists:produk_sku,id',

                'data_acuan' => 'nullable|array',
                'data_acuan.*.warna' => 'required|string',
                'data_acuan.*.berat_acuan' => 'required|numeric|min:0',
                'data_acuan.*.banyak_produk' => 'required|numeric|min:0',
                'data_acuan.*.berat_acuan_per_produk' => 'required|numeric|min:0',

                'status_perbandingan_agregat' => 'nullable|array',
                'status_perbandingan_agregat.*.warna' => 'required_with:status_perbandingan_agregat.*|string',
                'status_perbandingan_agregat.*.status' => 'nullable|string',
                'status_perbandingan_agregat.*.selisih' => 'nullable|numeric|min:0',
                'status_perbandingan_agregat.*.berat_per_produk' => 'nullable|numeric|min:0',
                'status_perbandingan_agregat.*.berat_acuan_per_produk' => 'nullable|numeric|min:0',

                'distribusi_seri' => 'nullable|array',
                'distribusi_seri.*.jumlah_produk' => 'required_with:distribusi_seri|numeric|min:1',
                'distribusi_seri.*.detail' => 'nullable|array',
                'distribusi_seri.*.detail.*.warna' => 'required_with:distribusi_seri.*.detail|string',
                'distribusi_seri.*.detail.*.jumlah_produk' => 'required_with:distribusi_seri.*.detail|numeric|min:1',
                'distribusi_seri.*.detail.*.produk_sku_id' => 'nullable|exists:produk_sku,id',
            ]);

            DB::beginTransaction();

            /**
             * ===============================
             * HITUNG TOTAL PRODUK
             * ===============================
             */
            $totalProduk = array_sum(array_column($validated['data_hasil'], 'total_produk'));

            /**
             * ===============================
             * DISTRIBUSI SERI
             * ===============================
             */
            if (!empty($validated['distribusi_seri'])) {
                // Distribusi eksplisit dari user
                $distribusiSeri = $validated['distribusi_seri'];
                $totalDistribusi = array_sum(array_column($distribusiSeri, 'jumlah_produk'));

                if ($totalDistribusi !== $totalProduk) {
                    throw new \Exception(
                        "Total distribusi ({$totalDistribusi}) harus sama dengan total hasil cutting ({$totalProduk})"
                    );
                }
            } else {
                // IMPLICIT SINGLE SERIES
                $distribusiSeri = [
                    ['jumlah_produk' => $totalProduk]
                ];
            }

            /**
             * ===============================
             * HITUNG TOTAL BAYAR
             * ===============================
             */
            $spkCutting  = \App\Models\SpkCutting::findOrFail($validated['spk_cutting_id']);
            $hargaPerPcs = $spkCutting->harga_per_pcs ?? 0;
            $totalBayar  = $hargaPerPcs * $totalProduk;

            /**
             * ===============================
             * SIMPAN HASIL CUTTING (HEADER)
             * ===============================
             */
            $firstData = $validated['data_hasil'][0] ?? [];

            $hasilCuttingData = [
                'spk_cutting_id'        => $validated['spk_cutting_id'],
                'spk_cutting_bagian_id' => $firstData['spk_cutting_bagian_id'] ?? null,
                'nama_bagian'           => $firstData['nama_bagian'] ?? null,
                'nama_bahan'            => $firstData['nama_bahan'] ?? null,
                'warna'                 => $firstData['warna'] ?? null,
                'qty'                   => $firstData['qty'] ?? null,
                'total_produk'          => $totalProduk,
                'total_bayar'           => $totalBayar,
            ];

            if (!empty($validated['data_acuan'])) {
                $hasilCuttingData['data_acuan'] = json_encode($validated['data_acuan']);
            }

            if (!empty($validated['status_perbandingan_agregat'])) {
                $hasilCuttingData['status_perbandingan_agregat'] =
                    json_encode($validated['status_perbandingan_agregat']);
            } else {
                $hasilCuttingData['status_perbandingan_agregat'] = null;
            }

            $hasilCutting = HasilCutting::create($hasilCuttingData);

            /**
             * ===============================
             * SIMPAN DISTRIBUSI SERI
             * ===============================
             */
            $alphabet = range('A', 'Z');
            
            // Ambil semua kode_seri yang sudah ada untuk SPK ini
            $existingKodeSeri = SpkCuttingDistribusi::where('spk_cutting_id', $validated['spk_cutting_id'])
                ->pluck('kode_seri')
                ->toArray();

            foreach ($distribusiSeri as $index => $seri) {
                // Cari kode_seri yang belum digunakan
                $baseKodeSeri = $spkCutting->id_spk_cutting;
                $suffixIndex = $index;
                $kodeSeri = $baseKodeSeri . $alphabet[$suffixIndex];
                
                // Jika kode_seri sudah ada, cari yang belum digunakan
                while (in_array($kodeSeri, $existingKodeSeri)) {
                    $suffixIndex++;
                    if ($suffixIndex >= count($alphabet)) {
                        // Jika sudah sampai Z, gunakan kombinasi AA, AB, dst
                        $firstIndex = ($suffixIndex - count($alphabet)) % count($alphabet);
                        $secondIndex = intval(($suffixIndex - count($alphabet)) / count($alphabet));
                        $kodeSeri = $baseKodeSeri . $alphabet[$firstIndex] . $alphabet[$secondIndex];
                    } else {
                        $kodeSeri = $baseKodeSeri . $alphabet[$suffixIndex];
                    }
                }
                
                // Tambahkan kode_seri yang baru dibuat ke daftar existing untuk iterasi berikutnya
                $existingKodeSeri[] = $kodeSeri;

                $distribusi = SpkCuttingDistribusi::create([
                    'spk_cutting_id'   => $validated['spk_cutting_id'],
                    'hasil_cutting_id' => $hasilCutting->id,
                    'kode_seri'        => $kodeSeri,
                    'jumlah_produk'    => $seri['jumlah_produk'],
                    'status'           => 'draft',
                ]);

                /**
                 * ===============================
                 * SIMPAN DETAIL DISTRIBUSI (PER WARNA)
                 * ===============================
                 */
                if (!empty($seri['detail']) && is_array($seri['detail']) && count($seri['detail']) > 0) {
                    // Jika user sudah mengisi detail warna, gunakan data dari user
                    $totalDetail = array_sum(array_column($seri['detail'], 'jumlah_produk'));

                    // Validasi total detail harus sama dengan jumlah_produk distribusi
                    if ($totalDetail !== $seri['jumlah_produk']) {
                        throw new \Exception(
                            "Total detail distribusi seri {$kodeSeri} ({$totalDetail}) harus sama dengan jumlah produk ({$seri['jumlah_produk']})"
                        );
                    }

                    foreach ($seri['detail'] as $detail) {
                        SpkCuttingDistribusiDetail::create([
                            'spk_cutting_distribusi_id' => $distribusi->id,
                            'warna'                    => $detail['warna'],
                            'jumlah_produk'             => $detail['jumlah_produk'],
                            'produk_sku_id'             => $detail['produk_sku_id'] ?? null,
                        ]);
                    }
                } else {
                    // Jika user tidak mengisi detail warna, ambil data dari data_hasil
                    // Kelompokkan data_hasil berdasarkan warna dan jumlahkan jumlah_produk
                    $dataPerWarna = [];
                    foreach ($validated['data_hasil'] as $dataHasil) {
                        $warna = $dataHasil['warna'] ?? 'Unknown';
                        if (!isset($dataPerWarna[$warna])) {
                            $dataPerWarna[$warna] = 0;
                        }
                        // Gunakan jumlah_produk dari data_hasil
                        $dataPerWarna[$warna] += $dataHasil['jumlah_produk'] ?? 0;
                    }

                    // Hitung total produk dari data_hasil untuk proporsi
                    $totalProdukDataHasil = array_sum($dataPerWarna);

                    if ($totalProdukDataHasil > 0) {
                        // Distribusikan jumlah_produk seri berdasarkan proporsi dari data_hasil
                        $sisaJumlahProduk = $seri['jumlah_produk'];
                        $warnaArray = array_keys($dataPerWarna);
                        $totalWarna = count($warnaArray);

                        foreach ($warnaArray as $index => $warna) {
                            $jumlahProdukWarna = $dataPerWarna[$warna];
                            $isLast = ($index === $totalWarna - 1);

                            if ($isLast) {
                                // Untuk warna terakhir, gunakan sisa yang ada untuk menghindari pembulatan
                                $jumlahDistribusi = $sisaJumlahProduk;
                            } else {
                                // Hitung proporsi berdasarkan jumlah_produk per warna
                                $proporsi = $jumlahProdukWarna / $totalProdukDataHasil;
                                $jumlahDistribusi = round($seri['jumlah_produk'] * $proporsi);
                                $sisaJumlahProduk -= $jumlahDistribusi;
                            }

                            // Pastikan jumlah distribusi minimal 1 jika ada data warna
                            if ($jumlahDistribusi < 1 && $jumlahProdukWarna > 0) {
                                $jumlahDistribusi = 1;
                            }

                            // Hanya simpan jika jumlah distribusi > 0
                            if ($jumlahDistribusi > 0) {
                                SpkCuttingDistribusiDetail::create([
                                    'spk_cutting_distribusi_id' => $distribusi->id,
                                    'warna'                    => $warna,
                                    'jumlah_produk'             => $jumlahDistribusi,
                                    'produk_sku_id'            => null, // Default null jika tidak ada SKU yang dipilih
                                ]);
                            }
                        }
                    } else {
                        // Jika tidak ada data_hasil atau total 0, buat satu record default
                        Log::warning("Tidak ada data_hasil untuk membuat detail distribusi seri {$kodeSeri}");
                        SpkCuttingDistribusiDetail::create([
                            'spk_cutting_distribusi_id' => $distribusi->id,
                            'warna'                    => 'Unknown',
                            'jumlah_produk'             => $seri['jumlah_produk'],
                            'produk_sku_id'            => null,
                        ]);
                    }
                }
            }

            /**
             * ===============================
             * SIMPAN HASIL CUTTING PER BAHAN
             * ===============================
             */
            foreach ($validated['data_hasil'] as $data) {
                HasilCuttingBahan::create([
                    'hasil_cutting_id'      => $hasilCutting->id,
                    'spk_cutting_bahan_id'  => $data['spk_cutting_bahan_id'],
                    'spk_cutting_bagian_id' => $data['spk_cutting_bagian_id'],
                    'produk_sku_id'         => $data['produk_sku_id'] ?? null,
                    'jumlah_lembar'         => $data['jumlah_lembar'],
                    'jumlah_produk'         => $data['jumlah_produk'],
                    'berat'                 => $data['berat_total'],
                    'berat_per_produk'      => $data['berat_per_produk'],
                    'hasil'                 => $data['total_produk'],
                ]);
            }

            /**
             * ===============================
             * UPDATE STATUS SPK CUTTING
             * ===============================
             */
            $deadline = Carbon::parse($spkCutting->tanggal_batas_kirim);

            $sisaHariTerakhir = $deadline->isPast()
                ? 0
                : $deadline->diffInDays(now());

           $spkCutting->update([
                'status_cutting'     => 'Selesai',
                'sisa_hari_terakhir' => $sisaHariTerakhir,
            ]);

            SpkCuttingStatusLog::create([
                'spk_cutting_id' => $spkCutting->id,
                'status'         => 'Selesai',
                'keterangan'     => 'Hasil cutting telah dibuat',
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Data hasil cutting berhasil disimpan',
                'data'    => $hasilCutting->load('bahan')
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();

            Log::error('Validation error in HasilCuttingController@store: ', $e->errors());

            return response()->json([
                'message' => 'Validasi gagal',
                'errors'  => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error in HasilCuttingController@store: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            Log::error('Request data: ', $request->all());

            return response()->json([
                'message' => 'Terjadi kesalahan saat menyimpan data',
                'error'   => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }


    /**
     * Update hasil cutting
     */
    public function update(Request $request, $id)
    {
        try {
            $hasilCutting = HasilCutting::find($id);

            if (!$hasilCutting) {
                return response()->json([
                    'message' => 'Data hasil cutting tidak ditemukan'
                ], 404);
            }

            $validated = $request->validate([
                'spk_cutting_id' => 'required|exists:spk_cutting,id',
                'data_hasil' => 'required|array',
                'data_hasil.*.spk_cutting_bahan_id' => 'required|exists:spk_cutting_bahan,id',
                'data_hasil.*.spk_cutting_bagian_id' => 'required|exists:spk_cutting_bagian,id',
                'data_hasil.*.nama_bagian' => 'nullable|string',
                'data_hasil.*.nama_bahan' => 'nullable|string',
                'data_hasil.*.warna' => 'nullable|string',
                'data_hasil.*.qty' => 'nullable|numeric|min:0',
                'data_hasil.*.jumlah_lembar' => 'required|numeric|min:0',
                'data_hasil.*.jumlah_produk' => 'required|numeric|min:0',
                'data_hasil.*.total_produk' => 'required|numeric|min:0',
                'data_hasil.*.berat_total' => 'required|numeric|min:0',
                'data_hasil.*.berat_per_produk' => 'required|numeric|min:0',
                'data_hasil.*.produk_sku_id' => 'nullable|exists:produk_sku,id',
                'data_acuan' => 'nullable|array',
                'data_acuan.*.warna' => 'required|string',
                'data_acuan.*.berat_acuan' => 'required|numeric|min:0',
                'data_acuan.*.banyak_produk' => 'required|numeric|min:0',
                'data_acuan.*.berat_acuan_per_produk' => 'required|numeric|min:0',
                'status_perbandingan_agregat' => 'nullable|array',
                'status_perbandingan_agregat.*.warna' => 'required_with:status_perbandingan_agregat.*|string',
                'status_perbandingan_agregat.*.status' => 'nullable|string',
                'status_perbandingan_agregat.*.selisih' => 'nullable|numeric|min:0',
                'status_perbandingan_agregat.*.berat_per_produk' => 'nullable|numeric|min:0',
                'status_perbandingan_agregat.*.berat_acuan_per_produk' => 'nullable|numeric|min:0',
            ]);

            DB::beginTransaction();

            // Hitung total produk dari semua data hasil
            $totalProduk = array_sum(array_column($validated['data_hasil'], 'total_produk'));

            // Ambil harga_per_pcs dari spk_cutting
            $spkCutting = \App\Models\SpkCutting::findOrFail($validated['spk_cutting_id']);
            $hargaPerPcs = $spkCutting->harga_per_pcs ?? 0;

            // Hitung total_bayar = harga_per_pcs * total_produk
            $totalBayar = $hargaPerPcs * $totalProduk;

            // Ambil data pertama untuk kolom detail di hasil_cutting (representatif)
            $firstData = $validated['data_hasil'][0] ?? [];

            // Update record HasilCutting utama
            $hasilCuttingData = [
                'spk_cutting_id' => $validated['spk_cutting_id'],
                'spk_cutting_bagian_id' => $firstData['spk_cutting_bagian_id'] ?? null,
                'nama_bagian' => $firstData['nama_bagian'] ?? null,
                'nama_bahan' => $firstData['nama_bahan'] ?? null,
                'warna' => $firstData['warna'] ?? null,
                'qty' => $firstData['qty'] ?? null,
                'total_produk' => $totalProduk,
                'total_bayar' => $totalBayar,
            ];

            // Simpan data acuan sebagai JSON jika ada
            if (!empty($validated['data_acuan'])) {
                $hasilCuttingData['data_acuan'] = json_encode($validated['data_acuan']);
            } else {
                $hasilCuttingData['data_acuan'] = null;
            }

            // Simpan status perbandingan agregat sebagai JSON jika ada
            if (!empty($validated['status_perbandingan_agregat']) && is_array($validated['status_perbandingan_agregat']) && count($validated['status_perbandingan_agregat']) > 0) {
                $hasilCuttingData['status_perbandingan_agregat'] = json_encode($validated['status_perbandingan_agregat']);
                Log::info('Status perbandingan agregat diupdate:', ['data' => $validated['status_perbandingan_agregat']]);
            } else {
                // Set null jika tidak ada data
                $hasilCuttingData['status_perbandingan_agregat'] = null;
                Log::info('Status perbandingan agregat kosong atau tidak ada saat update, diset null');
            }

            $hasilCutting->update($hasilCuttingData);

            // Hapus data bahan lama
            $hasilCutting->bahan()->delete();

            // Simpan data hasil per bahan baru (tanpa kolom detail yang sudah dipindah ke hasil_cutting)
            foreach ($validated['data_hasil'] as $data) {
                HasilCuttingBahan::create([
                    'hasil_cutting_id' => $hasilCutting->id,
                    'spk_cutting_bahan_id' => $data['spk_cutting_bahan_id'],
                    'spk_cutting_bagian_id' => $data['spk_cutting_bagian_id'],
                    'produk_sku_id' => $data['produk_sku_id'] ?? null,
                    'jumlah_lembar' => $data['jumlah_lembar'],
                    'jumlah_produk' => $data['jumlah_produk'],
                    'berat' => $data['berat_total'],
                    'berat_per_produk' => $data['berat_per_produk'],
                    'hasil' => $data['total_produk'],
                    'total_produk' => $data['total_produk'],
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Data hasil cutting berhasil diupdate',
                'data' => $hasilCutting->load('bahan')
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in HasilCuttingController@update: ' . $e->getMessage());
            return response()->json([
                'message' => 'Terjadi kesalahan saat mengupdate data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete hasil cutting
     */
    public function destroy($id)
    {
        try {
            $hasilCutting = HasilCutting::find($id);

            if (!$hasilCutting) {
                return response()->json([
                    'message' => 'Data hasil cutting tidak ditemukan'
                ], 404);
            }

            DB::beginTransaction();

            // Hapus data bahan terlebih dahulu
            $hasilCutting->bahan()->delete();

            // Hapus data hasil cutting
            $hasilCutting->delete();

            DB::commit();

            return response()->json([
                'message' => 'Data hasil cutting berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in HasilCuttingController@destroy: ' . $e->getMessage());
            return response()->json([
                'message' => 'Terjadi kesalahan saat menghapus data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function historyGroupedByProduk(Request $request)
    {
        try {
            $hasilCutting = HasilCutting::with([
                'spkCutting:id,id_spk_cutting,produk_id',
                'spkCutting.produk:id,nama_produk,gambar_produk',
                'bahan.spkCuttingBahan.bagian',
                'bahan.spkCuttingBahan.bahan'
            ])
                ->orderByDesc('created_at')
                ->get();

            $grouped = $hasilCutting->groupBy(function ($item) {
                return optional($item->spkCutting)->produk_id ?? 'unknown';
            });

            $result = [];

            foreach ($grouped as $produkId => $items) {
                $produk = optional($items->first()->spkCutting)->produk;

                // Buat URL gambar produk (jika ada)
                $gambarUrl = null;
                if ($produk && $produk->gambar_produk) {
                    $gambarUrl = asset('storage/' . $produk->gambar_produk);
                }

                $result[] = [
                    'produk_id' => $produkId,
                    'nama_produk' => $produk->nama_produk ?? null,
                    'gambar_produk' => $gambarUrl,
                    'total_history' => $items->count(),
                    'history' => $items->map(function ($item) {

                        $statusAgregat = $item->status_perbandingan_agregat;
                        if (is_string($statusAgregat)) {
                            $statusAgregat = json_decode($statusAgregat, true);
                        }
                        if (!is_array($statusAgregat)) {
                            $statusAgregat = [];
                        }

                        return [
                            'id' => $item->id,
                            'id_spk_cutting' => optional($item->spkCutting)->id_spk_cutting,
                            'created_at' => $item->created_at,
                            'detail' => $item->bahan->map(function ($bahan) use ($statusAgregat) {

                                $spkBahan = $bahan->spkCuttingBahan;

                                $status = null;
                                if ($spkBahan && $spkBahan->warna) {
                                    $found = collect($statusAgregat)
                                        ->firstWhere('warna', $spkBahan->warna);
                                    $status = $found['status'] ?? null;
                                }

                                return [
                                    'nama_bagian' => optional($spkBahan->bagian)->nama_bagian,
                                    'nama_bahan' => optional($spkBahan->bahan)->nama_bahan,
                                    'warna' => $spkBahan->warna ?? null,
                                    'berat' => $bahan->berat,
                                    'qty' => $spkBahan->qty ?? null,
                                    'jumlah_lembar' => $bahan->jumlah_lembar,
                                    'jumlah_produk' => $bahan->jumlah_produk,
                                    'total_produk' => $bahan->hasil,
                                    'berat_per_produk' => $bahan->berat_per_produk,
                                    'status_perbandingan' => $status
                                ];
                            })->values()
                        ];
                    })->values()
                ];
            }

            return response()->json(['data' => $result]);
        } catch (\Exception $e) {
            Log::error('Error historyGroupedByProduk: ' . $e->getMessage());
            return response()->json([
                'message' => 'Terjadi kesalahan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

public function laporanPeriodePerHari(Request $request)
{
    $request->validate([
        'start_date' => 'required|date',
        'end_date'   => 'required|date|after_or_equal:start_date',
    ]);

    $start = Carbon::parse($request->start_date)->startOfDay();
    $end   = Carbon::parse($request->end_date)->endOfDay();

    // 🔥 ambil SEMUA tukang cutting
    $tukangList = TukangCutting::orderBy('nama_tukang_cutting')
        ->pluck('nama_tukang_cutting');

    // data hasil cutting per hari per tukang
    $rawData = HasilCutting::query()
        ->select(
            DB::raw('DATE(hasil_cutting.created_at) as tanggal'),
            'tukang_cutting.nama_tukang_cutting',
            DB::raw('SUM(hasil_cutting.total_produk) as total_pcs')
        )
        ->join('spk_cutting', 'spk_cutting.id', '=', 'hasil_cutting.spk_cutting_id')
        ->join('tukang_cutting', 'tukang_cutting.id', '=', 'spk_cutting.tukang_cutting_id')
        ->whereBetween('hasil_cutting.created_at', [$start, $end])
        ->groupBy('tanggal', 'tukang_cutting.nama_tukang_cutting')
        ->get();

    $result = [];

    for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
        $tanggal = $date->toDateString();

        $row = [
            'tanggal' => $tanggal,
            'total'   => 0,
        ];

        // init semua tukang = 0
        foreach ($tukangList as $nama) {
            $row[$nama] = 0;
        }

        $dataHari = $rawData->where('tanggal', $tanggal);

        foreach ($dataHari as $item) {
            $nama = $item->nama_tukang_cutting;

            $row[$nama] += (int) $item->total_pcs;
            $row['total'] += (int) $item->total_pcs;
        }

        $result[] = $row;
    }
    $grandTotal = [
    'total' => 0,
];

// init per tukang
foreach ($tukangList as $nama) {
    $grandTotal[$nama] = 0;
}

// akumulasi
foreach ($result as $row) {
    $grandTotal['total'] += $row['total'];

    foreach ($tukangList as $nama) {
        $grandTotal[$nama] += $row[$nama];
    }
}


    return response()->json([
        'start_date' => $start->toDateString(),
        'end_date'   => $end->toDateString(),
        'tukang'     => $tukangList,
        'grand_total'  => $grandTotal,
        'data'       => $result,
        
    ]);
}




}
