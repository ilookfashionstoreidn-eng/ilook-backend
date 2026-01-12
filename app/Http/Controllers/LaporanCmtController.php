<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LaporanCmt;
use App\Models\SpkCmt;
use App\Models\Penjahit;
use App\Models\Pengiriman;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LaporanCmtController extends Controller
{    
    public function index()
    {
        $laporans = LaporanCmt::with('spk')->get();
        return response()->json($laporans, 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_spk' => 'required|exists:spk_cmt,id_spk',
            'tgl_pengiriman' => 'required|date',
            'jumlah_dikirim' => 'required|integer|min:1',
            'barang_rusak' => 'nullable|integer|min:0',
            'barang_hilang' => 'nullable|integer|min:0',
            'upah_per_barang' => 'required|numeric|min:0',
            'total_upah' => 'required|numeric|min:0',
            'potongan' => 'nullable|numeric|min:0',
            'cashbon' => 'nullable|numeric|min:0',
            'status_pembayaran' => 'required|in:Paid,Unpaid',
            'keterangan' => 'nullable|string',
        ]);

        $laporan = LaporanCmt::create($validated);

        return response()->json(['message' => 'Laporan berhasil dibuat!', 'data' => $laporan], 201);
    }

    public function show($id)
    {
        $laporan = LaporanCmt::with('spk')->findOrFail($id);
        return response()->json($laporan, 200);
    }

    public function update(Request $request, $id)
    {
        $laporan = LaporanCmt::findOrFail($id);

        $validated = $request->validate([
            'id_spk' => 'required|exists:spk_cmt,id_spk',
            'tgl_pengiriman' => 'required|date',
            'jumlah_dikirim' => 'required|integer|min:1',
            'barang_rusak' => 'nullable|integer|min:0',
            'barang_hilang' => 'nullable|integer|min:0',
            'upah_per_barang' => 'required|numeric|min:0',
            'total_upah' => 'required|numeric|min:0',
            'potongan' => 'nullable|numeric|min:0',
            'cashbon' => 'nullable|numeric|min:0',
            'status_pembayaran' => 'required|in:Paid,Unpaid',
            'keterangan' => 'nullable|string',
        ]);

        $laporan->update($validated);

        return response()->json(['message' => 'Laporan berhasil diperbarui!', 'data' => $laporan], 200);
    }

    public function destroy($id)
    {
        $laporan = LaporanCmt::findOrFail($id);
        $laporan->delete();

        return response()->json(['message' => 'Laporan berhasil dihapus!'], 200);
    }

    public function getDataDikerjakanDanPengiriman(Request $request)
    {
        $penjahits = Penjahit::orderBy('nama_penjahit')->get();
        $result = [];
        
        // Ambil parameter periode dari request (default: minggu ini + 4 minggu sebelumnya)
        $periodeRequest = $request->input('periode', [0, -1, -2, -3, -4]);
        
        // Validasi dan sort periode (dari terbaru ke terlama)
        $periodeRequest = array_map('intval', $periodeRequest);
        $periodeRequest = array_unique($periodeRequest);
        sort($periodeRequest, SORT_NUMERIC);
        $periodeRequest = array_reverse($periodeRequest); // Dari terbaru ke terlama
        
        // Hitung tanggal untuk setiap periode
        $periodeDates = [];
        foreach ($periodeRequest as $weekOffset) {
            $start = Carbon::now()->addWeeks($weekOffset)->startOfWeek();
            $end = Carbon::now()->addWeeks($weekOffset)->endOfWeek();
            $periodeDates[] = [
                'offset' => $weekOffset,
                'start' => $start,
                'end' => $end,
                'label' => $weekOffset == 0 ? 'Minggu Ini' : abs($weekOffset) . ' Minggu Sebelumnya'
            ];
        }
        
        // Hitung periode minggu ini (untuk kolom tetap)
        $mingguIniStart = Carbon::now()->startOfWeek();
        $mingguIniEnd = Carbon::now()->endOfWeek();
        
        $totalDikerjakan = 0;
        $totalLebihDeadline = 0;
        $totalMasihDeadline = 0;
        $totalPengirimanMingguIni = 0;
        
        foreach ($penjahits as $index => $penjahit) {
            if (empty($penjahit->nama_penjahit)) {
                continue;
            }
            
            // 1. Hitung data yang masih dikerjakan
            // Ambil semua SPK, lalu hitung sisa_barang-nya (yang masih dikerjakan adalah yang sisa_barang > 0)
            $spks = SpkCmt::where('id_penjahit', $penjahit->id_penjahit)
                ->with(['spkCuttingDistribusi', 'spkJasa.spkCuttingDistribusi'])
                ->get();
            
            $jmlTotalDikerjakan = 0;
            $lebihDariDeadline = 0;
            $masihDalamDeadline = 0;
            $now = Carbon::now();
            
            foreach ($spks as $spk) {
                $latestPengiriman = Pengiriman::where('id_spk', $spk->id_spk)
                    ->latest('tanggal_pengiriman')
                    ->first();
                
                $sisaBarang = 0;
                if ($latestPengiriman) {
                    $sisaBarang = $latestPengiriman->sisa_barang ?? 0;
                } else {
                    // Jika belum ada pengiriman, hitung dari source
                    if ($spk->source_type === 'cutting' && $spk->spkCuttingDistribusi) {
                        $sisaBarang = $spk->spkCuttingDistribusi->jumlah_produk ?? 0;
                    } elseif ($spk->source_type === 'jasa' && $spk->spkJasa && $spk->spkJasa->spkCuttingDistribusi) {
                        $sisaBarang = $spk->spkJasa->spkCuttingDistribusi->jumlah_produk ?? 0;
                    }
                }
                
                // Hanya hitung jika masih ada sisa barang
                if ($sisaBarang > 0) {
                    $jmlTotalDikerjakan += $sisaBarang;
                    
                    // Cek deadline
                    if ($spk->deadline) {
                        $deadline = Carbon::parse($spk->deadline);
                        if ($deadline->isPast()) {
                            $lebihDariDeadline += $sisaBarang;
                        } else {
                            $masihDalamDeadline += $sisaBarang;
                        }
                    } else {
                        // Jika tidak ada deadline, anggap masih dalam deadline
                        $masihDalamDeadline += $sisaBarang;
                    }
                }
            }
            
            // 2. Hitung pengiriman minggu ini
            $pengirimanMingguIni = Pengiriman::join('spk_cmt', 'pengiriman.id_spk', '=', 'spk_cmt.id_spk')
                ->where('spk_cmt.id_penjahit', $penjahit->id_penjahit)
                ->whereBetween('pengiriman.tanggal_pengiriman', [$mingguIniStart, $mingguIniEnd])
                ->sum('pengiriman.total_barang_dikirim');
            
            // 3. Hitung pengiriman untuk setiap periode yang dipilih
            $pengirimanPeriode = [];
            foreach ($periodeDates as $periode) {
                $pengiriman = Pengiriman::join('spk_cmt', 'pengiriman.id_spk', '=', 'spk_cmt.id_spk')
                    ->where('spk_cmt.id_penjahit', $penjahit->id_penjahit)
                    ->whereBetween('pengiriman.tanggal_pengiriman', [$periode['start'], $periode['end']])
                    ->sum('pengiriman.total_barang_dikirim');
                
                $pengirimanPeriode['periode_' . $periode['offset']] = $pengiriman;
            }
            
            // 4. Hitung rata-rata pengiriman mingguan
            // Ambil semua pengiriman, group by minggu, hitung rata-rata
            $semuaPengiriman = Pengiriman::join('spk_cmt', 'pengiriman.id_spk', '=', 'spk_cmt.id_spk')
                ->where('spk_cmt.id_penjahit', $penjahit->id_penjahit)
                ->select('pengiriman.tanggal_pengiriman', 'pengiriman.total_barang_dikirim')
                ->get();
            
            $pengirimanPerMinggu = $semuaPengiriman->groupBy(function ($item) {
                return Carbon::parse($item->tanggal_pengiriman)->year . '-W' . 
                       str_pad(Carbon::parse($item->tanggal_pengiriman)->week, 2, '0', STR_PAD_LEFT);
            });
            
            $totalPerMinggu = [];
            foreach ($pengirimanPerMinggu as $minggu => $items) {
                $totalPerMinggu[] = $items->sum('total_barang_dikirim');
            }
            
            $rataRataPengirimanMingguan = count($totalPerMinggu) > 0 
                ? array_sum($totalPerMinggu) / count($totalPerMinggu) 
                : 0;
            
            // 5. Hitung kenaikan/penurunan dari rata-rata 2 minggu terakhir (dari periode yang dipilih)
            // Ambil periode selain minggu ini (offset < 0) untuk perhitungan rata-rata
            $periodeUntukRataRata = array_filter($periodeRequest, function($offset) {
                return $offset < 0; // Hanya periode sebelum minggu ini
            });
            
            $rataRata2MingguTerakhir = 0;
            if (count($periodeUntukRataRata) >= 2) {
                // Ambil 2 periode terbaru sebelum minggu ini
                $periodeUntukRataRata = array_slice(array_reverse($periodeUntukRataRata), 0, 2);
                $totalRataRata = 0;
                foreach ($periodeUntukRataRata as $offset) {
                    $key = 'periode_' . $offset;
                    $totalRataRata += $pengirimanPeriode[$key] ?? 0;
                }
                $rataRata2MingguTerakhir = $totalRataRata / count($periodeUntukRataRata);
            } elseif (count($periodeUntukRataRata) == 1) {
                $offset = reset($periodeUntukRataRata);
                $key = 'periode_' . $offset;
                $rataRata2MingguTerakhir = $pengirimanPeriode[$key] ?? 0;
            }
            $kenaikanPenurunanDariRata2 = $pengirimanMingguIni - $rataRata2MingguTerakhir;
            
            // 6. Hitung pengiriman tertinggi periode ini
            $pengirimanTertinggi = count($totalPerMinggu) > 0 ? max($totalPerMinggu) : 0;
            
            // 7. Hitung kenaikan/penurunan dari kirim tertinggi
            $kenaikanPenurunanDariTertinggi = $pengirimanMingguIni - $pengirimanTertinggi;
            
            // Buat array result dengan data periode dinamis
            $resultItem = [
                'no' => count($result) + 1,
                'nama_cmt' => $penjahit->nama_penjahit,
                'jml_total_dikerjakan' => (int)$jmlTotalDikerjakan,
                'lebih_dari_deadline' => (int)$lebihDariDeadline,
                'masih_dalam_deadline' => (int)$masihDalamDeadline,
                'pengiriman_minggu_ini' => (int)$pengirimanMingguIni,
                'rata_rata_pengiriman_mingguan' => round($rataRataPengirimanMingguan, 0),
                'kenaikan_penurunan_dari_rata2' => round($kenaikanPenurunanDariRata2, 0),
                'pengiriman_tertinggi' => (int)$pengirimanTertinggi,
                'kenaikan_penurunan_dari_tertinggi' => round($kenaikanPenurunanDariTertinggi, 0),
            ];
            
            // Tambahkan data pengiriman per periode
            foreach ($pengirimanPeriode as $key => $value) {
                $resultItem[$key] = (int)$value;
            }
            
            $result[] = $resultItem;
            
            // Accumulate totals
            $totalDikerjakan += $jmlTotalDikerjakan;
            $totalLebihDeadline += $lebihDariDeadline;
            $totalMasihDeadline += $masihDalamDeadline;
            $totalPengirimanMingguIni += $pengirimanMingguIni;
        }
        
        return response()->json([
            'data' => $result,
            'summary' => [
                'total_dikerjakan' => (int)$totalDikerjakan,
                'total_lebih_deadline' => (int)$totalLebihDeadline,
                'total_masih_deadline' => (int)$totalMasihDeadline,
                'total_pengiriman_minggu_ini' => (int)$totalPengirimanMingguIni,
            ],
            'periode' => $periodeDates // Kirim info periode untuk frontend
        ], 200);
    }
}
