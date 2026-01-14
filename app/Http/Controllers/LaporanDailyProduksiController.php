<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\SpkCutting;
use App\Models\HasilCutting;
use App\Models\SpkCmt;
use App\Models\SpkCuttingDistribusi;
use App\Models\SpkJasa;
use App\Models\Pengiriman;
use App\Models\TukangCutting;

class LaporanDailyProduksiController extends Controller
{
    public function index(Request $request)
    {
        // Ambil tanggal dari request, default hari ini
        $tanggal = $request->input('tanggal', Carbon::today()->toDateString());
        $tanggalCarbon = Carbon::parse($tanggal);

        // Hitung awal dan akhir minggu ini (untuk hasil cuttingan minggu ini)
        $mingguIniStart = Carbon::now()->startOfWeek();
        $mingguIniEnd = Carbon::now()->endOfWeek();

        // Data Cutting Produk
        $cuttingData = $this->getCuttingData($tanggalCarbon, $mingguIniStart, $mingguIniEnd);

        // Data CMT
        $cmtData = $this->getCmtData($tanggalCarbon, $mingguIniStart, $mingguIniEnd);

        return response()->json([
            'data' => [
                'tanggal' => $tanggal,
                'cutting' => $cuttingData,
                'cmt' => $cmtData,
            ]
        ], 200);
    }

    private function getCuttingData($tanggal, $mingguIniStart, $mingguIniEnd)
    {
        // 1. SPK Belum Potong
        // SPK yang belum ada hasil cutting-nya (belum ada di tabel hasil_cutting)
        $spkBelumPotong = SpkCutting::whereNotIn('id', function ($query) {
            $query->select('spk_cutting_id')
                ->from('hasil_cutting')
                ->distinct();
        })
        ->where('status_cutting', '!=', 'selesai')
        ->get();

        $jmlModelBelumPotong = $spkBelumPotong->groupBy('produk_id')->count();
        $jmlPcsBelumPotong = $spkBelumPotong->sum('jumlah_asumsi_produk');

        // Turunan produk berdasarkan jenis_spk
        $turunanProduk = [
            'terjual' => $spkBelumPotong->where('jenis_spk', 'Terjual')->sum('jumlah_asumsi_produk'),
            'fittingan_baru' => $spkBelumPotong->where('jenis_spk', 'Fittingan')->sum('jumlah_asumsi_produk'),
            'habisin_bahan' => $spkBelumPotong->where('jenis_spk', 'Habisin Bahan')->sum('jumlah_asumsi_produk'),
        ];

        // 2. Hasil Cuttingan Minggu Ini
        $hasilCuttingMingguIni = HasilCutting::select(
            'tukang_cutting.nama_tukang_cutting',
            DB::raw('SUM(hasil_cutting.total_produk) as jml_pcs'),
            DB::raw('COUNT(DISTINCT hasil_cutting.spk_cutting_id) as jml_spk'),
            DB::raw('COUNT(DISTINCT spk_cutting.tukang_cutting_id) as jml_tim')
        )
        ->join('spk_cutting', 'hasil_cutting.spk_cutting_id', '=', 'spk_cutting.id')
        ->join('tukang_cutting', 'spk_cutting.tukang_cutting_id', '=', 'tukang_cutting.id')
        ->whereBetween('hasil_cutting.created_at', [$mingguIniStart, $mingguIniEnd])
        ->groupBy('tukang_cutting.nama_tukang_cutting', 'spk_cutting.tukang_cutting_id')
        ->get();

        $totalJmlPcsMingguIni = $hasilCuttingMingguIni->sum('jml_pcs');

        // Format data tukang cutting
        $tukangCuttingData = $hasilCuttingMingguIni->map(function ($item) {
            return [
                'nama' => $item->nama_tukang_cutting,
                'jml_pcs' => (int) $item->jml_pcs,
                'jml_spk' => (int) $item->jml_spk,
                'jml_tim' => (int) $item->jml_tim,
            ];
        })->values();

        return [
            'spk_belum_potong' => [
                'jml_model' => $jmlModelBelumPotong,
                'jml_pcs' => (int) $jmlPcsBelumPotong,
                'turunan_produk' => [
                    'terjual' => (int) $turunanProduk['terjual'],
                    'fittingan_baru' => (int) $turunanProduk['fittingan_baru'],
                    'habisin_bahan' => (int) $turunanProduk['habisin_bahan'],
                ],
            ],
            'hasil_cuttingan_minggu_ini' => [
                'total_jml_pcs' => (int) $totalJmlPcsMingguIni,
                'tukang_cutting' => $tukangCuttingData,
            ],
        ];
    }

    private function getCmtData($tanggal, $mingguIniStart, $mingguIniEnd)
    {
        // 1. Perkiraan PCS Waiting Bahan (input manual)
        // Ini bisa dari config atau tabel khusus, untuk sekarang kita set 0 atau bisa diambil dari request
        $perkiraanPcsWaitingBahan = 0; // TODO: Implementasi jika ada tabel/config untuk ini

        // 2. SPK Belum Ambil CMT
        // Distribusi yang belum dibuatkan SPK CMT (seperti di menu Kode Seri Belum Dikerjakan)
        
        // Ambil semua kode seri yang sudah dibuatkan SPK CMT
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
            if ($spk->spkJasa && $spk->spkJasa->spkCuttingDistribusi && !empty($spk->spkJasa->spkCuttingDistribusi->kode_seri)) {
                $kodeSeri = trim($spk->spkJasa->spkCuttingDistribusi->kode_seri);
                if (!empty($kodeSeri)) {
                    $kodeSeriSudahCmt[] = $kodeSeri;
                }
            }
        }
        
        // Hapus duplikasi
        $kodeSeriSudahCmt = array_unique($kodeSeriSudahCmt);
        
        // Ambil distribusi cutting yang belum dibuatkan SPK CMT
        $cuttingQuery = SpkCuttingDistribusi::with(['spkCutting.produk'])
            ->whereNotNull('kode_seri')
            ->where('kode_seri', '!=', '');
        
        if (!empty($kodeSeriSudahCmt)) {
            $cuttingQuery->whereNotIn('kode_seri', $kodeSeriSudahCmt);
        }
        
        $cuttingBelumCmt = $cuttingQuery->get();
        
        // Ambil distribusi jasa yang belum dibuatkan SPK CMT
        $jasaQuery = SpkJasa::with(['spkCuttingDistribusi.spkCutting.produk']);
        
        $jasaQuery->whereHas('spkCuttingDistribusi', function ($query) use ($kodeSeriSudahCmt) {
            $query->whereNotNull('kode_seri')
                ->where('kode_seri', '!=', '');
            if (!empty($kodeSeriSudahCmt)) {
                $query->whereNotIn('kode_seri', $kodeSeriSudahCmt);
            }
        });
        
        $jasaBelumCmt = $jasaQuery->get();
        
        // Hitung statistik - Grouping berdasarkan kode seri (seperti di KodeSeriBelumDikerjakan)
        $groupedBySeri = [];
        $produkIds = [];
        
        // Proses distribusi cutting
        foreach ($cuttingBelumCmt as $distribusi) {
            $kodeSeri = $distribusi->kode_seri;
            $jumlahProduk = $distribusi->jumlah_produk ?? 0;
            $produk = $distribusi->spkCutting->produk ?? null;
            
            if (!$kodeSeri) {
                continue;
            }
            
            // Cek apakah kode seri ini juga ada di jasa (jika ada, skip cutting karena jasa yang diutamakan)
            $hasJasa = false;
            foreach ($jasaBelumCmt as $jasa) {
                if ($jasa->spkCuttingDistribusi && $jasa->spkCuttingDistribusi->kode_seri === $kodeSeri) {
                    $hasJasa = true;
                    break;
                }
            }
            
            if (!$hasJasa) {
                if (!isset($groupedBySeri[$kodeSeri])) {
                    $groupedBySeri[$kodeSeri] = [
                        'jumlah' => 0,
                        'produk_id' => $produk ? $produk->id : null,
                    ];
                }
                $groupedBySeri[$kodeSeri]['jumlah'] += $jumlahProduk;
                
                if ($produk && $produk->id && !in_array($produk->id, $produkIds)) {
                    $produkIds[] = $produk->id;
                }
            }
        }
        
        // Proses distribusi jasa
        foreach ($jasaBelumCmt as $jasa) {
            if (!$jasa->spkCuttingDistribusi) {
                continue;
            }
            
            $distribusi = $jasa->spkCuttingDistribusi;
            $kodeSeri = $distribusi->kode_seri;
            $jumlahProduk = $jasa->jumlah ?? 0;
            $produk = $distribusi->spkCutting->produk ?? null;
            
            if (!$kodeSeri) {
                continue;
            }
            
            if (!isset($groupedBySeri[$kodeSeri])) {
                $groupedBySeri[$kodeSeri] = [
                    'jumlah' => 0,
                    'produk_id' => $produk ? $produk->id : null,
                ];
            }
            $groupedBySeri[$kodeSeri]['jumlah'] += $jumlahProduk;
            
            if ($produk && $produk->id && !in_array($produk->id, $produkIds)) {
                $produkIds[] = $produk->id;
            }
        }
        
        // Hitung total
        $jmlPcsBelumAmbil = 0;
        $jmlProdukBelumAmbil = 0;
        foreach ($groupedBySeri as $kodeSeri => $data) {
            $jmlPcsBelumAmbil += $data['jumlah'];
            $jmlProdukBelumAmbil += $data['jumlah'];
        }
        
        $jmlSpkBelumAmbil = count($groupedBySeri); // Jumlah kode seri unik
        $jmlModelProdukBelumAmbil = count(array_unique($produkIds));

        // 3. Sedang Dikerjakan CMT
        // SPK CMT yang sudah ada pengiriman tapi masih ada sisa barang
        // Ambil semua SPK CMT yang sudah ada pengiriman
        $spkSedangDikerjakan = SpkCmt::with(['pengiriman' => function ($query) {
            $query->latest('tanggal_pengiriman');
        }])
        ->whereIn('id_spk', function ($query) {
            $query->select('id_spk')
                ->from('pengiriman')
                ->distinct();
        })
        ->get();

        $totalJmlPcsSedangDikerjakan = 0;
        $masihDalamDeadline = 0;
        $overDeadline = 0;
        $kirimMingguIni = 0;

        $now = Carbon::now();

        foreach ($spkSedangDikerjakan as $spk) {
            $latestPengiriman = Pengiriman::where('id_spk', $spk->id_spk)
                ->latest('tanggal_pengiriman')
                ->first();

            $sisaBarang = $latestPengiriman->sisa_barang ?? 0;

            if ($sisaBarang > 0) {
                $totalJmlPcsSedangDikerjakan += $sisaBarang;

                // Cek deadline
                if ($spk->deadline) {
                    $deadline = Carbon::parse($spk->deadline);
                    if ($deadline->isPast()) {
                        $overDeadline += $sisaBarang;
                    } else {
                        $masihDalamDeadline += $sisaBarang;
                    }
                } else {
                    $masihDalamDeadline += $sisaBarang;
                }
            }
        }

        // Hitung kirim minggu ini
        $kirimMingguIni = Pengiriman::join('spk_cmt', 'pengiriman.id_spk', '=', 'spk_cmt.id_spk')
            ->whereBetween('pengiriman.tanggal_pengiriman', [$mingguIniStart, $mingguIniEnd])
            ->sum('pengiriman.total_barang_dikirim');

        // 4. Kemampuan Kirim All CMT
        // Hitung pengiriman 4 minggu sebelumnya (dari 4 minggu lalu sampai 1 minggu lalu)
        $periode4Minggu = [];
        for ($i = 4; $i >= 1; $i--) {
            $start = Carbon::now()->subWeeks($i)->startOfWeek();
            $end = Carbon::now()->subWeeks($i)->endOfWeek();

            $jmlPcs = Pengiriman::whereBetween('tanggal_pengiriman', [$start, $end])
                ->sum('total_barang_dikirim');

            $label = $i == 1 ? '1 Minggu Sebelumnya' : $i . ' Minggu Sebelumnya';
            
            $periode4Minggu[] = [
                'minggu' => $label,
                'jml_pcs' => (int) $jmlPcs,
            ];
        }

        // Rata-rata kirim
        $rataRataKirim = count($periode4Minggu) > 0
            ? (int) round(collect($periode4Minggu)->avg('jml_pcs'))
            : 0;

        // 5. Jumlah Total Pekerjaan
        // Rumus: PCS Menunggu Bahan + SPK Belum Potong + SPK Belum Ambil CMT + Sedang Dikerjakan CMT
        $spkBelumPotongPcs = SpkCutting::whereNotIn('id', function ($query) {
            $query->select('spk_cutting_id')
                ->from('hasil_cutting')
                ->distinct();
        })
        ->where('status_cutting', '!=', 'selesai')
        ->sum('jumlah_asumsi_produk');

        $jumlahTotalPekerjaan = $perkiraanPcsWaitingBahan +
            $spkBelumPotongPcs +
            $jmlPcsBelumAmbil +
            $totalJmlPcsSedangDikerjakan;

        // Persentase kekuatan pengiriman CMT
        // Rumus: (Rata-rata Kirim / Jumlah Total Pekerjaan) * 100
        $persentaseKekuatanPengiriman = $jumlahTotalPekerjaan > 0
            ? round(($rataRataKirim / $jumlahTotalPekerjaan) * 100, 2)
            : 0;

        return [
            'perkiraan_pcs_waiting_bahan' => (int) $perkiraanPcsWaitingBahan,
            'spk_belum_ambil' => [
                'jml_pcs' => (int) $jmlPcsBelumAmbil,
                'jml_spk' => (int) $jmlSpkBelumAmbil,
                'jml_produk' => (int) $jmlProdukBelumAmbil,
                'jml_model_produk' => (int) $jmlModelProdukBelumAmbil,
            ],
            'sedang_dikerjakan' => [
                'total_jml_pcs' => (int) $totalJmlPcsSedangDikerjakan,
                'masih_dalam_deadline' => (int) $masihDalamDeadline,
                'over_deadline' => (int) $overDeadline,
                'kirim_minggu_ini' => (int) $kirimMingguIni,
            ],
            'kemampuan_kirim' => [
                'periode_4_minggu' => $periode4Minggu,
                'rata_rata_kirim' => $rataRataKirim,
                'persentase_kekuatan_pengiriman' => $persentaseKekuatanPengiriman,
            ],
            'jumlah_total_pekerjaan' => (int) $jumlahTotalPekerjaan,
        ];
    }

}
