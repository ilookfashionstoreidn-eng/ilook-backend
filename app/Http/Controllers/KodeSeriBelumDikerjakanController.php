<?php

namespace App\Http\Controllers;

use App\Models\SpkCmt;
use App\Models\SpkCmtWarna;
use App\Models\SpkCuttingDistribusi;
use App\Models\SpkJasa;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class KodeSeriBelumDikerjakanController extends Controller
{
    public function index()
    {
        // Ambil semua SpkCuttingDistribusi yang belum memiliki SPK CMT
        // Cek apakah ada di tabel spk_cmt dengan source_type='cutting' dan source_id=id
        $cuttingIdsSudahCmt = SpkCmt::where('source_type', 'cutting')->pluck('source_id')->toArray();
        $cuttingBelumCmt = SpkCuttingDistribusi::with([
            'spkCutting.produk'
        ])
            ->whereNotIn('id', $cuttingIdsSudahCmt)
            ->get();

        // Ambil semua SpkJasa yang belum memiliki SPK CMT
        $jasaIdsSudahCmt = SpkCmt::where('source_type', 'jasa')->pluck('source_id')->toArray();
        $jasaBelumCmt = SpkJasa::with([
            'spkCuttingDistribusi.spkCutting.produk'
        ])
            ->whereNotIn('id', $jasaIdsSudahCmt)
            ->get();

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
        foreach ($cuttingBelumCmt as $distribusi) {
            $kodeSeri = $distribusi->kode_seri;
            $produk = $distribusi->spkCutting->produk ?? null;
            $deadline = $distribusi->spkCutting->tanggal_batas_kirim ?? null;
            $jumlahQty = $distribusi->jumlah_produk ?? 0;

            if (!$kodeSeri) {
                continue;
            }

            // Cek deadline
            $isOverDeadline = false;
            if ($deadline) {
                $deadlineDate = Carbon::parse($deadline);
                $isOverDeadline = $deadlineDate->lt($today);
            }

            // Grouping berdasarkan kode seri
            if (!isset($groupedBySeri[$kodeSeri])) {
                $groupedBySeri[$kodeSeri] = [
                    'kode_seri' => $kodeSeri,
                    'nama_produk' => $produk->nama_produk ?? 'Produk Tidak Diketahui',
                    'deadline' => $deadline,
                    'jumlah' => 0,
                    'distribusi_list' => [],
                ];
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

        // Proses Jasa
        foreach ($jasaBelumCmt as $jasa) {
            if (!$jasa->spkCuttingDistribusi) {
                continue;
            }

            $distribusi = $jasa->spkCuttingDistribusi;
            $kodeSeri = $distribusi->kode_seri;
            $produk = $distribusi->spkCutting->produk ?? null;
            $deadline = $jasa->deadline ?? null;
            $jumlahQty = $jasa->jumlah ?? 0;

            if (!$kodeSeri) {
                continue;
            }

            // Cek deadline
            $isOverDeadline = false;
            if ($deadline) {
                $deadlineDate = Carbon::parse($deadline);
                $isOverDeadline = $deadlineDate->lt($today);
            }

            // Grouping berdasarkan kode seri
            if (!isset($groupedBySeri[$kodeSeri])) {
                $groupedBySeri[$kodeSeri] = [
                    'kode_seri' => $kodeSeri,
                    'nama_produk' => $produk->nama_produk ?? 'Produk Tidak Diketahui',
                    'deadline' => $deadline,
                    'jumlah' => 0,
                    'distribusi_list' => [],
                ];
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

            $filteredData[] = $item;
        }

        // Convert distribusi_list kembali ke array indexed
        foreach ($filteredData as &$item) {
            $item['distribusi_list'] = array_values($item['distribusi_list']);
        }

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

        return response()->json([
            'data' => $data,
            'statistics' => $statistics,
        ], 200);
    }
}
