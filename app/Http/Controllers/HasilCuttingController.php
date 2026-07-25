<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SpkCutting;
use App\Models\StokBahanKeluar;
use App\Models\SpkCuttingDistribusi;
use App\Models\SpkCuttingDistribusiDetail;
use App\Models\HasilCutting;
use App\Models\ProductList;
use App\Models\TukangCutting;
use App\Models\HasilCuttingBahan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\SpkCuttingStatusLog;


class HasilCuttingController extends Controller
{
    private const JENIS_HASIL_UTAMA = 'utama';
    private const JENIS_HASIL_KOMBINASI = 'kombinasi';

    private function formatProductListSku(ProductList $sku): array
    {
        return [
            'id' => $sku->id,
            'product_list_id' => $sku->id,
            'sku' => $sku->sku_name,
            'sku_name' => $sku->sku_name,
            'warna' => $sku->product_colour,
            'product_colour' => $sku->product_colour,
            'ukuran' => $sku->product_size,
            'product_size' => $sku->product_size,
            'nama_produk' => $sku->product,
            'product' => $sku->product,
        ];
    }

    private function normalizeJenisHasil(?string $jenis): string
    {
        $normalized = strtolower(trim((string) $jenis));

        return $normalized === self::JENIS_HASIL_KOMBINASI
            ? self::JENIS_HASIL_KOMBINASI
            : self::JENIS_HASIL_UTAMA;
    }

    private function isKombinasiBagian(?string $namaBagian): bool
    {
        $name = strtolower(trim((string) $namaBagian));

        return str_contains($name, 'kombinasi') || str_contains($name, 'combinasi') || str_contains($name, 'kombin');
    }

    private function isAksesorisBagian(?string $namaBagian): bool
    {
        $name = strtolower(trim((string) $namaBagian));

        return str_contains($name, 'aksesor') || str_contains($name, 'accessor');
    }

    private function shouldIncludeBagianForJenis(?string $namaBagian, string $jenisHasil): bool
    {
        if ($jenisHasil === self::JENIS_HASIL_KOMBINASI) {
            return $this->isKombinasiBagian($namaBagian);
        }

        return !$this->isKombinasiBagian($namaBagian) && !$this->isAksesorisBagian($namaBagian);
    }

    private function getDistribusiSkuId(?SpkCuttingDistribusi $distribusi): ?int
    {
        if (!$distribusi) {
            return null;
        }

        $detail = $distribusi->relationLoaded('detail')
            ? $distribusi->detail->first()
            : $distribusi->detail()->first();

        return $detail?->product_list_id ?: $detail?->produk_sku_id;
    }

    private function updateDistribusiFromHasil(SpkCuttingDistribusi $distribusi, HasilCutting $hasilCutting, int $totalProduk, array $dataHasil): void
    {
        $distribusi->update([
            'hasil_cutting_id' => $hasilCutting->id,
            'jumlah_produk' => $totalProduk,
            'status' => 'draft',
        ]);

        // Group data_hasil by color to get accurate totals and SKU per color
        $dataPerWarna = [];
        $skuPerWarna = [];
        foreach ($dataHasil as $data) {
            $warna = $data['warna'] ?? 'Unknown';
            if (!isset($dataPerWarna[$warna])) {
                $dataPerWarna[$warna] = 0;
            }
            $dataPerWarna[$warna] += $data['total_produk'] ?? 0;
            if (!empty($data['produk_sku_id'])) {
                $skuPerWarna[$warna] = $data['produk_sku_id'];
            }
        }

        // Delete old details and recreate with correct distribution
        $distribusi->detail()->delete();

        foreach ($dataPerWarna as $warna => $jumlah) {
            if ($jumlah > 0) {
                SpkCuttingDistribusiDetail::create([
                    'spk_cutting_distribusi_id' => $distribusi->id,
                    'warna' => $warna,
                    'jumlah_produk' => $jumlah,
                    'produk_sku_id' => null,
                    'product_list_id' => $skuPerWarna[$warna] ?? null,
                ]);
            }
        }
    }

    /**
     * Get list data hasil cutting (untuk index)
     */
    public function index(Request $request)
    {
        try {
            // Batasi per_page agar query tetap stabil untuk data besar
            $perPage = max(1, min((int) $request->input('per_page', 50), 100));

            $query = HasilCutting::query()
                ->select([
                    'id',
                    'spk_cutting_id',
                    'spk_cutting_distribusi_id',
                    'jenis_hasil',
                    'total_produk',
                    'total_bayar',
                    'tanggal_potong',
                    'created_at',
                ])
                ->withSum('bahan as bahan_sum_jumlah_produk', 'jumlah_produk')
                ->withSum('bahan as bahan_sum_hasil', 'hasil')
                ->with([
                'spkCutting:id,id_spk_cutting,produk_id,product_list_id,harga_jasa,satuan_harga,harga_per_pcs,tukang_cutting_id,jumlah_asumsi_produk',
                'spkCutting.produk:id,nama_produk',
                'spkCutting.productList:id,product,product_group',
                'spkCutting.tukangCutting:id,nama_tukang_cutting',
                'spkCuttingDistribusi:id,kode_seri,jumlah_produk,status',
                'spkCuttingDistribusi.detail.productListSku:id,product_size',
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
                            ->orWhereHas('productList', function ($p) use ($searchTerm) {
                                $p->where('product', 'like', "%{$searchTerm}%")
                                    ->orWhere('product_group', 'like', "%{$searchTerm}%");
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
                    'spk_cutting_distribusi_id' => $item->spk_cutting_distribusi_id,
                    'kode_distribusi' => $item->spkCuttingDistribusi->kode_seri ?? null,
                    'jenis_hasil' => $item->jenis_hasil ?? self::JENIS_HASIL_UTAMA,
                    'id_spk_cutting' => $item->spkCutting->id_spk_cutting ?? null,
                    'nama_produk' => $item->spkCutting->productList->product_group ?? $item->spkCutting->productList->product ?? $item->spkCutting->produk->nama_produk ?? null,
                    'tukang_cutting_id' => $item->spkCutting->tukang_cutting_id ?? null,
                    'nama_tukang_cutting' => $item->spkCutting->tukangCutting->nama_tukang_cutting ?? null,
                    'estimasi' => (int) ($item->spkCutting->jumlah_asumsi_produk ?? 0),
                    'size' => call_user_func(function() use ($item) {
                        $distribusi = $item->spkCuttingDistribusi;
                        $sizes = [];
                        if ($distribusi && $distribusi->relationLoaded('detail')) {
                            foreach ($distribusi->detail as $detail) {
                                if ($detail->productListSku && $detail->productListSku->product_size) {
                                    $sizes[] = $detail->productListSku->product_size;
                                }
                            }
                        }
                        return !empty($sizes) ? implode(', ', array_unique($sizes)) : '-';
                    }),
                    'total_produk' => $totalProduk,
                    'total_bayar' => $totalBayar,
                    'tanggal_potong' => $item->tanggal_potong,
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

            $monthlyTarget = 250000;
            $weeklyTarget = 50000;
            $dailyTarget = 8333;

            $startOfMonth = Carbon::now()->startOfMonth();
            $endOfMonth = Carbon::now()->endOfMonth();

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
                        "COALESCE(SUM(CASE WHEN created_at BETWEEN ? AND ? THEN total_produk ELSE 0 END), 0) AS monthly_total",
                        [$startOfMonth->toDateTimeString(), $endOfMonth->toDateTimeString()]
                    )
                    ->selectRaw(
                        "COALESCE(SUM(CASE WHEN created_at BETWEEN ? AND ? THEN total_produk ELSE 0 END), 0) AS weekly_total",
                        [$startOfWeek->toDateTimeString(), $endOfWeek->toDateTimeString()]
                    )
                    ->selectRaw(
                        "COALESCE(SUM(CASE WHEN DATE(created_at) = ? THEN total_produk ELSE 0 END), 0) AS daily_total",
                        [$today->toDateString()]
                    )
                    ->first();

                $monthlyTotal = (int) ($statsRow->monthly_total ?? 0);
                $weeklyTotal = (int) ($statsRow->weekly_total ?? 0);
                $dailyTotal = (int) ($statsRow->daily_total ?? 0);
            }

            $monthlyRemaining = max(0, $monthlyTarget - $monthlyTotal);
            $weeklyRemaining = max(0, $weeklyTarget - $weeklyTotal);
            $dailyRemaining = max(0, $dailyTarget - $dailyTotal);

            return response()->json([
                'data' => $formattedData,
                'current_page' => $hasilCutting->currentPage(),
                'last_page' => $hasilCutting->lastPage(),
                'total' => $hasilCutting->total(),
                'stats' => [
                    'monthly_target' => $monthlyTarget,
                    'monthly_total' => $monthlyTotal,
                    'monthly_remaining' => $monthlyRemaining,
                    'weekly_target' => $weeklyTarget,
                    'weekly_total' => $weeklyTotal,
                    'weekly_remaining' => $weeklyRemaining,
                    'daily_target' => $dailyTarget,
                    'daily_total' => $dailyTotal,
                    'daily_remaining' => $dailyRemaining,
                    'month_start' => $startOfMonth->toDateString(),
                    'month_end' => $endOfMonth->toDateString(),
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
            $jenisHasil = $this->normalizeJenisHasil($request->input('jenis_hasil'));
            $distribusiInput = $request->input('spk_cutting_distribusi_id');
            $kodeSeriInput = trim((string) $request->input('kode_seri', ''));
            $selectedDistribusi = null;

            if ($distribusiInput || $kodeSeriInput !== '') {
                $selectedDistribusi = SpkCuttingDistribusi::with([
                    'detail.productListSku:id,product,sku_name,product_colour,product_size',
                    'spkCutting',
                ])
                    ->when($distribusiInput, fn ($query) => $query->where('id', $distribusiInput))
                    ->when(!$distribusiInput && $kodeSeriInput !== '', fn ($query) => $query->where('kode_seri', $kodeSeriInput))
                    ->first();

                if (!$selectedDistribusi) {
                    return response()->json([
                        'message' => 'Distribusi cutting tidak ditemukan',
                    ], 404);
                }

                $spkCuttingId = $selectedDistribusi->spk_cutting_id;
            }

            if (!$spkCuttingId) {
                return response()->json([
                    'message' => 'SPK Cutting ID atau kode distribusi diperlukan'
                ], 400);
            }

            // Ambil detail SPK Cutting dengan relasi
            $spkCutting = SpkCutting::with([
                'bagian.bahan.bahan',
                'bagian.bahan.skus',
                'produk:id,nama_produk',
                'productList:id,product,product_group',
                'productListSkus:id,product,sku_name,product_colour,product_size',
                'skus.produk:id,nama_produk'
            ])->find($spkCuttingId);

            if (!$spkCutting) {
                Log::warning("SPK Cutting tidak ditemukan dengan ID: " . $spkCuttingId);
                Log::warning("Mencoba mencari dengan id_spk_cutting...");

                // Coba cari dengan id_spk_cutting jika tidak ditemukan dengan ID
                $spkCutting = SpkCutting::with([
                    'bagian.bahan.bahan',
                    'bagian.bahan.skus',
                    'produk:id,nama_produk',
                    'productList:id,product,product_group',
                    'productListSkus:id,product,sku_name,product_colour,product_size',
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

            // Hitung jumlah distribusi untuk membagi berat proporsional per suffix
            $distribusiCount = \App\Models\SpkCuttingDistribusi::where('spk_cutting_id', $spkCutting->id)->count();
            $distribusiCount = max(1, $distribusiCount); // Cegah division by zero

            // Ambil data berat dari stok_bahan_keluar yang sudah di-scan
            $stokBahanKeluar = StokBahanKeluar::where('spk_cutting_id', $spkCutting->id)
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
                    if (!$this->shouldIncludeBagianForJenis($bagian->nama_bagian, $jenisHasil)) {
                        continue;
                    }

                    foreach ($bagian->bahan as $bahan) {
                        $beratScanned = $beratPerBahan[$bahan->id] ?? 0;

                        $detailData[] = [
                            'spk_cutting_bahan_id' => $bahan->id,
                            'spk_cutting_bagian_id' => $bagian->id,
                            'nama_bagian' => $bagian->nama_bagian,
                            'bahan_id' => $bahan->bahan_id,
                            'nama_bahan' => $bahan->bahan->nama_bahan ?? null,
                            'warna' => $bahan->warna,
                            'qty' => $bahan->qty !== null ? round($bahan->qty / $distribusiCount, 2) : null,
                            'produk_sku_id' => $this->getDistribusiSkuId($selectedDistribusi),
                            'product_list_id' => $this->getDistribusiSkuId($selectedDistribusi),
                            'berat_spk' => $bahan->berat, // Berat dari SPK Cutting (rencana)
                            'berat_scanned' => round($beratScanned / $distribusiCount, 2), // Berat yang sudah di-scan keluar dibagi suffix
                            'skus' => $bahan->skus ? $bahan->skus->map(function ($sku) {
                                return ['id' => $sku->id, 'sku_name' => $sku->sku_name];
                            })->toArray() : [],
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
                                'skus' => [],
                            ];
                        } else {
                            $bagian = $spkBahan->bagian;
                            $bahan = $spkBahan->bahan;
                            $uniqueData[$bahanId] = [
                                'spk_cutting_bahan_id' => $bahanId,
                                'spk_cutting_bagian_id' => $bagian->id ?? null,
                                'nama_bagian' => $bagian->nama_bagian ?? null,
                                'bahan_id' => $bahan->id ?? null,
                                'nama_bahan' => $bahan->nama_bahan ?? null,
                                'warna' => $spkBahan->warna ?? null,
                                'qty' => $spkBahan->qty !== null ? round($spkBahan->qty / $distribusiCount, 2) : null,
                                'berat_spk' => $spkBahan->berat ?? 0,
                                'berat_scanned' => $beratScanned,
                                'skus' => $spkBahan->skus ? $spkBahan->skus->map(function ($sku) {
                                    return ['id' => $sku->id, 'sku_name' => $sku->sku_name];
                                })->toArray() : [],
                            ];
                        }
                    }

                    // Tambahkan berat
                    $uniqueData[$bahanId]['berat_scanned'] += $item->berat ?? 0;
                }

                // Konversi ke array dan bulatkan berat serta qty
                foreach ($uniqueData as $data) {
                    $data['berat_scanned'] = round($data['berat_scanned'] / $distribusiCount, 2);
                    if ($data['qty'] !== null) {
                        $data['qty'] = round($data['qty'] / $distribusiCount, 2);
                    }
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
                    ->where('stok_bahan_keluar.spk_cutting_id', $spkCutting->id)
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
                        'qty' => $row->qty !== null ? round($row->qty / $distribusiCount, 2) : null,
                        'berat_spk' => $row->berat_spk ?? 0,
                        'berat_scanned' => round(($row->berat_scanned ?? 0) / $distribusiCount, 2),
                    ];
                }

                Log::info("Data dari query raw: " . count($detailData) . " item");
            }

            Log::info("Total detail data: " . count($detailData));

            // Fetch all distributions for this SPK
            $distribusiList = \App\Models\SpkCuttingDistribusi::with([
                'detail.productListSku:id,product,sku_name,product_colour,product_size',
            ])->where('spk_cutting_id', $spkCutting->id)->get();

            // Pastikan selalu mengembalikan response, meskipun detail kosong
            return response()->json([
                'spk_cutting' => [
                    'id' => $spkCutting->id,
                    'id_spk_cutting' => $spkCutting->id_spk_cutting,
                    'nama_produk' => $spkCutting->productList->product_group ?? $spkCutting->productList->product ?? $spkCutting->produk->nama_produk ?? null,
                ],
                'jenis_hasil' => $jenisHasil,
                'distribusi' => $selectedDistribusi ? [
                    'id' => $selectedDistribusi->id,
                    'kode_seri' => $selectedDistribusi->kode_seri,
                    'jumlah_produk' => $selectedDistribusi->jumlah_produk,
                    'status' => $selectedDistribusi->status,
                    'sku' => $selectedDistribusi->detail->first()?->productListSku
                        ? $this->formatProductListSku($selectedDistribusi->detail->first()->productListSku)
                        : null,
                    'skus' => $selectedDistribusi->detail->map(function ($d) {
                        return $d->productListSku ? $this->formatProductListSku($d->productListSku) : null;
                    })->filter()->values()->toArray(),
                ] : null,
                'distribusi_list' => $distribusiList->map(function ($dist) {
                    return [
                        'id' => $dist->id,
                        'kode_seri' => $dist->kode_seri,
                        'jumlah_produk' => $dist->jumlah_produk,
                        'status' => $dist->status,
                        'sku' => $dist->detail->first()?->productListSku
                            ? $this->formatProductListSku($dist->detail->first()->productListSku)
                            : null,
                        'skus' => $dist->detail->map(function ($d) {
                            return $d->productListSku ? $this->formatProductListSku($d->productListSku) : null;
                        })->filter()->values()->toArray(),
                    ];
                })->toArray(),
                'skus' => $spkCutting->productListSkus->isNotEmpty()
                    ? $spkCutting->productListSkus->map(fn ($sku) => $this->formatProductListSku($sku))->values()->toArray()
                    : $spkCutting->skus->map(function ($sku) {
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
                'spkCutting:id,id_spk_cutting,produk_id,product_list_id,harga_jasa,satuan_harga,harga_per_pcs',
                'spkCutting.produk:id,nama_produk',
                'spkCutting.productList:id,product,product_group',
                'spkCuttingDistribusi:id,kode_seri,jumlah_produk,status',
                'bahan.spkCuttingBahan.bagian',
                'bahan.spkCuttingBahan.bahan',
                'bahan.productListSku:id,product,sku_name,product_colour,product_size'
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
                    $hasilCutting->load('distribusi.detail.productListSku:id,product,sku_name,product_colour,product_size');
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
                                        'produk_sku_id' => $d->product_list_id ?? $d->produk_sku_id,
                                        'product_list_id' => $d->product_list_id,
                                        'sku' => $d->productListSku ? $this->formatProductListSku($d->productListSku) : null,
                                    ];
                                })->toArray(),
                            ];
                        })->toArray();
                    }
                } else {
                    // Jika relasi belum ada, coba query langsung
                    $distribusiData = \App\Models\SpkCuttingDistribusi::with('detail.productListSku:id,product,sku_name,product_colour,product_size')
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
                                        'produk_sku_id' => $d->product_list_id ?? $d->produk_sku_id,
                                        'product_list_id' => $d->product_list_id,
                                        'sku' => $d->productListSku ? $this->formatProductListSku($d->productListSku) : null,
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
                'spk_cutting_distribusi_id' => $hasilCutting->spk_cutting_distribusi_id,
                'kode_distribusi' => $hasilCutting->spkCuttingDistribusi->kode_seri ?? null,
                'jenis_hasil' => $hasilCutting->jenis_hasil ?? self::JENIS_HASIL_UTAMA,
                'id_spk_cutting' => $hasilCutting->spkCutting->id_spk_cutting ?? null,
                'nama_produk' => $hasilCutting->spkCutting->productList->product_group ?? $hasilCutting->spkCutting->productList->product ?? $hasilCutting->spkCutting->produk->nama_produk ?? null,
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
                        'produk_sku_id' => $bahan->product_list_id ?? $bahan->produk_sku_id,
                        'product_list_id' => $bahan->product_list_id,
                        'sku' => $bahan->productListSku ? $this->formatProductListSku($bahan->productListSku) : null,
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
                'tanggal_potong' => $hasilCutting->tanggal_potong,
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
                'spk_cutting_distribusi_id' => 'nullable|exists:spk_cutting_distribusi,id',
                'jenis_hasil' => 'nullable|in:utama,kombinasi',
                'tanggal_potong' => 'nullable|date',

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
                'data_hasil.*.produk_sku_id' => 'nullable|integer|exists:product_lists,id',

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
                'distribusi_seri.*.detail.*.produk_sku_id' => 'nullable|integer|exists:product_lists,id',
            ]);

            DB::beginTransaction();

            /**
             * ===============================
             * HITUNG TOTAL PRODUK
             * ===============================
             */
            $totalProduk = array_sum(array_column($validated['data_hasil'], 'total_produk'));
            $jenisHasil = $this->normalizeJenisHasil($validated['jenis_hasil'] ?? null);
            $selectedDistribusi = null;

            if (!empty($validated['spk_cutting_distribusi_id'])) {
                $selectedDistribusi = SpkCuttingDistribusi::with('detail.productListSku')
                    ->findOrFail($validated['spk_cutting_distribusi_id']);

                if ((int) $selectedDistribusi->spk_cutting_id !== (int) $validated['spk_cutting_id']) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'spk_cutting_distribusi_id' => ['Distribusi tidak sesuai dengan SPK Cutting yang dipilih.'],
                    ]);
                }

                $duplicate = HasilCutting::query()
                    ->where('spk_cutting_distribusi_id', $selectedDistribusi->id)
                    ->where('jenis_hasil', $jenisHasil)
                    ->exists();

                if ($duplicate) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'spk_cutting_distribusi_id' => ['Hasil cutting untuk distribusi dan jenis bahan ini sudah pernah dibuat. Gunakan edit jika perlu mengubah data.'],
                    ]);
                }
            }

            /**
             * ===============================
             * DISTRIBUSI SERI
             * ===============================
             */
            if ($selectedDistribusi) {
                $distribusiSeri = [];
            } elseif (!empty($validated['distribusi_seri'])) {
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
                'spk_cutting_distribusi_id' => $selectedDistribusi?->id,
                'jenis_hasil'           => $jenisHasil,
                'spk_cutting_bagian_id' => $firstData['spk_cutting_bagian_id'] ?? null,
                'nama_bagian'           => $firstData['nama_bagian'] ?? null,
                'nama_bahan'            => $firstData['nama_bahan'] ?? null,
                'warna'                 => $firstData['warna'] ?? null,
                'qty'                   => $firstData['qty'] ?? null,
                'total_produk'          => $totalProduk,
                'total_bayar'           => $totalBayar,
                'tanggal_potong'        => $validated['tanggal_potong'] ?? null,
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

            try {
                $hasilCutting = HasilCutting::create($hasilCuttingData);
            } catch (\Illuminate\Database\QueryException $e) {
                // Unique index hasil_cutting_distribusi_jenis_unique menangkap race
                // condition: dua request bersamaan bisa lolos exists()-check di atas
                // sebelum salah satunya sempat commit. Constraint DB adalah jaring
                // pengaman terakhir terhadap duplikat, bukan cuma exists()-check.
                if ((int) $e->getCode() === 23000) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'spk_cutting_distribusi_id' => ['Hasil cutting untuk distribusi dan jenis bahan ini sudah pernah dibuat. Gunakan edit jika perlu mengubah data.'],
                    ]);
                }

                throw $e;
            }

            /**
             * ===============================
             * SIMPAN DISTRIBUSI SERI
             * ===============================
             */
            if ($selectedDistribusi) {
                $this->updateDistribusiFromHasil($selectedDistribusi, $hasilCutting, (int) $totalProduk, $validated['data_hasil']);
            } else {
            $alphabet = range('A', 'Z');
            
            // Ambil semua kode_seri yang sudah ada untuk SPK ini
            $existingKodeSeri = SpkCuttingDistribusi::where('spk_cutting_id', $validated['spk_cutting_id'])
                ->pluck('kode_seri')
                ->toArray();

            // Group data_hasil by color to find matching SKU for each color
            $skuPerWarna = [];
            foreach ($validated['data_hasil'] as $dataHasil) {
                if (!empty($dataHasil['warna']) && !empty($dataHasil['produk_sku_id'])) {
                    $skuPerWarna[$dataHasil['warna']] = $dataHasil['produk_sku_id'];
                }
            }

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
                            'produk_sku_id'             => null,
                            'product_list_id'           => $detail['produk_sku_id'] ?? $skuPerWarna[$detail['warna']] ?? null,
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
                        // Gunakan total_produk dari data_hasil
                        $dataPerWarna[$warna] += $dataHasil['total_produk'] ?? 0;
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
                                    'product_list_id'          => $skuPerWarna[$warna] ?? null,
                                ]);
                            }
                        }
                    } else {
                        // Jika tidak ada data_hasil atau total 0, buat satu record default
                        Log::warning("Tidak ada data_hasil untuk membuat detail distribusi seri {$kodeSeri}");
                        
                        $firstSkuId = null;
                        foreach ($validated['data_hasil'] as $dataHasil) {
                            if (!empty($dataHasil['produk_sku_id'])) {
                                $firstSkuId = $dataHasil['produk_sku_id'];
                                break;
                            }
                        }

                        SpkCuttingDistribusiDetail::create([
                            'spk_cutting_distribusi_id' => $distribusi->id,
                            'warna'                    => 'Unknown',
                            'jumlah_produk'             => $seri['jumlah_produk'],
                            'produk_sku_id'            => null,
                            'product_list_id'          => $firstSkuId,
                        ]);
                    }
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
                    'produk_sku_id'         => null,
                    'product_list_id'       => $data['produk_sku_id'] ?? $this->getDistribusiSkuId($selectedDistribusi),
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
                'spk_cutting_distribusi_id' => 'nullable|exists:spk_cutting_distribusi,id',
                'jenis_hasil' => 'nullable|in:utama,kombinasi',
                'tanggal_potong' => 'nullable|date',
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
                'data_hasil.*.produk_sku_id' => 'nullable|integer|exists:product_lists,id',
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
            $jenisHasil = $this->normalizeJenisHasil($validated['jenis_hasil'] ?? $hasilCutting->jenis_hasil ?? null);
            $selectedDistribusi = null;

            if (!empty($validated['spk_cutting_distribusi_id'])) {
                $selectedDistribusi = SpkCuttingDistribusi::with('detail.productListSku')
                    ->findOrFail($validated['spk_cutting_distribusi_id']);

                if ((int) $selectedDistribusi->spk_cutting_id !== (int) $validated['spk_cutting_id']) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'spk_cutting_distribusi_id' => ['Distribusi tidak sesuai dengan SPK Cutting yang dipilih.'],
                    ]);
                }

                $duplicate = HasilCutting::query()
                    ->where('spk_cutting_distribusi_id', $selectedDistribusi->id)
                    ->where('jenis_hasil', $jenisHasil)
                    ->where('id', '!=', $hasilCutting->id)
                    ->exists();

                if ($duplicate) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'spk_cutting_distribusi_id' => ['Hasil cutting untuk distribusi dan jenis bahan ini sudah dipakai data lain.'],
                    ]);
                }
            }

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
                'spk_cutting_distribusi_id' => $selectedDistribusi?->id,
                'jenis_hasil' => $jenisHasil,
                'spk_cutting_bagian_id' => $firstData['spk_cutting_bagian_id'] ?? null,
                'nama_bagian' => $firstData['nama_bagian'] ?? null,
                'nama_bahan' => $firstData['nama_bahan'] ?? null,
                'warna' => $firstData['warna'] ?? null,
                'qty' => $firstData['qty'] ?? null,
                'total_produk' => $totalProduk,
                'total_bayar' => $totalBayar,
                'tanggal_potong' => $validated['tanggal_potong'] ?? null,
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
                    'produk_sku_id' => null,
                    'product_list_id' => $data['produk_sku_id'] ?? $this->getDistribusiSkuId($selectedDistribusi),
                    'jumlah_lembar' => $data['jumlah_lembar'],
                    'jumlah_produk' => $data['jumlah_produk'],
                    'berat' => $data['berat_total'],
                    'berat_per_produk' => $data['berat_per_produk'],
                    'hasil' => $data['total_produk'],
                ]);
            }

            if ($selectedDistribusi) {
                $this->updateDistribusiFromHasil($selectedDistribusi, $hasilCutting, (int) $totalProduk, $validated['data_hasil']);
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

            $spkCuttingId = $hasilCutting->spk_cutting_id;

            // Lepaskan distribusi dari hasil cutting ini agar tidak ikut terhapus
            // karena constraint ON DELETE CASCADE, sehingga seri bisa diinput lagi
            \App\Models\SpkCuttingDistribusi::where('hasil_cutting_id', $hasilCutting->id)
                ->update([
                    'hasil_cutting_id' => null,
                    'status' => 'draft'
                ]);

            // Hapus data bahan terlebih dahulu
            $hasilCutting->bahan()->delete();

            // Hapus data hasil cutting
            $hasilCutting->delete();

            // Cek apakah masih ada hasil cutting lain untuk SPK ini
            $sisaHasilCutting = \App\Models\HasilCutting::where('spk_cutting_id', $spkCuttingId)->exists();
            
            // Jika tidak ada lagi hasil cutting, kembalikan status SPK ke status
            // sebelum ada hasil cutting sama sekali. Status_cutting hanya mengenal
            // 'belum_diambil'/'sudah_diambil'/'selesai' (lihat SpkCuttingController) --
            // string lain seperti "Proses Cutting" tidak dikenali oleh filter/rekap
            // status_cutting di tempat lain, jadi SPK ini jadi macet di status yang
            // tidak bisa ditransisikan lagi lewat alur normal.
            if (!$sisaHasilCutting) {
                $spkCutting = \App\Models\SpkCutting::find($spkCuttingId);
                if ($spkCutting) {
                    $spkCutting->update([
                        'status_cutting' => 'belum_diambil'
                    ]);

                    \App\Models\SpkCuttingStatusLog::create([
                        'spk_cutting_id' => $spkCuttingId,
                        'status'         => 'belum_diambil',
                        'keterangan'     => 'Hasil cutting dihapus, kembali ke belum diambil',
                    ]);
                }
            }

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
                'spkCutting:id,id_spk_cutting,produk_id,product_list_id',
                'spkCutting.produk:id,nama_produk,gambar_produk',
                'spkCutting.productList:id,sku_name,product_list_image_id',
                'spkCutting.productList.productListImage:id,image_path',
                'bahan.spkCuttingBahan.bagian',
                'bahan.spkCuttingBahan.bahan'
            ])
                ->orderByDesc('created_at')
                ->get();

            $grouped = $hasilCutting->groupBy(function ($item) {
                $spk = optional($item->spkCutting);
                if ($spk->product_list_id) {
                    return 'PL-' . $spk->product_list_id;
                }
                if ($spk->produk_id) {
                    return 'PR-' . $spk->produk_id;
                }
                return 'unknown';
            });

            $result = [];

            foreach ($grouped as $groupId => $items) {
                $spk = optional($items->first()->spkCutting);
                $namaProduk = null;
                $gambarUrl = null;
                $displayId = $groupId;

                if (str_starts_with($groupId, 'PL-')) {
                    $productList = $spk->productList;
                    if ($productList) {
                        $namaProduk = $productList->sku_name;
                        $displayId = $productList->id;
                        $gambarUrl = optional($productList->productListImage)->image_url;
                    }
                } elseif (str_starts_with($groupId, 'PR-')) {
                    $produk = $spk->produk;
                    if ($produk) {
                        $namaProduk = $produk->nama_produk;
                        $displayId = $produk->id;
                        if ($produk->gambar_produk) {
                            $gambarUrl = asset('storage/' . $produk->gambar_produk);
                        }
                    }
                } else {
                    $displayId = 'unknown';
                }

                $result[] = [
                    'produk_id' => $displayId,
                    'nama_produk' => $namaProduk,
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
            DB::raw('DATE(hasil_cutting.tanggal_potong) as tanggal'),
            'tukang_cutting.nama_tukang_cutting',
            DB::raw('SUM(hasil_cutting.total_produk) as total_pcs')
        )
        ->join('spk_cutting', 'spk_cutting.id', '=', 'hasil_cutting.spk_cutting_id')
        ->join('tukang_cutting', 'tukang_cutting.id', '=', 'spk_cutting.tukang_cutting_id')
        ->whereBetween('hasil_cutting.tanggal_potong', [$start, $end])
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

    /**
     * Laporan Data Acuan dalam Cutting
     */
    public function laporanDataAcuan(Request $request)
    {
        try {
            $data = HasilCuttingBahan::with([
                'hasilCutting.spkCutting.productList',
                'hasilCutting.spkCutting.produk',
                'spkCuttingBahan.bagian',
                'spkCuttingBahan.bahan'
            ])->get();

            $grouped = $data->groupBy(function ($item) {
                $productName = $item->hasilCutting->spkCutting->productList->product_group 
                    ?? $item->hasilCutting->spkCutting->productList->product 
                    ?? $item->hasilCutting->spkCutting->produk->nama_produk 
                    ?? 'Unknown';
                $bagianName = $item->spkCuttingBahan->bagian->nama_bagian ?? '-';
                $bahanName = $item->spkCuttingBahan->bahan->nama_bahan ?? '-';
                $warnaName = $item->spkCuttingBahan->warna ?? '-';
                $groupBahan = $item->spkCuttingBahan->bahan->group_bahan ?? '-';
                $satuan = $item->spkCuttingBahan->bahan->satuan ?? 'kg';
                
                return "{$productName} | {$bagianName} | {$bahanName} | {$warnaName} | {$groupBahan} | {$satuan}";
            });

            $result = [];
            foreach ($grouped as $key => $items) {
                $parts = explode(" | ", $key);
                $productName = $parts[0] ?? 'Unknown';
                $bagianName = $parts[1] ?? '-';
                $bahanName = $parts[2] ?? '-';
                $warnaName = $parts[3] ?? '-';
                $groupBahan = $parts[4] ?? '-';
                $satuan = $parts[5] ?? 'kg';
                
                // Get unique SPKs for this combination
                $spkIds = $items->map(function ($item) {
                    return $item->hasilCutting->spk_cutting_id ?? null;
                })->filter()->unique();
                
                $spkCount = $spkIds->count();
                
                // Hitung rata-rata berat per produk (KG/Yard/Meter)
                $avgWeight = $items->avg('berat_per_produk') ?: 0;
                
                // Hitung total produk dan total berat
                $totalProduk = $items->sum('total_produk') ?: $items->sum('hasil') ?: 0;
                $totalBerat = $items->sum('berat') ?: 0;

                $result[] = [
                    'product' => $productName,
                    'bagian' => $bagianName,
                    'bahan' => $bahanName,
                    'warna' => $warnaName,
                    'group_bahan' => $groupBahan,
                    'satuan' => $satuan,
                    'spk_count' => $spkCount,
                    'avg_weight' => round($avgWeight, 4),
                    'total_produk' => (int) $totalProduk,
                    'total_berat' => round($totalBerat, 2),
                    'is_valid' => $spkCount >= 3,
                ];
            }

            // Urutkan berdasarkan nama produk, bagian, bahan, warna
            usort($result, function ($a, $b) {
                if ($a['product'] !== $b['product']) {
                    return strcmp($a['product'], $b['product']);
                }
                if ($a['bagian'] !== $b['bagian']) {
                    return strcmp($a['bagian'], $b['bagian']);
                }
                if ($a['bahan'] !== $b['bahan']) {
                    return strcmp($a['bahan'], $b['bahan']);
                }
                return strcmp($a['warna'], $b['warna']);
            });

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Error in laporanDataAcuan: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memproses data acuan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export Template Excel berisi semua SPK Cutting (bagian + bahan) untuk diisi manual hasil cutting
     */
    public function exportTemplate(Request $request)
    {
        try {
            $statusFilter = $request->get('status', 'all');

            $query = SpkCutting::with([
                'bagian.bahan.bahan',
                'produk:id,nama_produk',
                'productList:id,product,product_group',
            ])->orderBy('created_at', 'desc');
            $distribusis = SpkCuttingDistribusi::with([
                'spkCutting.bagian.bahan.bahan',
                'spkCutting.produk',
                'spkCutting.productList',
                'detail.productListSku:id,product_size',
            ])->whereNull('hasil_cutting_id')->get();

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Template Hasil Cutting');

            // Headers
            $headers = [
                'A' => 'NO',
                'B' => 'SPK_CUTTING_ID',
                'C' => 'DISTRIBUSI_ID',
                'D' => 'NO SPK (KODE SERI)',
                'E' => 'NAMA PRODUK',
                'F' => 'SIZE',
                'G' => 'STATUS',
                'H' => 'BAGIAN_ID',
                'I' => 'NAMA BAGIAN',
                'J' => 'BAHAN_ID',
                'K' => 'NAMA BAHAN',
                'L' => 'WARNA',
                'M' => 'QTY BAHAN',
                'N' => 'TANGGAL POTONG',
                'O' => 'JUMLAH LEMBAR',
                'P' => 'JUMLAH PRODUK',
                'Q' => 'BERAT TOTAL (KG)',
                'R' => 'BERAT PER PRODUK (KG)',
            ];

            foreach ($headers as $col => $label) {
                $sheet->setCellValue("{$col}1", $label);
            }

            // Style headers
            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '2458CE']],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            ];
            $sheet->getStyle('A1:R1')->applyFromArray($headerStyle);

            // Style kolom yang perlu diisi user (kuning)
            $yellowFill = [
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFDE7']],
            ];

            $row = 2;
            $no = 1;

            foreach ($distribusis as $dist) {
                $spk = $dist->spkCutting;
                if (!$spk) continue;

                $namaProduk = $spk->productList->product_group ?? $spk->productList->product ?? $spk->produk->nama_produk ?? '-';
                
                $sizes = [];
                if ($dist->detail) {
                    foreach ($dist->detail as $detail) {
                        if ($detail->productListSku && $detail->productListSku->product_size) {
                            $sizes[] = $detail->productListSku->product_size;
                        }
                    }
                }
                $sizeStr = count($sizes) > 0 ? implode(', ', array_unique($sizes)) : '-';

                if ($spk->bagian->count() > 0) {
                    foreach ($spk->bagian as $bagian) {
                        if ($bagian->bahan->count() > 0) {
                            foreach ($bagian->bahan as $bahan) {
                                $sheet->setCellValue("A{$row}", $no);
                                $sheet->setCellValueExplicit("B{$row}", $spk->id, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                                $sheet->setCellValueExplicit("C{$row}", $dist->id, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                                $sheet->setCellValue("D{$row}", $dist->kode_seri);
                                $sheet->setCellValue("E{$row}", $namaProduk);
                                $sheet->setCellValue("F{$row}", $sizeStr);
                                $sheet->setCellValue("G{$row}", $spk->status_cutting);
                                $sheet->setCellValueExplicit("H{$row}", $bagian->id, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                                $sheet->setCellValue("I{$row}", $bagian->nama_bagian);
                                $sheet->setCellValueExplicit("J{$row}", $bahan->id, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                                $sheet->setCellValue("K{$row}", $bahan->bahan->nama_bahan ?? '-');
                                $sheet->setCellValue("L{$row}", $bahan->warna ?? '-');
                                $sheet->setCellValue("M{$row}", $bahan->qty ?? 0);

                                // Kolom kosong untuk diisi user
                                $sheet->setCellValue("N{$row}", '');
                                $sheet->setCellValue("O{$row}", '');
                                $sheet->setCellValue("P{$row}", '');
                                $sheet->setCellValue("Q{$row}", '');
                                $sheet->setCellValue("R{$row}", '');

                                // Warnai kolom user
                                $sheet->getStyle("N{$row}:R{$row}")->applyFromArray($yellowFill);

                                $row++;
                                $no++;
                            }
                        } else {
                            // Bagian tanpa bahan
                            $sheet->setCellValue("A{$row}", $no);
                            $sheet->setCellValueExplicit("B{$row}", $spk->id, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                            $sheet->setCellValueExplicit("C{$row}", $dist->id, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                            $sheet->setCellValue("D{$row}", $dist->kode_seri);
                            $sheet->setCellValue("E{$row}", $namaProduk);
                            $sheet->setCellValue("F{$row}", $sizeStr);
                            $sheet->setCellValue("G{$row}", $spk->status_cutting);
                            $sheet->setCellValueExplicit("H{$row}", $bagian->id, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                            $sheet->setCellValue("I{$row}", $bagian->nama_bagian);
                            $sheet->setCellValue("J{$row}", '');
                            $sheet->setCellValue("K{$row}", '-');
                            $sheet->setCellValue("L{$row}", '-');
                            $sheet->setCellValue("M{$row}", 0);
                            $sheet->getStyle("N{$row}:R{$row}")->applyFromArray($yellowFill);

                            $row++;
                            $no++;
                        }
                    }
                }
            }

            foreach (range('A', 'R') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Sembunyikan kolom ID (B, C, H, J) agar user tidak bingung
            $sheet->getColumnDimension('B')->setVisible(false);
            $sheet->getColumnDimension('C')->setVisible(false);
            $sheet->getColumnDimension('H')->setVisible(false);
            $sheet->getColumnDimension('J')->setVisible(false);

            // Freeze header row
            $sheet->freezePane('A2');

            $fileName = 'template-hasil-cutting-' . date('Y-m-d-His') . '.xlsx';

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            ]);
        } catch (\Exception $e) {
            Log::error('Error exporting Hasil Cutting template: ' . $e->getMessage());
            return response()->json([
                'message' => 'Gagal export template Excel',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Import Excel hasil cutting yang sudah diisi manual oleh user
     */
    public function importExcel(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($request->file('file')->getRealPath());
            $rows = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);

            if (count($rows) < 2) {
                return response()->json(['message' => 'File kosong atau tidak memiliki data.'], 422);
            }

            // Map headers
            $headers = array_map(fn($v) => strtolower(trim((string) $v)), $rows[0]);
            $headerMap = [];
            foreach ($headers as $index => $header) {
                if ($header !== '') $headerMap[$header] = $index;
            }

            // Validate required headers
            $requiredHeaders = ['spk_cutting_id', 'distribusi_id', 'bagian_id', 'bahan_id'];
            foreach ($requiredHeaders as $rh) {
                if (!isset($headerMap[$rh])) {
                    return response()->json([
                        'message' => "Kolom '{$rh}' tidak ditemukan di file Excel. Pastikan Anda menggunakan template yang benar.",
                    ], 422);
                }
            }

            DB::beginTransaction();

            $processed = 0;
            $created = 0;
            $skipped = 0;
            $errors = [];

            // Group rows by distribusi_id
            $groupedByDistribusi = [];

            foreach (array_slice($rows, 1) as $index => $row) {
                $rowNumber = $index + 2;

                // Cek baris kosong
                $hasContent = false;
                foreach ($row as $val) {
                    if (trim((string) $val) !== '') { $hasContent = true; break; }
                }
                if (!$hasContent) continue;

                $spkCuttingId = $row[$headerMap['spk_cutting_id'] ?? -1] ?? null;
                $distribusiId = $row[$headerMap['distribusi_id'] ?? -1] ?? null;
                $bagianId = $row[$headerMap['bagian_id'] ?? -1] ?? null;
                $bahanId = $row[$headerMap['bahan_id'] ?? -1] ?? null;
                $namaBagian = $row[$headerMap['nama bagian'] ?? -1] ?? null;
                $namaBahan = $row[$headerMap['nama bahan'] ?? -1] ?? null;
                $warna = $row[$headerMap['warna'] ?? -1] ?? null;
                $qtyBahan = $row[$headerMap['qty bahan'] ?? -1] ?? 0;
                $tanggalPotong = $row[$headerMap['tanggal potong'] ?? -1] ?? null;
                $jumlahLembar = $row[$headerMap['jumlah lembar'] ?? -1] ?? null;
                $jumlahProduk = $row[$headerMap['jumlah produk'] ?? -1] ?? null;
                $beratTotal = $row[$headerMap['berat total (kg)'] ?? -1] ?? null;
                $beratPerProduk = $row[$headerMap['berat per produk (kg)'] ?? -1] ?? null;

                // Skip row jika kolom hasil belum diisi
                if (empty($jumlahLembar) && empty($jumlahProduk) && empty($beratTotal)) {
                    continue;
                }

                $processed++;

                if (!$spkCuttingId || !$distribusiId) {
                    $skipped++;
                    $errors[] = ['row' => $rowNumber, 'message' => 'SPK Cutting ID atau Distribusi ID kosong.'];
                    continue;
                }

                if (!$bagianId || !$bahanId) {
                    $skipped++;
                    $errors[] = ['row' => $rowNumber, 'message' => 'Bagian ID atau Bahan ID kosong.'];
                    continue;
                }

                // Group by Distribusi
                if (!isset($groupedByDistribusi[$distribusiId])) {
                    $groupedByDistribusi[$distribusiId] = [
                        'spk_cutting_id' => $spkCuttingId,
                        'tanggal_potong' => null,
                        'items' => [],
                    ];
                }

                if ($tanggalPotong) {
                    $groupedByDistribusi[$distribusiId]['tanggal_potong'] = $tanggalPotong;
                }

                $totalProduk = (float) ($jumlahLembar ?: 0) * (float) ($jumlahProduk ?: 0);

                $groupedByDistribusi[$distribusiId]['items'][] = [
                    'row' => $rowNumber,
                    'spk_cutting_bagian_id' => (int) $bagianId,
                    'spk_cutting_bahan_id' => (int) $bahanId,
                    'nama_bagian' => $namaBagian,
                    'nama_bahan' => $namaBahan,
                    'warna' => $warna,
                    'qty' => (float) ($qtyBahan ?: 0),
                    'jumlah_lembar' => (float) ($jumlahLembar ?: 0),
                    'jumlah_produk' => (float) ($jumlahProduk ?: 0),
                    'total_produk' => $totalProduk,
                    'berat_total' => (float) ($beratTotal ?: 0),
                    'berat_per_produk' => (float) ($beratPerProduk ?: 0),
                ];
            }

            // Process each Distribusi group
            foreach ($groupedByDistribusi as $distribusiId => $group) {
                $spkCuttingId = $group['spk_cutting_id'];
                
                $spkCutting = SpkCutting::find($spkCuttingId);
                if (!$spkCutting) {
                    $skipped += count($group['items']);
                    foreach ($group['items'] as $item) {
                        $errors[] = ['row' => $item['row'], 'message' => "SPK Cutting ID {$spkCuttingId} tidak ditemukan."];
                    }
                    continue;
                }

                $distribusi = SpkCuttingDistribusi::find($distribusiId);
                if (!$distribusi) {
                    $skipped += count($group['items']);
                    foreach ($group['items'] as $item) {
                        $errors[] = ['row' => $item['row'], 'message' => "Distribusi ID {$distribusiId} tidak ditemukan."];
                    }
                    continue;
                }

                if ($distribusi->hasil_cutting_id) {
                    $skipped += count($group['items']);
                    foreach ($group['items'] as $item) {
                        $errors[] = ['row' => $item['row'], 'message' => "Distribusi {$distribusi->kode_seri} sudah memiliki hasil cutting."];
                    }
                    continue;
                }

                // Validate bagian & bahan exist
                $valid = true;
                foreach ($group['items'] as $item) {
                    $bagianExists = \App\Models\SpkCuttingBagian::where('id', $item['spk_cutting_bagian_id'])
                        ->where('spk_cutting_id', $spkCutting->id)
                        ->exists();

                    if (!$bagianExists) {
                        $skipped++;
                        $errors[] = ['row' => $item['row'], 'message' => "Bagian ID {$item['spk_cutting_bagian_id']} tidak cocok dengan SPK Cutting ID {$spkCuttingId}."];
                        $valid = false;
                    }

                    $bahanExists = \App\Models\SpkCuttingBahan::where('id', $item['spk_cutting_bahan_id'])->exists();
                    if (!$bahanExists) {
                        $skipped++;
                        $errors[] = ['row' => $item['row'], 'message' => "Bahan ID {$item['spk_cutting_bahan_id']} tidak ditemukan."];
                        $valid = false;
                    }
                }

                if (!$valid) continue;

                // Calculate totals
                $totalProdukAll = array_sum(array_column($group['items'], 'total_produk'));
                $hargaPerPcs = $spkCutting->harga_per_pcs ?? 0;
                $totalBayar = $hargaPerPcs * $totalProdukAll;

                $firstItem = $group['items'][0];

                // Parse tanggal
                $tanggalPotongParsed = null;
                if ($group['tanggal_potong']) {
                    try {
                        if (is_numeric($group['tanggal_potong'])) {
                            $tanggalPotongParsed = Carbon::instance(
                                \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((int) $group['tanggal_potong'])
                            )->toDateString();
                        } else {
                            $tanggalPotongParsed = Carbon::parse($group['tanggal_potong'])->toDateString();
                        }
                    } catch (\Exception $e) {
                        $tanggalPotongParsed = null;
                    }
                }

                // Create HasilCutting (header)
                $hasilCutting = HasilCutting::create([
                    'spk_cutting_id' => $spkCutting->id,
                    'spk_cutting_distribusi_id' => $distribusi->id,
                    'jenis_hasil' => 'utama',
                    'spk_cutting_bagian_id' => $firstItem['spk_cutting_bagian_id'],
                    'nama_bagian' => $firstItem['nama_bagian'],
                    'nama_bahan' => $firstItem['nama_bahan'],
                    'warna' => $firstItem['warna'],
                    'qty' => $firstItem['qty'],
                    'total_produk' => $totalProdukAll,
                    'total_bayar' => $totalBayar,
                    'tanggal_potong' => $tanggalPotongParsed,
                ]);

                // Create HasilCuttingBahan (detail per baris)
                foreach ($group['items'] as $item) {
                    HasilCuttingBahan::create([
                        'hasil_cutting_id' => $hasilCutting->id,
                        'spk_cutting_bahan_id' => $item['spk_cutting_bahan_id'],
                        'spk_cutting_bagian_id' => $item['spk_cutting_bagian_id'],
                        'jumlah_lembar' => $item['jumlah_lembar'],
                        'jumlah_produk' => $item['jumlah_produk'],
                        'berat' => $item['berat_total'],
                        'berat_per_produk' => $item['berat_per_produk'],
                        'hasil' => $item['total_produk'],
                    ]);
                }

                // Update existing distribusi seri
                $distribusi->update([
                    'hasil_cutting_id' => $hasilCutting->id,
                    'jumlah_produk' => $totalProdukAll,
                    'status' => 'draft',
                ]);

                // Update detail distribusi per warna
                $dataPerWarna = [];
                foreach ($group['items'] as $item) {
                    $w = $item['warna'] ?? 'Unknown';
                    if (!isset($dataPerWarna[$w])) $dataPerWarna[$w] = 0;
                    $dataPerWarna[$w] += $item['total_produk'];
                }

                foreach ($dataPerWarna as $w => $jumlah) {
                    $detailExists = \App\Models\SpkCuttingDistribusiDetail::where('spk_cutting_distribusi_id', $distribusi->id)
                        ->where('warna', $w)
                        ->first();
                        
                    if ($detailExists) {
                        $detailExists->update(['jumlah_produk' => $jumlah]);
                    } else {
                        \App\Models\SpkCuttingDistribusiDetail::create([
                            'spk_cutting_distribusi_id' => $distribusi->id,
                            'warna' => $w,
                            'jumlah_produk' => $jumlah,
                        ]);
                    }
                }

                // Update status SPK Cutting
                if ($spkCutting->status_cutting === 'In Progress' || $spkCutting->status_cutting === 'Pending') {
                    $deadline = Carbon::parse($spkCutting->tanggal_batas_kirim);
                    $sisaHari = $deadline->isPast() ? 0 : $deadline->diffInDays(now());

                    $spkCutting->update([
                        'status_cutting' => 'In Progress',
                        'sisa_hari_terakhir' => $sisaHari,
                    ]);
                }

                $created++;
            }

            DB::commit();

            return response()->json([
                'message' => 'Import berhasil.',
                'processed' => $processed,
                'created' => $created,
                'skipped' => $skipped,
                'total_errors' => count($errors),
                'errors' => $errors,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error importing Hasil Cutting: ' . $e->getMessage());
            return response()->json([
                'message' => 'Gagal mengimport file Excel.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}
