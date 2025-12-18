<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SpkCutting;
use App\Models\StokBahanKeluar;
use App\Models\SpkCuttingDistribusi;
use App\Models\HasilCutting;
use App\Models\HasilCuttingBahan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


class HasilCuttingController extends Controller
{
    /**
     * Get list data hasil cutting (untuk index)
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 7); // Default 7 items per page

            $hasilCutting = HasilCutting::with([
                'spkCutting:id,id_spk_cutting,produk_id,harga_jasa,satuan_harga,harga_per_pcs',
                'spkCutting.produk:id,nama_produk',
                'bahan'
            ])
                ->orderByDesc('created_at')
                ->paginate($perPage);

            // Format data untuk response
            $formattedData = $hasilCutting->map(function ($item) {
                // Pastikan data_acuan selalu berupa array
                $dataAcuan = $item->data_acuan;
                if (!is_array($dataAcuan)) {
                    $dataAcuan = is_string($dataAcuan) ? json_decode($dataAcuan, true) : [];
                    if (!is_array($dataAcuan)) {
                        $dataAcuan = [];
                    }
                }

                // Ambil total produk dari hasil_cutting (sudah disimpan di tabel utama)
                $totalProduk = $item->total_produk ?? $item->bahan->sum('total_produk') ?? $item->bahan->sum('hasil') ?? 0;

                // Hitung total_bayar jika masih 0 atau null
                $totalBayar = $item->total_bayar ?? 0;
                if (($totalBayar == 0 || $totalBayar == null) && $item->spkCutting) {
                    $spkCutting = $item->spkCutting;
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

                    $totalBayar = $hargaPerPcs * $totalProduk;

                    Log::info('Menghitung ulang total_bayar untuk hasil_cutting ID: ' . $item->id, [
                        'harga_per_pcs' => $hargaPerPcs,
                        'total_produk' => $totalProduk,
                        'total_bayar' => $totalBayar,
                        'harga_jasa' => $spkCutting->harga_jasa ?? null,
                        'satuan_harga' => $spkCutting->satuan_harga ?? null
                    ]);

                    // Update database jika total_bayar masih 0 atau null
                    if ($item->total_bayar == null || $item->total_bayar == 0) {
                        $item->update(['total_bayar' => $totalBayar]);
                    }
                }

                return [
                    'id' => $item->id,
                    'spk_cutting_id' => $item->spk_cutting_id,
                    'id_spk_cutting' => $item->spkCutting->id_spk_cutting ?? null,
                    'nama_produk' => $item->spkCutting->produk->nama_produk ?? null,
                    'total_produk' => $totalProduk,
                    'total_bayar' => $totalBayar,
                    'created_at' => $item->created_at,
                ];
            });

            // ==========================
            // Stat target mingguan & harian (dengan custom period)
            // ==========================
            // Weekly: default Senin–Sabtu (startOfWeek sampai startOfWeek + 5 hari)
            $weeklyStartInput = $request->input('weekly_start');
            $weeklyEndInput = $request->input('weekly_end');

            if ($weeklyStartInput) {
                $startOfWeek = Carbon::parse($weeklyStartInput)->startOfDay();
            } else {
                $startOfWeek = Carbon::today()->startOfWeek(); // Senin
            }

            if ($weeklyEndInput) {
                $endOfWeek = Carbon::parse($weeklyEndInput)->endOfDay();
            } else {
                // Default: Sabtu (Senin + 5 hari)
                $endOfWeek = $startOfWeek->copy()->addDays(5)->endOfDay();
            }

            // Daily: default hari ini, bisa di-custom via daily_date
            $dailyDateInput = $request->input('daily_date');
            $today = $dailyDateInput ? Carbon::parse($dailyDateInput)->startOfDay() : Carbon::today();

            $weeklyTarget = 50000;
            $dailyTarget = 7143;

            // Asumsi kolom total_produk sudah diisi saat simpan hasil cutting
            $weeklyTotal = HasilCutting::whereBetween('created_at', [$startOfWeek, $endOfWeek])
                ->sum('total_produk');

            $dailyTotal = HasilCutting::whereDate('created_at', $today->toDateString())
                ->sum('total_produk');

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
                'produk:id,nama_produk'
            ])->find($spkCuttingId);

            if (!$spkCutting) {
                Log::warning("SPK Cutting tidak ditemukan dengan ID: " . $spkCuttingId);
                Log::warning("Mencoba mencari dengan id_spk_cutting...");

                // Coba cari dengan id_spk_cutting jika tidak ditemukan dengan ID
                $spkCutting = SpkCutting::with([
                    'bagian.bahan.bahan',
                    'produk:id,nama_produk'
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
                // Coba load relasi distribusi
                if (method_exists($hasilCutting, 'distribusi')) {
                    $hasilCutting->load('distribusi');
                    if ($hasilCutting->distribusi && $hasilCutting->distribusi->count() > 0) {
                        $distribusi = $hasilCutting->distribusi->toArray();
                    }
                } else {
                    // Jika relasi belum ada, coba query langsung
                    $distribusiData = \App\Models\SpkCuttingDistribusi::where('hasil_cutting_id', $hasilCutting->id)->get();
                    if ($distribusiData && $distribusiData->count() > 0) {
                        $distribusi = $distribusiData->toArray();
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
                    ];
                })->toArray(),
                'bahan' => $hasilCutting->bahan->map(function ($bahan) {
                    $spkBahan = $bahan->spkCuttingBahan;
                    return [
                        'id' => $bahan->id,
                        'spk_cutting_bahan_id' => $bahan->spk_cutting_bahan_id,
                        'spk_cutting_bagian_id' => $bahan->spk_cutting_bagian_id,
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

                'distribusi_seri' => 'required|array|min:1',

                'distribusi_seri.*.jumlah_produk' => 'required|integer|min:1',


            ]);

            DB::beginTransaction();


            // Hitung total produk dari semua data hasil
            $totalProduk = array_sum(array_column($validated['data_hasil'], 'total_produk'));
            $totalDistribusi = array_sum(array_column($validated['distribusi_seri'], 'jumlah_produk'));

            if ($totalDistribusi !== $totalProduk) {
                throw new \Exception(
                    "Total distribusi ({$totalDistribusi}) harus sama dengan total hasil cutting ({$totalProduk})"
                );
            }
            // Ambil harga_per_pcs dari spk_cutting
            $spkCutting = \App\Models\SpkCutting::findOrFail($validated['spk_cutting_id']);
            $hargaPerPcs = $spkCutting->harga_per_pcs ?? 0;

            // Hitung total_bayar = harga_per_pcs * total_produkasd
            $totalBayar = $hargaPerPcs * $totalProduk;

            // Ambil data pertama untuk kolom detail di hasil_cutting (representatif)
            $firstData = $validated['data_hasil'][0] ?? [];

            // Buat record HasilCutting utama
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
            }

            // Simpan status perbandingan agregat sebagai JSON jika ada
            if (!empty($validated['status_perbandingan_agregat']) && is_array($validated['status_perbandingan_agregat']) && count($validated['status_perbandingan_agregat']) > 0) {
                $hasilCuttingData['status_perbandingan_agregat'] = json_encode($validated['status_perbandingan_agregat']);
                Log::info('Status perbandingan agregat disimpan:', ['data' => $validated['status_perbandingan_agregat']]);
            } else {
                // Set null jika tidak ada data
                $hasilCuttingData['status_perbandingan_agregat'] = null;
                Log::info('Status perbandingan agregat kosong atau tidak ada, diset null');
            }

            $hasilCutting = HasilCutting::create($hasilCuttingData);

            $alphabet = range('A', 'Z');

            foreach ($validated['distribusi_seri'] as $index => $seri) {
                $kodeSeri = $spkCutting->id_spk_cutting . $alphabet[$index];

                SpkCuttingDistribusi::create([
                    'spk_cutting_id'   => $validated['spk_cutting_id'],
                    'hasil_cutting_id' => $hasilCutting->id,

                    'kode_seri'        => $kodeSeri, // generate otomatis
                    'jumlah_produk'    => $seri['jumlah_produk'],
                    'status'           => 'draft',
                ]);
            }



            // Simpan data hasil per bahan (tanpa kolom detail yang sudah dipindah ke hasil_cutting)
            foreach ($validated['data_hasil'] as $data) {
                HasilCuttingBahan::create([
                    'hasil_cutting_id' => $hasilCutting->id,
                    'spk_cutting_bahan_id' => $data['spk_cutting_bahan_id'],
                    'spk_cutting_bagian_id' => $data['spk_cutting_bagian_id'],
                    'jumlah_lembar' => $data['jumlah_lembar'],
                    'jumlah_produk' => $data['jumlah_produk'],
                    'berat' => $data['berat_total'],
                    'berat_per_produk' => $data['berat_per_produk'],
                    'hasil' => $data['total_produk'],
                ]);
            }
            $deadline = Carbon::parse($spkCutting->tanggal_batas_kirim);

            $sisaHariTerakhir = $deadline->isPast()
                ? 0
                : $deadline->diffInDays(now());

            $spkCutting->update([
                'status_cutting' => 'Completed',
                'sisa_hari_terakhir' => $sisaHariTerakhir,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Data hasil cutting berhasil disimpan',
                'data' => $hasilCutting->load('bahan')
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in HasilCuttingController@store: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            Log::error('Request data: ' . json_encode($request->all()));
            return response()->json([
                'message' => 'Terjadi kesalahan saat menyimpan data',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
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
}
