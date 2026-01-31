<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Produk;
use App\Models\SpkCutting;
use App\Models\SpkCuttingBagian;
use App\Models\SpkCuttingBahan;
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

    private function generateSpkNumber($tukangCuttingId)

    {
        $tukangCutting = TukangCutting::find($tukangCuttingId);

        if (!$tukangCutting) {
            throw new \Exception('Tukang cutting tidak ditemukan');
        }

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


    public function index(Request $request)
    {
        $query = SpkCutting::with([
            'produk:id,nama_produk',
            'skus:id,produk_id,sku', 
            'bagian.bahan.bahan',
            'tukangCutting:id,nama_tukang_cutting',
            'tukangPola:id,nama',
        ]);

        // Filter berdasarkan status jika ada
        if ($request->has('status') && $request->status !== '' && $request->status !== 'all') {
            $query->where('status_cutting', $request->status);
        }

        // Filter berdasarkan jenis_spk jika ada
        if ($request->filled('jenis_spk')) {
            $query->where('jenis_spk', $request->jenis_spk);
        }

        // Filter berdasarkan tanggal dibuat (created_at) jika ada
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $data = $query->get();

        // Hitung summary per status (hilangkan Pending)
        // Summary tetap menghormati filter tanggal, tetapi tidak terpengaruh oleh filter status
        $summaryBaseQuery = SpkCutting::query();

        // Filter berdasarkan jenis_spk jika ada (untuk summary juga)
        if ($request->filled('jenis_spk')) {
            $summaryBaseQuery->where('jenis_spk', $request->jenis_spk);
        }

        if ($request->filled('start_date')) {
            $summaryBaseQuery->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $summaryBaseQuery->whereDate('created_at', '<=', $request->end_date);
        }

        // Hitung summary per status
        $summaryAll = (clone $summaryBaseQuery)->count();
        $summaryBelumDiambil = (clone $summaryBaseQuery)->where('status_cutting', 'belum_diambil');
        $summarySudahDiambil = (clone $summaryBaseQuery)->where('status_cutting', 'sudah_diambil')->count();
        $summarySelesai = (clone $summaryBaseQuery)->where('status_cutting', 'selesai')->count();

        // Hitung total jumlah asumsi produk untuk SPK yang belum_diambil
        $totalAsumsiBelumDiambil = (int)((clone $summaryBelumDiambil)->sum('jumlah_asumsi_produk') ?? 0);
        $countBelumDiambil = $summaryBelumDiambil->count();

        // Hitung statistik berdasarkan periode (untuk card target)
        // Status filter untuk progress cards (default: 'belum_diambil' jika tidak ada atau 'all')
        $progressStatusFilter = $request->get('progress_status', 'belum_diambil');
        if ($progressStatusFilter === 'all' || $progressStatusFilter === '') {
            $progressStatusFilter = 'belum_diambil'; // Default ke belum_diambil jika all
        }

        $weeklyStart = $request->get('weekly_start');
        $weeklyEnd = $request->get('weekly_end');
        $dailyDate = $request->get('daily_date');

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
                ->where('status_cutting', $progressStatusFilter)
                ->whereDate('created_at', '>=', $weeklyStart)
                ->whereDate('created_at', '<=', $weeklyEnd);
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
                ->where('status_cutting', $progressStatusFilter)
                ->whereDate('created_at', $dailyDate);
            $inProgressDaily['count'] = $dailyQuery->count();
            $inProgressDaily['total_asumsi_produk'] = (int)($dailyQuery->sum('jumlah_asumsi_produk') ?? 0);
            $inProgressDaily['target'] = $dailyTarget;
            $inProgressDaily['remaining'] = max(0, $dailyTarget - $inProgressDaily['total_asumsi_produk']);
        } else {
            $inProgressDaily['target'] = $dailyTarget;
            $inProgressDaily['remaining'] = $dailyTarget;
        }

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
        ];

        return response()->json([
            'data' => $data,
            'summary' => $summary,
        ]);
    }


    public function show($id)
    {
        $spk = SpkCutting::with('produk.markeranProduk', 'bagian.bahan.bahan', 'tukangPola:id,nama', 'skus:id,produk_id,sku,warna,ukuran')->find($id);
        if (!$spk) {
            return response()->json(['message' => 'SPK Cutting tidak ditemukan'], 404);
        }
        return response()->json(['data' => $spk]);
    }


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

        $validated = $request->validate([
            'produk_id' => 'required|exists:produk,id',
            'tanggal_batas_kirim' => 'required|date',
            'harga_jasa' => 'required|numeric|min:0',
            'satuan_harga' => 'required|in:Lusin,Pcs',
            'keterangan' => 'nullable|string',
            'jumlah_asumsi_produk' => 'nullable|integer|min:0',
            'jenis_spk' => 'nullable|string|in:Terjual,Fittingan,Habisin Bahan',
            'bagian' => 'required|array',
            'bagian.*.nama_bagian' => 'required|string',
            'bagian.*.bahan' => 'required|array',
            'bagian.*.bahan.*.bahan_id' => 'required|exists:bahan,id',
            'bagian.*.bahan.*.warna' => 'nullable|string|max:255',
            'bagian.*.bahan.*.berat' => 'nullable|numeric|min:0',
            'bagian.*.bahan.*.qty' => 'required|numeric|min:1',
            'tukang_cutting_id' => 'required|exists:tukang_cutting,id',
            'tukang_pola_id' => 'nullable|exists:tukang_pola,id',
            'produk_sku_ids' => 'required|array|min:1',
            'produk_sku_ids.*' => 'exists:produk_sku,id',

        ]);

        // 🔒 VALIDASI SKU MILIK PRODUK
        // ===============================
        $skuCount = \App\Models\ProdukSku::where('produk_id', $validated['produk_id'])
            ->whereIn('id', $validated['produk_sku_ids'])
            ->count();

        if ($skuCount !== count($validated['produk_sku_ids'])) {
            return response()->json([
                'message' => 'Terdapat SKU yang tidak sesuai dengan produk',
            ], 422);
        }

        // ===============================
        // GENERATE NO SPK
        // ===============================
        $validated['id_spk_cutting'] = $this->generateSpkNumber($validated['tukang_cutting_id']);

        $exists = SpkCutting::where('id_spk_cutting', $validated['id_spk_cutting'])->exists();
        if ($exists) {
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

            $nextNumber = $lastSpk
                ? ((int) explode('-', $lastSpk->id_spk_cutting)[1]) + 1
                : 1;

            $validated['id_spk_cutting'] = $inisial . '-' . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);
        }

       
        $validated['harga_per_pcs'] = $validated['satuan_harga'] === 'Lusin'
            ? $validated['harga_jasa'] / 12
            : $validated['harga_jasa'];

       $validated['status_cutting'] = 'belum_diambil';

        $validated['barcode'] = 'SPKC-' . strtoupper(uniqid());

        DB::beginTransaction();

        
        $spk = SpkCutting::create($validated);


        $spk->skus()->attach($validated['produk_sku_ids']);

      
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
            'message' => 'SPK Cutting berhasil ditambahkan',
            'data' => $spk->load([
                'skus',
                'bagian.bahan',
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
                'produk_id' => 'required|exists:produk,id',
                'tanggal_batas_kirim' => 'required|date',
                'harga_jasa' => 'required|numeric|min:0',
                'satuan_harga' => 'required|in:Lusin,Pcs',
                'keterangan' => 'nullable|string',
                'jumlah_asumsi_produk' => 'nullable|integer|min:0',
                'jenis_spk' => 'nullable|string|in:Terjual,Fittingan,Habisin Bahan',
                'bagian' => 'required|array',
                'bagian.*.nama_bagian' => 'required|string',
                'bagian.*.bahan' => 'required|array',
                'bagian.*.bahan.*.bahan_id' => 'required|exists:bahan,id',
                'bagian.*.bahan.*.warna' => 'nullable|string|max:255',
                'bagian.*.bahan.*.berat' => 'nullable|numeric|min:0',
                'bagian.*.bahan.*.qty' => 'required|numeric|min:1',
                'tukang_cutting_id' => 'required|exists:tukang_cutting,id',
                'tukang_pola_id' => 'nullable|exists:tukang_pola,id',
                'produk_sku_ids' => 'required|array|min:1',
                'produk_sku_ids.*' => 'exists:produk_sku,id',
            ]);

            // 🔒 VALIDASI SKU MILIK PRODUK
            // ===============================
            $skuCount = \App\Models\ProdukSku::where('produk_id', $validated['produk_id'])
                ->whereIn('id', $validated['produk_sku_ids'])
                ->count();

            if ($skuCount !== count($validated['produk_sku_ids'])) {
                return response()->json([
                    'message' => 'Terdapat SKU yang tidak sesuai dengan produk',
                ], 422);
            }
            // ===============================
            $validated['harga_per_pcs'] = $validated['satuan_harga'] === 'Lusin'
                ? $validated['harga_jasa'] / 12
                : $validated['harga_jasa'];
            DB::beginTransaction();
            // Update data utama SPK Cutting
            $spk->update($validated);
            
            // Update SKU (sync untuk replace semua SKU yang ada)
            $spk->skus()->sync($validated['produk_sku_ids']);
            
            // Hapus bagian dan bahan lama
            foreach ($spk->bagian as $bagian) {
                // Hapus semua bahan yang terkait dengan bagian ini
                $bagian->bahan()->delete();
            }
            // Hapus semua bagian yang terkait dengan SPK Cutting ini
            $spk->bagian()->delete();
            // Buat bagian dan bahan baru
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
                'message' => 'SPK Cutting berhasil diperbarui.',
                'data' => $spk->load(['bagian.bahan.bahan', 'skus'])
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
                'produk:id,nama_produk,gambar_produk',
                'tukangCutting:id,nama_tukang_cutting',
                'bagian.bahan.bahan:id,nama_bahan'
            ])->findOrFail($id);
            if (!$spkCutting->barcode) {
                return response()->json([
                    'message' => 'Barcode belum tersedia untuk SPK Cutting ini'
                ], 404);
            }
            // Generate PDF (QR code akan di-generate di view menggunakan DNS2D)
            // Ukuran kertas 105mm x 148.5mm (dalam points: 105mm = 297.638 points, 148.5mm = 421.245 points)
            $pdf = Pdf::loadView('pdf.barcode_spk_cutting', [
                'spkCutting' => $spkCutting,
            ])->setPaper([0, 0, 297.638, 421.245], 'portrait');
            return $pdf->download("qr-code-spk-cutting-{$spkCutting->id_spk_cutting}.pdf");
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
}
