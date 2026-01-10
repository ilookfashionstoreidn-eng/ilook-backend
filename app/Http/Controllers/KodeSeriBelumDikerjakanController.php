<?php

namespace App\Http\Controllers;

use App\Models\SpkCmt;
use App\Models\SpkCmtWarna;
use App\Models\SpkCuttingDistribusi;
use App\Models\SpkJasa;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KodeSeriBelumDikerjakanController extends Controller
{
    public function index()
    {
        // Ambil semua kode seri yang sudah dibuatkan SPK CMT (baik dari cutting maupun jasa)
        $kodeSeriSudahCmt = [];

        // Ambil kode seri dari SPK CMT yang dibuat dari cutting
        $spkCmtCutting = SpkCmt::where('source_type', 'cutting')
            ->with('spkCuttingDistribusi')
            ->get();
        foreach ($spkCmtCutting as $spk) {
            if ($spk->spkCuttingDistribusi && !empty($spk->spkCuttingDistribusi->kode_seri)) {
                $kodeSeri = trim($spk->spkCuttingDistribusi->kode_seri);
                if (!empty($kodeSeri)) {
                    $kodeSeriSudahCmt[] = $kodeSeri;
                }
            }
        }

        // Ambil kode seri dari SPK CMT yang dibuat dari jasa
        $spkCmtJasa = SpkCmt::where('source_type', 'jasa')
            ->with('spkJasa.spkCuttingDistribusi')
            ->get();
        foreach ($spkCmtJasa as $spk) {
            if (
                $spk->spkJasa
                && $spk->spkJasa->spkCuttingDistribusi
                && !empty($spk->spkJasa->spkCuttingDistribusi->kode_seri)
            ) {
                $kodeSeri = trim($spk->spkJasa->spkCuttingDistribusi->kode_seri);
                if (!empty($kodeSeri)) {
                    $kodeSeriSudahCmt[] = $kodeSeri;
                }
            }
        }

        // Hapus duplikasi dan konversi ke array unique
        $kodeSeriSudahCmt = array_unique($kodeSeriSudahCmt);
        $kodeSeriSudahCmt = array_values($kodeSeriSudahCmt);

        Log::info('Kode seri yang sudah dibuatkan SPK CMT: ', $kodeSeriSudahCmt);

        // Ambil semua SpkCuttingDistribusi yang kode seri-nya belum dibuatkan SPK CMT
        $cuttingQuery = SpkCuttingDistribusi::with([
            'spkCutting.produk'
        ])
            ->whereNotNull('kode_seri')
            ->where('kode_seri', '!=', '');

        if (!empty($kodeSeriSudahCmt)) {
            $cuttingQuery->whereNotIn('kode_seri', $kodeSeriSudahCmt);
        }

        $cuttingBelumCmt = $cuttingQuery->orderBy('id', 'asc')->get();

        // Ambil semua SpkJasa yang kode seri-nya belum dibuatkan SPK CMT
        $jasaQuery = SpkJasa::with([
            'spkCuttingDistribusi.spkCutting.produk'
        ]);

        $jasaQuery->whereHas('spkCuttingDistribusi', function ($query) use ($kodeSeriSudahCmt) {
            $query->whereNotNull('kode_seri')
                ->where('kode_seri', '!=', '');
            if (!empty($kodeSeriSudahCmt)) {
                $query->whereNotIn('kode_seri', $kodeSeriSudahCmt);
            }
        });

        $jasaBelumCmt = $jasaQuery->get();

        // Debug: Log untuk melihat data yang diambil
        Log::info('Cutting belum CMT count: ' . $cuttingBelumCmt->count());
        Log::info('Jasa belum CMT count: ' . $jasaBelumCmt->count());

        $cuttingKodeSeriDebug = [];
        foreach ($cuttingBelumCmt as $c) {
            if ($c->kode_seri) {
                $cuttingKodeSeriDebug[] = ['id' => $c->id, 'kode_seri' => $c->kode_seri];
            }
        }
        Log::info('Cutting belum CMT - Detail: ', $cuttingKodeSeriDebug);

        $jasaKodeSeri = [];
        foreach ($jasaBelumCmt as $j) {
            if ($j->spkCuttingDistribusi && $j->spkCuttingDistribusi->kode_seri) {
                $jasaKodeSeri[] = ['jasa_id' => $j->id, 'distribusi_id' => $j->spkCuttingDistribusi->id, 'kode_seri' => $j->spkCuttingDistribusi->kode_seri];
            }
        }
        Log::info('Jasa belum CMT - Detail: ', $jasaKodeSeri);


        // Kelompokkan berdasarkan kode seri
        $groupedBySeri = [];
        $statistics = [
            'jumlah_spk' => 0,
            'jumlah_produk' => 0,
            'jumlah_qty' => 0,
            'jumlah_over_deadline' => 0,
            'jumlah_belum_deadline' => 0,
            'count_cutting' => 0,
            'count_jasa' => 0,
        ];

        $today = Carbon::today();
        $uniqueProduk = [];

        // Proses Cutting Distribusi
        $cuttingKodeSeri = [];
        foreach ($cuttingBelumCmt as $distribusi) {
            $kodeSeri = $distribusi->kode_seri;
            $produk = $distribusi->spkCutting->produk ?? null;
            $deadline = $distribusi->spkCutting->tanggal_batas_kirim ?? null;
            $jumlahQty = $distribusi->jumlah_produk ?? 0;

            if (!$kodeSeri) {
                continue;
            }

            $cuttingKodeSeri[] = $kodeSeri;

            // Cek deadline
            $isOverDeadline = false;
            if ($deadline) {
                $deadlineDate = Carbon::parse($deadline);
                $isOverDeadline = $deadlineDate->lt($today);
            }

            // Grouping berdasarkan kode seri (gunakan kode seri sebagai key untuk menghindari duplikasi entry)
            if (!isset($groupedBySeri[$kodeSeri])) {
                $groupedBySeri[$kodeSeri] = [
                    'kode_seri' => $kodeSeri,
                    'nama_produk' => $produk->nama_produk ?? 'Produk Tidak Diketahui',
                    'deadline' => $deadline,
                    'jumlah' => 0,
                    'distribusi_list' => [],
                ];
            } else {
                // Update nama produk jika belum ada
                if (!isset($groupedBySeri[$kodeSeri]['nama_produk']) || $groupedBySeri[$kodeSeri]['nama_produk'] === 'Produk Tidak Diketahui') {
                    if ($produk) {
                        $groupedBySeri[$kodeSeri]['nama_produk'] = $produk->nama_produk;
                    }
                }
            }

            $groupedBySeri[$kodeSeri]['jumlah'] += $jumlahQty;
            $groupedBySeri[$kodeSeri]['distribusi_list'][] = [
                'id_distribusi' => $distribusi->id,
                'type' => 'cutting',
                'deadline' => $deadline,
                'jumlah_qty' => $jumlahQty,
            ];

            // Update deadline jika ada yang lebih dekat
            if ($deadline && (!$groupedBySeri[$kodeSeri]['deadline'] || Carbon::parse($deadline)->lt(Carbon::parse($groupedBySeri[$kodeSeri]['deadline'])))) {
                $groupedBySeri[$kodeSeri]['deadline'] = $deadline;
            }

            // Update statistics
            $statistics['jumlah_spk']++;
            $statistics['jumlah_qty'] += $jumlahQty;

            // Hitung produk unik berdasarkan kode seri
            if ($produk && !isset($uniqueProduk[$kodeSeri])) {
                $uniqueProduk[$kodeSeri] = true;
                $statistics['jumlah_produk']++;
            }
        }

        Log::info('Cutting belum CMT - Kode Seri: ', array_unique($cuttingKodeSeri));

        // Proses Jasa
        foreach ($jasaBelumCmt as $jasa) {
            if (!$jasa->spkCuttingDistribusi) {
                Log::warning('Jasa ID ' . $jasa->id . ' tidak punya distribusi');
                continue;
            }

            $distribusi = $jasa->spkCuttingDistribusi;
            $kodeSeri = $distribusi->kode_seri;
            $produk = $distribusi->spkCutting->produk ?? null;
            // ✅ Ambil deadline dari cutting, bukan dari jasa
            $deadline = $distribusi->spkCutting->tanggal_batas_kirim ?? null;
            $jumlahQty = $jasa->jumlah ?? 0;

            if (!$kodeSeri) {
                Log::warning('Jasa ID ' . $jasa->id . ' - Distribusi ID ' . $distribusi->id . ' tidak punya kode seri');
                continue;
            }

            Log::info('Proses Jasa - Kode Seri: ' . $kodeSeri . ', Jasa ID: ' . $jasa->id);

            // Cek deadline
            $isOverDeadline = false;
            if ($deadline) {
                $deadlineDate = Carbon::parse($deadline);
                $isOverDeadline = $deadlineDate->lt($today);
            }

            // Grouping berdasarkan kode seri (gunakan kode seri sebagai key untuk menghindari duplikasi entry)
            if (!isset($groupedBySeri[$kodeSeri])) {
                $groupedBySeri[$kodeSeri] = [
                    'kode_seri' => $kodeSeri,
                    'nama_produk' => $produk->nama_produk ?? 'Produk Tidak Diketahui',
                    'deadline' => $deadline,
                    'jumlah' => 0,
                    'distribusi_list' => [],
                ];
            } else {
                // Update nama produk jika belum ada
                if (!isset($groupedBySeri[$kodeSeri]['nama_produk']) || $groupedBySeri[$kodeSeri]['nama_produk'] === 'Produk Tidak Diketahui') {
                    if ($produk) {
                        $groupedBySeri[$kodeSeri]['nama_produk'] = $produk->nama_produk;
                    }
                }
            }

            $groupedBySeri[$kodeSeri]['jumlah'] += $jumlahQty;
            $groupedBySeri[$kodeSeri]['distribusi_list'][] = [
                'id_distribusi' => $distribusi->id,
                'id_jasa' => $jasa->id,
                'type' => 'jasa',
                'deadline' => $deadline,
                'jumlah_qty' => $jumlahQty,
            ];

            // Update deadline jika ada yang lebih dekat
            if ($deadline && (!$groupedBySeri[$kodeSeri]['deadline'] || Carbon::parse($deadline)->lt(Carbon::parse($groupedBySeri[$kodeSeri]['deadline'])))) {
                $groupedBySeri[$kodeSeri]['deadline'] = $deadline;
            }

            // Update statistics
            $statistics['jumlah_spk']++;
            $statistics['jumlah_qty'] += $jumlahQty;

            // Hitung produk unik berdasarkan kode seri
            if ($produk && !isset($uniqueProduk[$kodeSeri])) {
                $uniqueProduk[$kodeSeri] = true;
                $statistics['jumlah_produk']++;
            }
        }

        // Filter: Jika kode seri punya cutting dan jasa, hanya tampilkan jasa saja
        $filteredData = [];
        foreach ($groupedBySeri as $kodeSeri => $item) {
            $hasCutting = false;
            $hasJasa = false;

            foreach ($item['distribusi_list'] as $dist) {
                if ($dist['type'] === 'cutting') {
                    $hasCutting = true;
                } elseif ($dist['type'] === 'jasa') {
                    $hasJasa = true;
                }
            }

            // Jika punya cutting dan jasa, hanya ambil jasa
            if ($hasCutting && $hasJasa) {
                $item['distribusi_list'] = array_filter($item['distribusi_list'], function ($dist) {
                    return $dist['type'] === 'jasa';
                });
                // Recalculate jumlah
                $item['jumlah'] = array_sum(array_column($item['distribusi_list'], 'jumlah_qty'));
            }

            // Pastikan distribusi_list tidak kosong
            if (!empty($item['distribusi_list'])) {
                $filteredData[] = $item;
            }
        }

        // Convert distribusi_list kembali ke array indexed dan pastikan tidak ada duplikasi
        $uniqueFilteredData = [];
        $seenKodeSeri = [];

        foreach ($filteredData as $item) {
            $kodeSeri = $item['kode_seri'];

            // Skip jika kode seri sudah ada (hindari duplikasi)
            if (isset($seenKodeSeri[$kodeSeri])) {
                Log::warning('Duplicate kode seri ditemukan: ' . $kodeSeri);
                continue;
            }

            $seenKodeSeri[$kodeSeri] = true;
            $item['distribusi_list'] = array_values($item['distribusi_list']);
            $uniqueFilteredData[] = $item;
        }

        $filteredData = $uniqueFilteredData;

        // Hitung count cutting dan jasa dari data yang sudah di-filter (sesuai dengan yang ditampilkan di tabel)
        $countCutting = 0;
        $countJasa = 0;

        foreach ($filteredData as $item) {
            $hasCutting = false;
            $hasJasa = false;

            foreach ($item['distribusi_list'] as $dist) {
                if ($dist['type'] === 'cutting') {
                    $hasCutting = true;
                } elseif ($dist['type'] === 'jasa') {
                    $hasJasa = true;
                }
            }

            if ($hasCutting) $countCutting++;
            if ($hasJasa) $countJasa++;
        }

        $statistics['count_cutting'] = $countCutting;
        $statistics['count_jasa'] = $countJasa;

        // Sort berdasarkan deadline (terdekat dulu)
        usort($filteredData, function ($a, $b) {
            if (!$a['deadline']) return 1;
            if (!$b['deadline']) return -1;
            return Carbon::parse($a['deadline'])->gt(Carbon::parse($b['deadline'])) ? 1 : -1;
        });

        $data = $filteredData;

        // Hitung ulang over deadline dan belum deadline berdasarkan kode seri (bukan per SPK)
        $statistics['jumlah_over_deadline'] = 0;
        $statistics['jumlah_belum_deadline'] = 0;

        foreach ($data as $item) {
            if ($item['deadline']) {
                $deadline = Carbon::parse($item['deadline']);
                if ($deadline->lt($today)) {
                    $statistics['jumlah_over_deadline']++;
                } else {
                    $statistics['jumlah_belum_deadline']++;
                }
            } else {
                $statistics['jumlah_belum_deadline']++;
            }
        }

        // Hitung ulang statistik setelah filtering
        $statistics['jumlah_spk'] = count($data);
        $statistics['jumlah_qty'] = array_sum(array_column($data, 'jumlah'));

        // Debug: Log semua kode seri yang akan dikembalikan dengan detail
        $finalKodeSeriDetail = [];
        foreach ($data as $item) {
            $finalKodeSeriDetail[] = [
                'kode_seri' => $item['kode_seri'],
                'jumlah_distribusi' => count($item['distribusi_list'] ?? []),
                'distribusi_list' => $item['distribusi_list'] ?? []
            ];
        }
        Log::info('Final Data - Detail: ', $finalKodeSeriDetail);

        return response()->json([
            'data' => $data,
            'statistics' => $statistics,
        ], 200);
    }
}
