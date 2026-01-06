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
        // Ambil semua SPK CMT yang belum dikerjakan (status bukan Completed)
        $spkBelumDikerjakan = SpkCmt::with([
            'warna',
            'penjahit',
            'spkCuttingDistribusi.spkCutting.produk',
            'spkJasa.spkCuttingDistribusi.spkCutting.produk',
        ])
            ->where('status', '!=', 'Completed')
            ->orderBy('deadline', 'asc')
            ->get();

        // Kelompokkan berdasarkan kode seri
        $groupedBySeri = [];
        $statistics = [
            'jumlah_spk' => 0,
            'jumlah_produk' => 0,
            'jumlah_qty' => 0,
            'jumlah_over_deadline' => 0,
            'jumlah_belum_deadline' => 0,
        ];

        $today = Carbon::today();
        $uniqueProduk = [];

        foreach ($spkBelumDikerjakan as $spk) {
            // Ambil kode seri berdasarkan source_type
            $kodeSeri = null;
            $produk = null;

            if ($spk->source_type === 'cutting' && $spk->source_id) {
                $distribusi = SpkCuttingDistribusi::with('spkCutting.produk')->find($spk->source_id);
                if ($distribusi) {
                    $kodeSeri = $distribusi->kode_seri;
                    $produk = $distribusi->spkCutting->produk ?? null;
                }
            } elseif ($spk->source_type === 'jasa' && $spk->source_id) {
                $jasa = SpkJasa::with('spkCuttingDistribusi.spkCutting.produk')->find($spk->source_id);
                if ($jasa && $jasa->spkCuttingDistribusi) {
                    $kodeSeri = $jasa->spkCuttingDistribusi->kode_seri;
                    $produk = $jasa->spkCuttingDistribusi->spkCutting->produk ?? null;
                }
            }

            // Skip jika tidak ada kode seri
            if (!$kodeSeri) {
                continue;
            }

            // Hitung jumlah qty dari warna
            $jumlahQty = $spk->warna->sum('qty');

            // Cek deadline
            $isOverDeadline = false;
            if ($spk->deadline) {
                $deadline = Carbon::parse($spk->deadline);
                $isOverDeadline = $deadline->lt($today);
            }

            // Grouping berdasarkan kode seri
            if (!isset($groupedBySeri[$kodeSeri])) {
                $groupedBySeri[$kodeSeri] = [
                    'kode_seri' => $kodeSeri,
                    'nama_produk' => $produk->nama_produk ?? 'Produk Tidak Diketahui',
                    'deadline' => $spk->deadline,
                    'jumlah' => 0,
                    'spk_list' => [],
                ];
            }

            $groupedBySeri[$kodeSeri]['jumlah'] += $jumlahQty;
            $groupedBySeri[$kodeSeri]['spk_list'][] = [
                'id_spk' => $spk->id_spk,
                'status' => $spk->status,
                'deadline' => $spk->deadline,
                'jumlah_qty' => $jumlahQty,
                'nama_penjahit' => $spk->penjahit->nama_penjahit ?? '-',
            ];

            // Update deadline jika ada yang lebih dekat
            if ($spk->deadline && (!$groupedBySeri[$kodeSeri]['deadline'] || Carbon::parse($spk->deadline)->lt(Carbon::parse($groupedBySeri[$kodeSeri]['deadline'])))) {
                $groupedBySeri[$kodeSeri]['deadline'] = $spk->deadline;
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

        // Convert groupedBySeri ke array values untuk response
        $data = array_values($groupedBySeri);

        // Sort berdasarkan deadline (terdekat dulu)
        usort($data, function ($a, $b) {
            if (!$a['deadline']) return 1;
            if (!$b['deadline']) return -1;
            return Carbon::parse($a['deadline'])->gt(Carbon::parse($b['deadline'])) ? 1 : -1;
        });

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

        return response()->json([
            'data' => $data,
            'statistics' => $statistics,
        ], 200);
    }
}
