<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProductList;
use App\Models\SpkCutting;
use App\Models\SpkCuttingBagian;
use App\Models\SpkCuttingBahan;
use App\Models\SpkCuttingDistribusi;
use App\Models\SpkCuttingDistribusiDetail;
use App\Models\TukangCutting;
use App\Models\SpkCuttingStatusLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Exports\SpkCuttingExport;
use Maatwebsite\Excel\Facades\Excel;


class SpkCuttingController extends Controller

{

    private const ASUMSI_PRODUK_PER_ROLL = 60;
    private const SKU_SIZE_ORDER = [
        'XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', 'XXXXL', 'ALL SIZE', 'FREE SIZE',
    ];

    private function getProductListCatalog(?int $productListId): ?ProductList
    {
        if (!$productListId) {
            return null;
        }

        $selected = ProductList::find($productListId, ['id', 'product', 'product_group']);

        if (!$selected) {
            return null;
        }

        return ProductList::query()
            ->where('product_group', $selected->product_group)
            ->orderBy('id')
            ->first(['id', 'product', 'product_group', 'price_cutting', 'notes_spk', 'estimasi_cutting', 'estimasi_combi']);
    }

    private function calculateJumlahAsumsiProduk(array $bagian, ?int $estimasiCutting, ?int $estimasiCombi): int
    {
        $totalAsumsi = 0;
        
        $multiplierCutting = ($estimasiCutting !== null && $estimasiCutting > 0) ? $estimasiCutting : self::ASUMSI_PRODUK_PER_ROLL;
        $multiplierCombi = ($estimasiCombi !== null && $estimasiCombi > 0) ? $estimasiCombi : self::ASUMSI_PRODUK_PER_ROLL;

        foreach ($bagian as $bagianData) {
            $namaBagian = strtolower(trim((string) ($bagianData['nama_bagian'] ?? '')));
            if ($this->isAksesorisBagian($namaBagian)) {
                continue;
            }

            $isCombi = str_contains($namaBagian, 'combi') || str_contains($namaBagian, 'kombinasi');
            $multiplier = $isCombi ? $multiplierCombi : $multiplierCutting;

            $totalRoll = 0;
            foreach (($bagianData['bahan'] ?? []) as $bahanData) {
                $totalRoll += (float) ($bahanData['qty'] ?? 0);
            }
            
            $totalAsumsi += $totalRoll * $multiplier;
        }

        return (int) round($totalAsumsi);
    }

    private function isAksesorisBagian(?string $namaBagian): bool
    {
        $name = strtolower(trim((string) $namaBagian));

        return str_contains($name, 'aksesor') || str_contains($name, 'accessor');
    }

    private function validateBagianKomponen(array $bagian, ?string $mode = 'biasa'): void
    {
        $errors = [];

        foreach ($bagian as $bagianIndex => $bagianData) {
            $isAksesoris = $this->isAksesorisBagian($bagianData['nama_bagian'] ?? null);

            foreach (($bagianData['bahan'] ?? []) as $bahanIndex => $bahanData) {
                $fieldPrefix = "bagian.$bagianIndex.bahan.$bahanIndex";

                if ($isAksesoris) {
                    if (empty($bahanData['aksesoris_id'])) {
                        $errors["$fieldPrefix.aksesoris_id"][] = 'Aksesoris wajib dipilih untuk bagian aksesoris.';
                    }
                } elseif ($mode !== 'potong_kecil' && empty($bahanData['bahan_id'])) {
                    $errors["$fieldPrefix.bahan_id"][] = 'Bahan wajib dipilih untuk bagian bahan.';
                }
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function makeSpkCuttingBahanPayload(int $bagianId, array $bagianData, array $bahanData): array
    {
        $isAksesoris = $this->isAksesorisBagian($bagianData['nama_bagian'] ?? null);

        return [
            'spk_cutting_bagian_id' => $bagianId,
            'sumber_komponen' => $isAksesoris ? 'aksesoris' : 'bahan',
            'bahan_id' => $isAksesoris ? null : ($bahanData['bahan_id'] ?? null),
            'aksesoris_id' => $isAksesoris ? ($bahanData['aksesoris_id'] ?? null) : null,
            'warna' => $isAksesoris ? null : ($bahanData['warna'] ?? null),
            'berat' => $isAksesoris ? null : ($bahanData['berat'] ?? null),
            'qty' => $bahanData['qty'],
        ];
    }

    private function applyAutomaticProductFields(array $validated): array
    {
        $catalog = $this->getProductListCatalog((int) ($validated['product_list_id'] ?? 0));

        $hargaJasa = $catalog?->price_cutting ?? null;
        if ($hargaJasa === null || (float) $hargaJasa <= 0) {
            $hargaJasa = $validated['harga_jasa'] ?? 0;
        }

        $validated['harga_jasa'] = (float) $hargaJasa;
        $validated['jumlah_asumsi_produk'] = $this->calculateJumlahAsumsiProduk(
            $validated['bagian'] ?? [],
            $catalog?->estimasi_cutting ?? null,
            $catalog?->estimasi_combi ?? null
        );

        $notesSpk = trim((string) ($catalog?->notes_spk ?? ''));
        if ($notesSpk !== '') {
            $validated['keterangan'] = $notesSpk;
        }

        return $validated;
    }

    private function validateProductListSkus(int $productListId, array $productListSkuIds): void
    {
        $selected = ProductList::findOrFail($productListId, ['id', 'product_group']);
        $skuCount = ProductList::query()
            ->where('product_group', $selected->product_group)
            ->whereIn('id', $productListSkuIds)
            ->count();

        if ($skuCount !== count($productListSkuIds)) {
            throw ValidationException::withMessages([
                'product_list_sku_ids' => ['Terdapat SKU yang tidak sesuai dengan product group Product List.'],
            ]);
        }
    }

    private function normalizeSkuSize(?string $size): string
    {
        $normalized = strtoupper(trim(preg_replace('/\s+/', ' ', str_replace(['_', '-'], ' ', (string) $size))));

        if (in_array($normalized, ['ALLSIZE', 'ALL SIZE'], true)) {
            return 'ALL SIZE';
        }

        if (in_array($normalized, ['FREESIZE', 'FREE SIZE'], true)) {
            return 'FREE SIZE';
        }

        return $normalized;
    }

    private function getSkuSizeOrder(?string $size): int
    {
        $index = array_search($this->normalizeSkuSize($size), self::SKU_SIZE_ORDER, true);

        return $index === false ? count(self::SKU_SIZE_ORDER) : $index;
    }

    private function makeDistribusiSuffix(int $index): string
    {
        $alphabet = range('A', 'Z');
        $suffix = '';
        $number = $index;

        do {
            $suffix = $alphabet[$number % 26] . $suffix;
            $number = intdiv($number, 26) - 1;
        } while ($number >= 0);

        return $suffix;
    }

    private function syncSkuDistributions(SpkCutting $spk, array $productListSkuIds): void
    {
        $skus = ProductList::query()
            ->whereIn('id', $productListSkuIds)
            ->get(['id', 'sku_name', 'product', 'product_colour', 'product_size'])
            ->sort(function ($a, $b) {
                $sizeCompare = $this->getSkuSizeOrder($a->product_size) <=> $this->getSkuSizeOrder($b->product_size);
                if ($sizeCompare !== 0) {
                    return $sizeCompare;
                }

                $colorCompare = strcmp((string) $a->product_colour, (string) $b->product_colour);
                if ($colorCompare !== 0) {
                    return $colorCompare;
                }

                return strcmp((string) $a->sku_name, (string) $b->sku_name);
            })
            ->values();

        $skusBySize = $skus->groupBy(function($sku) {
            return $this->normalizeSkuSize($sku->product_size);
        });

        $sizeCount = $skusBySize->count();
        $sizeIndex = 0;
        $activeDistribusiIds = [];

        foreach ($skusBySize as $size => $sizeSkus) {
            if ($sizeCount === 1) {
                $kodeSeri = $spk->id_spk_cutting;
            } else {
                $kodeSeri = $spk->id_spk_cutting . '-' . $this->makeDistribusiSuffix($sizeIndex);
            }
            $sizeIndex++;

            $distribusi = SpkCuttingDistribusi::firstOrCreate(
                [
                    'spk_cutting_id' => $spk->id,
                    'kode_seri' => $kodeSeri,
                ],
                [
                    'hasil_cutting_id' => null,
                    'jumlah_produk' => 0,
                    'status' => 'draft',
                ]
            );

            $activeDistribusiIds[] = $distribusi->id;
            $keptDetailIds = [];

            foreach ($sizeSkus as $sku) {
                $detail = SpkCuttingDistribusiDetail::updateOrCreate(
                    [
                        'spk_cutting_distribusi_id' => $distribusi->id,
                        'product_list_id' => $sku->id,
                    ],
                    [
                        'warna' => $sku->product_colour ?: '-',
                        'jumlah_produk' => 0,
                        'produk_sku_id' => null,
                    ]
                );
                $keptDetailIds[] = $detail->id;
            }

            // Remove details for this distribution that are no longer associated
            $distribusi->detail()->whereNotIn('id', $keptDetailIds)->delete();
        }

        // Clean up orphaned distributions for this SPK
        if (!empty($activeDistribusiIds)) {
            SpkCuttingDistribusi::where('spk_cutting_id', $spk->id)
                ->whereNotIn('id', $activeDistribusiIds)
                ->delete();
        }
    }

    private function generateSpkNumber()
    {
        $lastSpk = SpkCutting::orderBy('id', 'desc')->first();
        
        if ($lastSpk) {
            $code = $lastSpk->id_spk_cutting;
            if (preg_match('/^(.*?)(\d+)$/', $code, $matches)) {
                $prefix = $matches[1];
                $suffix = $matches[2];
                $padding = strlen($suffix);
                $nextNum = (int)$suffix + 1;
                $spkNumber = $prefix . str_pad($nextNum, $padding, '0', STR_PAD_LEFT);
            } else {
                $spkNumber = $code . '-1';
            }
        } else {
            $spkNumber = '1';
        }
        
        return $spkNumber;
    }


    public function index(Request $request)
    {
        // ✅ OPTIMASI P3: Eager loading dengan select spesifik untuk mengurangi data transfer
        $query = SpkCutting::with([
            'productList:id,product,product_group,price_cutting,estimasi_cutting,estimasi_combi',
            'productListSkus:id,product,sku_name,product_colour,product_size',
            'bagian' => function($q) {
                // Hanya load kolom yang diperlukan
                $q->select('id', 'spk_cutting_id', 'nama_bagian')
                  ->with(['bahan' => function($q) {
                      // Hanya load kolom yang diperlukan
                      $q->select('id', 'spk_cutting_bagian_id', 'sumber_komponen', 'bahan_id', 'aksesoris_id', 'warna', 'qty', 'berat')
                        ->with(['bahan:id,nama_bahan', 'aksesoris:id,nama_aksesoris', 'skus:id,product,sku_name,product_colour,product_size']); // Tambah skus
                  }]);
            },
            'tukangCutting:id,nama_tukang_cutting',
            'tukangPola:id,nama',
        ])->withExists('stokBahanKeluar as is_bahan_scanned');

        // Filter berdasarkan status jika ada
        if ($request->has('status') && $request->status !== '' && $request->status !== 'all') {
            $query->where('status_cutting', $request->status);
        }

        // Filter berdasarkan jenis_spk jika ada
        if ($request->filled('jenis_spk')) {
            $query->where('jenis_spk', $request->jenis_spk);
        }

        // Filter berdasarkan status scan (semua, sudah_discan, belum_discan)
        if ($request->has('scan_status') && $request->scan_status !== 'semua') {
            if ($request->scan_status === 'sudah_discan') {
                $query->whereHas('stokBahanKeluar');
            } else if ($request->scan_status === 'belum_discan') {
                $query->whereDoesntHave('stokBahanKeluar');
            }
        }

        // Filter berdasarkan tanggal dibuat (created_at) jika ada
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // ✅ OPTIMASI P0 + P2: Search dengan subquery + prefix search priority
        if ($request->filled('search')) {
            $searchTerm = $request->get('search');
            $query->where(function($q) use ($searchTerm) {
                // ✅ P2: Exact match untuk ID (paling cepat, bisa pakai primary key index)
                if (is_numeric($searchTerm)) {
                    $q->where('id', '=', $searchTerm);
                }
                
                // ✅ P2: Prefix search untuk id_spk_cutting (bisa pakai index, lebih cepat dari contains)
                // Prioritaskan prefix search dulu, baru contains sebagai fallback
                $q->orWhere(function($subQ) use ($searchTerm) {
                    $subQ->where('id_spk_cutting', 'like', "{$searchTerm}%") // Prefix (bisa pakai index)
                         ->orWhere('id_spk_cutting', 'like', "%{$searchTerm}%"); // Contains (fallback)
                });
                
                // ✅ P0: Gunakan subquery instead of orWhereHas (5-10x lebih cepat untuk 100k+ data)
                $q->orWhereIn('tukang_cutting_id', function($subQuery) use ($searchTerm) {
                    $subQuery->select('id')
                             ->from('tukang_cutting')
                             ->where(function($q) use ($searchTerm) {
                                 // Prioritaskan prefix search
                                 $q->where('nama_tukang_cutting', 'like', "{$searchTerm}%")
                                   ->orWhere('nama_tukang_cutting', 'like', "%{$searchTerm}%");
                             });
                })
                ->orWhereIn('product_list_id', function($subQuery) use ($searchTerm) {
                    $subQuery->select('id')
                             ->from('product_lists')
                             ->where(function($q) use ($searchTerm) {
                                 // Prioritaskan prefix search
                                 $q->where('product', 'like', "{$searchTerm}%")
                                   ->orWhere('product', 'like', "%{$searchTerm}%")
                                   ->orWhere('product_group', 'like', "{$searchTerm}%")
                                   ->orWhere('product_group', 'like', "%{$searchTerm}%");
                             });
                });
            });
        }

        // ✅ OPTIMASI: Pagination dengan default 15 per page, max 100
        $perPage = min($request->get('per_page', 15), 100);
        $data = $query->orderBy('id', 'desc')->paginate($perPage);

        // Hitung summary per status (hilangkan Pending)
        // Summary bersifat GLOBAL (semua waktu), tidak terpengaruh oleh filter tanggal tabel
        // agar Donut Chart dan Weekly/Daily Target tidak reset saat filter tabel adalah 'today'
        $summaryBaseQuery = SpkCutting::query();

        // Filter berdasarkan jenis_spk jika ada (untuk summary juga)
        if ($request->filled('jenis_spk')) {
            $summaryBaseQuery->where('jenis_spk', $request->jenis_spk);
        }

        // ✅ OPTIMASI: Gunakan single query dengan conditional aggregation untuk summary
        $summaryStats = (clone $summaryBaseQuery)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status_cutting = "belum_diambil" THEN 1 ELSE 0 END) as belum_diambil_count,
                SUM(CASE WHEN status_cutting = "sudah_diambil" THEN 1 ELSE 0 END) as sudah_diambil_count,
                SUM(CASE WHEN status_cutting = "selesai" THEN 1 ELSE 0 END) as selesai_count,
                SUM(CASE WHEN status_cutting = "belum_diambil" THEN COALESCE(jumlah_asumsi_produk, 0) ELSE 0 END) as total_asumsi_belum_diambil
            ')
            ->first();

        $summaryAll = $summaryStats->total ?? 0;
        $countBelumDiambil = $summaryStats->belum_diambil_count ?? 0;
        $summarySudahDiambil = $summaryStats->sudah_diambil_count ?? 0;
        $summarySelesai = $summaryStats->selesai_count ?? 0;
        $totalAsumsiBelumDiambil = (int)($summaryStats->total_asumsi_belum_diambil ?? 0);

        // Hitung statistik berdasarkan periode (untuk grafik dan data lainnya)
        $progressStatusFilter = $request->get('progress_status', 'all');

        $weeklyStart = $request->get('weekly_start') ?: \Carbon\Carbon::now()->startOfWeek()->format('Y-m-d');
        $weeklyEnd = $request->get('weekly_end') ?: \Carbon\Carbon::now()->endOfWeek()->format('Y-m-d');
        $dailyDate = $request->get('daily_date') ?: \Carbon\Carbon::now()->format('Y-m-d');

        $inProgressWeekly = [
            'count' => 0,
            'total_asumsi_produk' => 0,
            'status' => $progressStatusFilter,
        ];
        $inProgressDaily = [
            'count' => 0,
            'total_asumsi_produk' => 0,
            'status' => $progressStatusFilter,
        ];

        // Hitung untuk periode mingguan
        $weeklyTargetBase = 50000; // Target dasar per minggu 50.000
        if ($weeklyStart && $weeklyEnd) {
            // Hitung jumlah minggu dari rentang tanggal
            $startDate = \Carbon\Carbon::parse($weeklyStart);
            $endDate = \Carbon\Carbon::parse($weeklyEnd);
            // Hitung selisih hari (inklusif: termasuk start dan end date)
            $diffInDays = $startDate->diffInDays($endDate) + 1; // +1 untuk inklusif
            // Hitung jumlah minggu (bulatkan ke atas)
            $numberOfWeeks = ceil($diffInDays / 7);
            if ($numberOfWeeks < 1) {
                $numberOfWeeks = 1; // Minimal 1 minggu
            }

            // Target dinamis = target dasar x jumlah minggu
            $weeklyTarget = $weeklyTargetBase * $numberOfWeeks;

            $weeklyQuery = (clone $summaryBaseQuery)
                ->whereDate('created_at', '>=', $weeklyStart)
                ->whereDate('created_at', '<=', $weeklyEnd);
                
            if ($progressStatusFilter !== 'all' && $progressStatusFilter !== '') {
                $weeklyQuery->where('status_cutting', $progressStatusFilter);
            }
            $inProgressWeekly['count'] = $weeklyQuery->count();
            $inProgressWeekly['total_asumsi_produk'] = (int)($weeklyQuery->sum('jumlah_asumsi_produk') ?? 0);
            $inProgressWeekly['target'] = $weeklyTarget;
            $inProgressWeekly['remaining'] = max(0, $weeklyTarget - $inProgressWeekly['total_asumsi_produk']);
        } else {
            $inProgressWeekly['target'] = $weeklyTargetBase;
            $inProgressWeekly['remaining'] = $weeklyTargetBase;
        }

        // Hitung untuk periode harian
        $dailyTarget = 7143; // Target harian 7.143
        if ($dailyDate) {
            $dailyQuery = (clone $summaryBaseQuery)
                ->whereDate('created_at', $dailyDate);
                
            if ($progressStatusFilter !== 'all' && $progressStatusFilter !== '') {
                $dailyQuery->where('status_cutting', $progressStatusFilter);
            }
            $inProgressDaily['count'] = $dailyQuery->count();
            $inProgressDaily['total_asumsi_produk'] = (int)($dailyQuery->sum('jumlah_asumsi_produk') ?? 0);
            $inProgressDaily['target'] = $dailyTarget;
            $inProgressDaily['remaining'] = max(0, $dailyTarget - $inProgressDaily['total_asumsi_produk']);
        } else {
            $inProgressDaily['target'] = $dailyTarget;
            $inProgressDaily['remaining'] = $dailyTarget;
        }

        // --- CHART DATA AGGREGATION ---
        $chartQuery = (clone $summaryBaseQuery);
        
        if ($progressStatusFilter !== 'all' && $progressStatusFilter !== '') {
            $chartQuery->where('status_cutting', $progressStatusFilter);
        }

        $chartQuery->selectRaw('DATE(created_at) as date, COALESCE(SUM(jumlah_asumsi_produk), 0) as total_qty')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->limit(14); // 14 hari terakhir

        $chartDataRaw = $chartQuery->get();
        // Urutkan ascending untuk chart (kiri ke kanan)
        $chartData = $chartDataRaw->sortBy('date')->values()->toArray();

        $summary = [
            'all' => $summaryAll,
            'belum_diambil' => [
                'count' => $countBelumDiambil,
                'total_asumsi_produk' => $totalAsumsiBelumDiambil,
            ],
            'sudah_diambil' => $summarySudahDiambil,
            'selesai' => $summarySelesai,
            'in_progress_weekly' => $inProgressWeekly,
            'in_progress_daily' => $inProgressDaily,
            'chart_data' => $chartData,
        ];

        return response()->json([
            'data' => $data->items(),
            'pagination' => [
                'current_page' => (int) $data->currentPage(), // ✅ Fix: Ensure integer
                'last_page' => (int) $data->lastPage(), // ✅ Fix: Ensure integer
                'per_page' => (int) $data->perPage(), // ✅ Fix: Ensure integer (not string)
                'total' => (int) $data->total(), // ✅ Fix: Ensure integer
                'from' => (int) ($data->firstItem() ?? 0), // ✅ Fix: Handle null value and ensure integer
                'to' => (int) ($data->lastItem() ?? 0), // ✅ Fix: Handle null value and ensure integer
            ],
            'summary' => $summary,
        ]);
    }


    public function show($id)
    {
        $spk = SpkCutting::with('productList:id,product,product_group,price_cutting,estimasi_cutting,estimasi_combi', 'bagian.bahan.bahan', 'bagian.bahan.aksesoris', 'bagian.bahan.skus', 'tukangPola:id,nama', 'productListSkus:id,product,sku_name,product_colour,product_size')->find($id);
        if (!$spk) {
            return response()->json(['message' => 'SPK Cutting tidak ditemukan'], 404);
        }
        return response()->json(['data' => $spk]);
    }


    public function getGeneratedSpkNumber(Request $request)
    {
        try {
            $request->validate([
                'tukang_cutting_id' => 'nullable|exists:tukang_cutting,id',
            ]);
            $spkNumber = $this->generateSpkNumber();
            return response()->json([
                'id_spk_cutting' => $spkNumber,
                'generated_number' => $spkNumber
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

        $validated = $request->validate([
            'id_spk_cutting' => 'required|string|max:255|unique:spk_cutting,id_spk_cutting',
            'pic' => 'nullable|string|max:255',
            'product_list_id' => 'required|exists:product_lists,id',
            'produk_id' => 'nullable',
            'tanggal_buat' => 'required|date',
            'tanggal_batas_kirim' => 'required|date',
            'harga_jasa' => 'nullable|numeric|min:0',
            'satuan_harga' => 'required|in:Lusin,Pcs',
            'keterangan' => 'nullable|string',
            'jumlah_asumsi_produk' => 'nullable|integer|min:0',
            'jenis_spk' => 'nullable|string|in:Terjual,Fittingan,Habisin Bahan',
            'mode' => 'nullable|string|in:biasa,potong_kecil',
            'bagian' => 'required|array',
            'bagian.*.nama_bagian' => 'required|string',
            'bagian.*.bahan' => 'required|array',
            'bagian.*.bahan.*.sumber_komponen' => 'nullable|in:bahan,aksesoris',
            'bagian.*.bahan.*.bahan_id' => 'nullable|exists:bahan,id',
            'bagian.*.bahan.*.aksesoris_id' => 'nullable|exists:aksesoris,id',
            'bagian.*.bahan.*.warna' => 'nullable|string|max:255',
            'bagian.*.bahan.*.berat' => 'nullable|numeric|min:0',
            'bagian.*.bahan.*.qty' => 'nullable|numeric|min:0',
            'tukang_cutting_id' => 'required|exists:tukang_cutting,id',
            'tukang_pola_id' => 'nullable|exists:tukang_pola,id',
            'bagian.*.bahan.*.skus' => 'nullable|array',
            'bagian.*.bahan.*.skus.*.sku_id' => 'required_with:bagian.*.bahan.*.skus|exists:product_lists,id',
            'bagian.*.bahan.*.skus.*.qty' => 'required_with:bagian.*.bahan.*.skus|numeric|min:0',
        ]);

        $mode = $validated['mode'] ?? 'biasa';
        $this->validateBagianKomponen($validated['bagian'] ?? [], $mode);

        if ($mode === 'potong_kecil') {
            $totalPcs = 0;
            foreach ($validated['bagian'] as $bagianData) {
                foreach ($bagianData['bahan'] as $bahanData) {
                    if (!empty($bahanData['skus'])) {
                        foreach ($bahanData['skus'] as $sku) {
                            $totalPcs += (float) ($sku['qty'] ?? 0);
                        }
                    }
                }
            }
            $validated['jumlah_asumsi_produk'] = (int) round($totalPcs);
        } else {
            $validated = $this->applyAutomaticProductFields($validated);
        }

        // Kumpulkan semua SKU yang dipilih dari bahan
        $productListSkuIds = [];
        foreach ($validated['bagian'] as $bagianData) {
            foreach ($bagianData['bahan'] as $bahanData) {
                if (!empty($bahanData['skus'])) {
                    foreach ($bahanData['skus'] as $sku) {
                        $productListSkuIds[] = $sku['sku_id'];
                    }
                }
            }
        }
        $productListSkuIds = array_values(array_unique($productListSkuIds));

        if (empty($productListSkuIds)) {
            throw ValidationException::withMessages([
                'bagian' => ['Minimal pilih 1 SKU pada bahan produk.'],
            ]);
        }

        $this->validateProductListSkus((int) $validated['product_list_id'], $productListSkuIds);

        // ===============================
        // MANUAL NO SPK (from request)
        // ===============================
        // Nomor seri sudah divalidasi dan diambil dari request

       
        $validated['harga_per_pcs'] = $validated['satuan_harga'] === 'Lusin'
            ? $validated['harga_jasa'] / 12
            : $validated['harga_jasa'];

       $validated['status_cutting'] = 'belum_diambil';

        $validated['barcode'] = 'SPKC-' . strtoupper(uniqid());

        DB::beginTransaction();

        $validated['produk_id'] = null;

        $tanggalBuat = null;
        if (isset($validated['tanggal_buat'])) {
            $tanggalBuat = \Carbon\Carbon::parse($validated['tanggal_buat'])->format('Y-m-d H:i:s');
            unset($validated['tanggal_buat']);
        }

        $spk = SpkCutting::create($validated);

        if ($tanggalBuat) {
            $spk->created_at = $tanggalBuat;
            $spk->save();
        }


        $spk->productListSkus()->attach($productListSkuIds);
        $this->syncSkuDistributions($spk, $productListSkuIds);

      
        SpkCuttingStatusLog::create([
            'spk_cutting_id' => $spk->id,
            'status' => $spk->status_cutting,
            'keterangan' => 'SPK Cutting dibuat',
        ]);

       
        foreach ($request->bagian as $bagianData) {

            $bagian = SpkCuttingBagian::create([
                'spk_cutting_id' => $spk->id,
                'nama_bagian' => $bagianData['nama_bagian'],
            ]);

            foreach ($bagianData['bahan'] as $bahanData) {
                $bahan = SpkCuttingBahan::create($this->makeSpkCuttingBahanPayload($bagian->id, $bagianData, $bahanData));
                
                if (!empty($bahanData['skus'])) {
                    $skuData = [];
                    foreach ($bahanData['skus'] as $sku) {
                        $skuData[$sku['sku_id']] = ['qty' => $sku['qty']];
                    }
                    $bahan->skus()->sync($skuData);
                }
            }
        }

        DB::commit();

      return response()->json([
            'message' => 'SPK Cutting berhasil ditambahkan',
            'data' => $spk->load([
                'productListSkus',
                'bagian.bahan.bahan',
                'bagian.bahan.aksesoris',
            ]),
        ], 201);

    } catch (ValidationException $e) {
        return response()->json([
            'message' => 'Validasi gagal',
            'errors' => $e->errors(),
        ], 422);
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Gagal menyimpan data',
            'error' => $e->getMessage(),
        ], 500);
    }
}


public function updateStatus(Request $request, $id)
{
    $validated = $request->validate([
        'status' => 'required|in:belum_diambil,sudah_diambil,selesai',
        'keterangan' => 'nullable|string',
    ]);

    $spk = SpkCutting::findOrFail($id);

    // ❌ Kalau status sama, stop
    if ($spk->status_cutting === $validated['status']) {
        return response()->json([
            'message' => 'Status sudah sama, tidak ada perubahan'
        ], 422);
    }

    DB::transaction(function () use ($spk, $validated) {

        // 🔹 Update status utama
        $spk->update([
            'status_cutting' => $validated['status']
        ]);

        // 🔹 Simpan log status
        SpkCuttingStatusLog::create([
            'spk_cutting_id' => $spk->id,
            'status' => $validated['status'],
            'keterangan' => $validated['keterangan'] ?? null,
        ]);
    });

    return response()->json([
        'message' => 'Status SPK Cutting berhasil diperbarui',
        'data' => $spk->load('statusLogs')
    ]);
}

    public function update(Request $request, $id)
    {
        try {
            $spk = SpkCutting::findOrFail($id);
            // Validasi data
            $validated = $request->validate([
                'id_spk_cutting' => 'required|string|max:255|unique:spk_cutting,id_spk_cutting,' . $id,
                'pic' => 'nullable|string|max:255',
                'product_list_id' => 'required|exists:product_lists,id',
                'produk_id' => 'nullable',
                'tanggal_buat' => 'required|date',
                'tanggal_batas_kirim' => 'required|date',
                'harga_jasa' => 'nullable|numeric|min:0',
                'satuan_harga' => 'required|in:Lusin,Pcs',
                'keterangan' => 'nullable|string',
                'jumlah_asumsi_produk' => 'nullable|integer|min:0',
                'jenis_spk' => 'nullable|string|in:Terjual,Fittingan,Habisin Bahan',
                'mode' => 'nullable|string|in:biasa,potong_kecil',
                'bagian' => 'required|array',
                'bagian.*.nama_bagian' => 'required|string',
                'bagian.*.bahan' => 'required|array',
                'bagian.*.bahan.*.sumber_komponen' => 'nullable|in:bahan,aksesoris',
                'bagian.*.bahan.*.bahan_id' => 'nullable|exists:bahan,id',
                'bagian.*.bahan.*.aksesoris_id' => 'nullable|exists:aksesoris,id',
                'bagian.*.bahan.*.warna' => 'nullable|string|max:255',
                'bagian.*.bahan.*.berat' => 'nullable|numeric|min:0',
                'bagian.*.bahan.*.qty' => 'nullable|numeric|min:0',
                'tukang_cutting_id' => 'required|exists:tukang_cutting,id',
                'tukang_pola_id' => 'nullable|exists:tukang_pola,id',
                'bagian.*.bahan.*.skus' => 'nullable|array',
                'bagian.*.bahan.*.skus.*.sku_id' => 'required_with:bagian.*.bahan.*.skus|exists:product_lists,id',
                'bagian.*.bahan.*.skus.*.qty' => 'required_with:bagian.*.bahan.*.skus|numeric|min:0',
            ]);

            $mode = $validated['mode'] ?? 'biasa';
            $this->validateBagianKomponen($validated['bagian'] ?? [], $mode);

            if ($mode === 'potong_kecil') {
                $totalPcs = 0;
                foreach ($validated['bagian'] as $bagianData) {
                    foreach ($bagianData['bahan'] as $bahanData) {
                        if (!empty($bahanData['skus'])) {
                            foreach ($bahanData['skus'] as $sku) {
                                $totalPcs += (float) ($sku['qty'] ?? 0);
                            }
                        }
                    }
                }
                $validated['jumlah_asumsi_produk'] = (int) round($totalPcs);
            } else {
                $validated = $this->applyAutomaticProductFields($validated);
            }

            $productListSkuIds = [];
            foreach ($validated['bagian'] as $bagianData) {
                foreach ($bagianData['bahan'] as $bahanData) {
                    if (!empty($bahanData['skus'])) {
                        foreach ($bahanData['skus'] as $sku) {
                            $productListSkuIds[] = $sku['sku_id'];
                        }
                    }
                }
            }
            $productListSkuIds = array_values(array_unique($productListSkuIds));
            
            if (empty($productListSkuIds)) {
                throw ValidationException::withMessages([
                    'bagian' => ['Minimal pilih 1 SKU pada bahan produk.'],
                ]);
            }

            $this->validateProductListSkus((int) $validated['product_list_id'], $productListSkuIds);
            $validated['harga_per_pcs'] = $validated['satuan_harga'] === 'Lusin'
                ? $validated['harga_jasa'] / 12
                : $validated['harga_jasa'];
            DB::beginTransaction();
            // Update data utama SPK Cutting
            $validated['produk_id'] = null;

            $tanggalBuat = null;
            if (isset($validated['tanggal_buat'])) {
                $tanggalBuat = \Carbon\Carbon::parse($validated['tanggal_buat'])->format('Y-m-d H:i:s');
                unset($validated['tanggal_buat']);
            }

            $spk->update($validated);

            if ($tanggalBuat) {
                $spk->created_at = $tanggalBuat;
                $spk->save();
            }
            
            // Update SKU (sync untuk replace semua SKU yang ada)
            $spk->productListSkus()->sync($productListSkuIds);
            $this->syncSkuDistributions($spk, $productListSkuIds);
            
            // Clean up existing StokBahanKeluar and reset StokBahan status to 'tersedia'
            $stokBahanKeluarRecords = \App\Models\StokBahanKeluar::where('spk_cutting_id', $spk->id)->get();
            foreach ($stokBahanKeluarRecords as $item) {
                $stokBahan = \App\Models\StokBahan::find($item->stok_bahan_id);
                if ($stokBahan) {
                    $stokBahan->update(['status' => 'tersedia']);
                }
                $item->delete();
            }

            // Hapus bagian dan bahan lama
            foreach ($spk->bagian as $bagian) {
                $bagian->bahan()->delete();
            }
            $spk->bagian()->delete();

            // Buat bagian dan bahan baru
            foreach ($request->bagian as $bagianData) {
                $bagian = SpkCuttingBagian::create([
                    'spk_cutting_id' => $spk->id,
                    'nama_bagian' => $bagianData['nama_bagian'],
                ]);
                foreach ($bagianData['bahan'] as $bahanData) {
                    $bahan = SpkCuttingBahan::create($this->makeSpkCuttingBahanPayload($bagian->id, $bagianData, $bahanData));
                    
                    if (!empty($bahanData['skus'])) {
                        $skuData = [];
                        foreach ($bahanData['skus'] as $sku) {
                            $skuData[$sku['sku_id']] = ['qty' => $sku['qty']];
                        }
                        $bahan->skus()->sync($skuData);
                    }
                }
            }
            DB::commit();
            return response()->json([
                'message' => 'SPK Cutting berhasil diperbarui.',
                'data' => $spk->load(['bagian.bahan.bahan', 'bagian.bahan.aksesoris', 'productListSkus'])
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memperbarui data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function downloadQrCode($id)
    {
        try {
            // Load semua relasi yang diperlukan untuk PDF
            $spkCutting = SpkCutting::with([
                'productList:id,product,product_group,product_list_image_id,id_s,id_m,id_l,id_xl,pj_dress,pj_celana,pj_baju',
                'productList.productListImage:id,image_path',
                'productListSkus:id,product,sku_name,product_colour,product_size',
                'tukangCutting:id,nama_tukang_cutting',
                'tukangPola:id,nama',
                'bagian.bahan.bahan:id,nama_bahan',
                'bagian.bahan.aksesoris:id,nama_aksesoris',
                'bagian.bahan.skus'
            ])->findOrFail($id);
            if (!$spkCutting->barcode) {
                return response()->json([
                    'message' => 'Barcode belum tersedia untuk SPK Cutting ini'
                ], 404);
            }

            $productGroup = trim((string) ($spkCutting->productList->product_group ?? ''));
            $assignedVariants = collect();

            if ($productGroup !== '') {
                // Get chosen colors from SKUs
                $chosenColors = $spkCutting->productListSkus->pluck('product_colour')
                    ->map(fn($w) => trim(strtoupper((string)$w)))
                    ->filter()
                    ->unique()
                    ->toArray();

                $assignedVariants = ProductList::query()
                    ->with('productListImage:id,image_path')
                    ->where('product_group', $productGroup)
                    ->whereNotNull('product_colour')
                    ->where('product_colour', '!=', '')
                    ->orderBy('id')
                    ->get([
                        'id',
                        'product_colour',
                        'product_list_image_id',
                    ])
                    ->map(function ($row) {
                        return [
                            'warna' => trim((string) $row->product_colour),
                            'image_path' => $row->productListImage->image_path ?? null,
                        ];
                    })
                    ->filter(fn ($row) => $row['warna'] !== '');
                
                // Filter variants by chosen colors if available
                if (!empty($chosenColors)) {
                    $assignedVariants = $assignedVariants->filter(function ($row) use ($chosenColors) {
                        return in_array(strtoupper($row['warna']), $chosenColors);
                    });
                }

                $assignedVariants = $assignedVariants->unique('warna')
                    ->take(5)
                    ->values();
            }

            $format = request()->query('format', 'pdf');
            $viewData = [
                'spkCutting' => $spkCutting,
                'assignedVariants' => $assignedVariants,
            ];

            if ($format === 'html') {
                return view('pdf.barcode_spk_cutting', $viewData);
            }

            $pdf = Pdf::loadView('pdf.barcode_spk_cutting', $viewData)->setPaper('a4', 'portrait');

            $productList = $spkCutting->productList;
            $legacyProduk = $spkCutting->produk;
            $productTitle = strtoupper($productList?->product_group ?: ($productList?->product ?: ($legacyProduk?->nama_produk ?? '-')));

            $filenameProduct = preg_replace('/[^A-Za-z0-9\-\_]/', '', str_replace(' ', '-', $productTitle));
            $filename = "SPK-{$filenameProduct}-{$spkCutting->id_spk_cutting}-{$spkCutting->barcode}.pdf";
            return $pdf->download($filename);
        } catch (\Exception $e) {
            Log::error('Error downloading QR code SPK Cutting: ' . $e->getMessage());
            return response()->json([
                'message' => 'Gagal membuat QR code',
                'error' => $e->getMessage()

            ], 500);
        }
    }

 
    public function exportExcel(Request $request)
    {
        try {
            $statusFilter = $request->get('status', 'all');
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
            $fileName = 'spk-cutting-' . date('Y-m-d-His') . '.xlsx';

            return Excel::download(new SpkCuttingExport($statusFilter, $startDate, $endDate), $fileName);
        } catch (\Exception $e) {
            Log::error('Error exporting SPK Cutting to Excel: ' . $e->getMessage());

            return response()->json([
                'message' => 'Gagal export data ke Excel',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $spk = SpkCutting::findOrFail($id);

            DB::beginTransaction();

            // Clean up existing StokBahanKeluar and reset StokBahan status to 'tersedia'
            $stokBahanKeluarRecords = \App\Models\StokBahanKeluar::where('spk_cutting_id', $spk->id)->get();
            foreach ($stokBahanKeluarRecords as $item) {
                $stokBahan = \App\Models\StokBahan::find($item->stok_bahan_id);
                if ($stokBahan) {
                    $stokBahan->update(['status' => 'tersedia']);
                }
                $item->delete();
            }

            // Hapus bagian dan bahan terkait
            foreach ($spk->bagian as $bagian) {
                $bagian->bahan()->delete();
            }
            $spk->bagian()->delete();

            $spk->delete();

            DB::commit();

            return response()->json([
                'message' => 'SPK Cutting berhasil dihapus'
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            if ($e->getCode() == 23000) {
                return response()->json([
                    'message' => 'SPK Cutting tidak dapat dihapus karena sudah berlanjut ke tahap selanjutnya (SPK Jasa, CMT, dll).',
                    'error' => $e->getMessage()
                ], 400);
            }
            return response()->json([
                'message' => 'Gagal menghapus SPK Cutting (Database Error).',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menghapus SPK Cutting',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

