<?php

namespace App\Http\Controllers;

use App\Models\PendapatanCutting;
use App\Models\CashboanCutting;
use App\Models\HutangCutting;
use App\Models\SpkCutting;
use App\Models\HasilCutting;
use App\Models\TukangCutting;
use App\Models\HistoryHutangCutting;
use App\Models\HistoryCashboanCutting;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PendapatanCuttingController extends Controller
{
  public function index()
    {
        $pendapatan = PendapatanCutting::with([
            'tukangCutting:id,nama_tukang_cutting',

        ])->get();
        return response()->json($pendapatan); 
    }

  public function getPendapatanMingguIni()
    {
        $periodeAwal = now()->startOfWeek();
        $periodeAkhir = now()->endOfWeek();

        $dataPendapatan = TukangCutting::all()->map(function ($tcutting) use ($periodeAwal, $periodeAkhir) {

            $hasil = $tcutting->getPendapatanMingguIni(); 
            $totalPendapatan = $hasil->total_pendapatan ?? 0;

            $potonganHutang = 0;
            $hutang = HutangCutting::where('tukang_cutting_id', $tcutting->id)
                ->orderBy('tanggal_hutang', 'desc')
                ->first();

            if ($hutang) {
                $potongan = $hutang->is_potongan_persen
                    ? ($hutang->persentase_potongan / 100) * $totalPendapatan
                    : $hutang->potongan_per_minggu;

                $potonganHutang = min($hutang->jumlah_hutang, $potongan);
            }

            $potonganCashbon = 0;
            $cashbon = CashboanCutting::where('tukang_cutting_id', $tcutting->id)
                ->orderBy('tanggal_cashboan', 'desc')
                ->first();
        if ($cashbon) {
        $potonganCashbon = $cashbon->jumlah_cashboan;
    }

            $awalMingguIni = now()->startOfWeek();

            $pengirimanBelumMasukPendapatan = HasilCutting::join('spk_cutting', 'hasil_cutting.spk_cutting_id', '=', 'spk_cutting.id')
                ->where('spk_cutting.tukang_cutting_id', $tcutting->id)
                ->where('hasil_cutting.created_at', '<', $awalMingguIni)
                ->whereNotIn('hasil_cutting.id', function ($query) {
                        $query->select('hasil_cutting_id')->from('hasil_pendapatan_cutting');
                })
                ->get();

            $totalPendapatanBelumDibayar = $pengirimanBelumMasukPendapatan->sum('total_bayar');

            $totalTransfer = $totalPendapatan + $totalPendapatanBelumDibayar
                - $potonganHutang - $potonganCashbon;

            $sudahDibayar = PendapatanCutting::where('tukang_cutting_id', $tcutting->id)
                ->whereBetween('created_at', [$periodeAwal, $periodeAkhir])
                ->where('status_pembayaran', 'sudah_dibayar')
                ->exists();

            return [
                'tukang_cutting_id' => $tcutting->id,
                'nama_tukang_cutting' => $tcutting->nama_tukang_cutting,
                'total_pendapatan' => $totalPendapatan,
                'potongan_hutang' => $potonganHutang,
                'potongan_cashbon' => $potonganCashbon,
                'total_transfer' => $totalTransfer,
                'status_pembayaran' => $sudahDibayar ? 'sudah dibayar' : 'belum dibayar',
                'pendapatan_belum_dibayar_sebelumnya' => $totalPendapatanBelumDibayar,
            ];
        });

        return response()->json($dataPendapatan);
    }

    public function simulasiPendapatanCutting(Request $request)
    {
        $request->validate([
            'tukang_cutting_id' => 'required|exists:tukang_cutting,id',
            'kurangi_hutang' => 'required|boolean',
            'kurangi_cashbon' => 'required|boolean',
        ]);

        $periodeAwal = now()->startOfWeek();
        $periodeAkhir = now()->endOfWeek();

        $tukang = TukangCutting::findOrFail($request->tukang_cutting_id);
        $hasil = $tukang->getPendapatanMingguIni(); 

        $totalPendapatan = $hasil->total_pendapatan ?? 0;

        $potonganHutang = 0;
        if ($request->kurangi_hutang) {
            $hutang = HutangCutting::where('tukang_cutting_id', $tukang->id)
                ->orderBy('tanggal_hutang', 'desc')
                ->first();

            if ($hutang) {
                $potongan = $hutang->is_potongan_persen
                    ? ($hutang->persentase_potongan / 100) * $totalPendapatan
                    : $hutang->potongan_per_minggu;

                $potonganHutang = min($hutang->jumlah_hutang, $potongan);
            }
        }

        $potonganCashbon = 0;
        if ($request->kurangi_cashbon) {
            $cashbon = CashboanCutting::where('tukang_cutting_id', $tukang->id)
                ->orderBy('tanggal_cashboan', 'desc')
                ->first();

            if ($cashbon) {
                $potonganCashbon = $cashbon->jumlah_cashboan;
            }
        }
        $awalMingguIni = now()->startOfWeek();
        $pengirimanBelumMasukPendapatan = HasilCutting::join('spk_cutting', 'hasil_cutting.spk_cutting_id', '=', 'spk_cutting.id')
            ->where('spk_cutting.tukang_cutting_id', $tukang->id)
            ->where('hasil_cutting.created_at', '<', $awalMingguIni)
            ->whereNotIn('hasil_cutting.id', function ($query) {
                $query->select('hasil_cutting_id')->from('hasil_pendapatan_cutting');
            })
            ->get();

        $totalPendapatanBelumDibayar = $pengirimanBelumMasukPendapatan->sum('total_bayar');

        $totalTransfer = $totalPendapatan + $totalPendapatanBelumDibayar
            - $potonganHutang - $potonganCashbon;

        return response()->json([
            'total_pendapatan' => $totalPendapatan,
            'pendapatan_belum_dibayar_sebelumnya' => $totalPendapatanBelumDibayar,
            'potongan_hutang' => $potonganHutang,
            'potongan_cashbon' => $potonganCashbon,
            'total_transfer' => $totalTransfer,
        ]);
    }


    public function tambahPendapatanCutting(Request $request)
    {
        $request->validate([
            'tukang_cutting_id' => 'required|exists:tukang_cutting,id',
            'kurangi_hutang' => 'required|boolean',
            'kurangi_cashbon' => 'required|boolean',
            'bukti_transfer' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:200048',
        ]);

        $path = null;
        if ($request->hasFile('bukti_transfer')) {
            $path = $request->file('bukti_transfer')->store('bukti_transfer_pendapatan_cutting', 'public');
        }

        $periodeAwal = now()->startOfWeek();
        $periodeAkhir = now()->endOfWeek();

        if (PendapatanCutting::where('tukang_cutting_id', $request->tukang_cutting_id)
            ->whereBetween('created_at', [$periodeAwal, $periodeAkhir])
            ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Pendapatan minggu ini sudah dihitung.',
            ], 422);
        }

        $tukang = TukangCutting::findOrFail($request->tukang_cutting_id);
        $hasil = $tukang->getPendapatanMingguIni(); // method harus ada di model TukangCutting

        $totalPendapatan = $hasil->total_pendapatan ?? 0;

        $potonganHutang = 0;
        $potonganCashbon = 0;

        if ($request->kurangi_hutang) {
            $hutang = HutangCutting::where('tukang_cutting_id', $request->tukang_cutting_id)
                ->orderBy('tanggal_hutang', 'desc')
                ->first();

            if ($hutang) {
                $potongan = $hutang->is_potongan_persen
                    ? ($hutang->persentase_potongan / 100) * $totalPendapatan
                    : $hutang->potongan_per_minggu;

                $penguranganHutang = min($hutang->jumlah_hutang, $potongan);
                $hutang->jumlah_hutang -= $penguranganHutang;
                $hutang->save();

                HistoryHutangCutting::create([
                     'hutang_cutting_id' => $hutang->id,
                    'jenis_perubahan' => 'pengurangan',
                    'tanggal_perubahan' => now(),
                    'jumlah_hutang' => $hutang->jumlah_hutang,
                    'perubahan_hutang' => $penguranganHutang,
                    'bukti_transfer' => $path,
                ]);

                $potonganHutang = $penguranganHutang;
            }
        }

        if ($request->kurangi_cashbon) {
            $cashbon = CashboanCutting::where('tukang_cutting_id', $request->tukang_cutting_id)
                ->orderBy('tanggal_cashboan', 'desc')
                ->first();

            if ($cashbon) {
               
                $penguranganCashbon = min($cashbon->jumlah_cashboan, $potongan);
                $cashbon->jumlah_cashboan -= $penguranganCashbon;
                $cashbon->save();

                HistoryCashboanCutting::create([
                    'cashboan_cutting_id' => $cashbon->id,
                    'jenis_perubahan' => 'pengurangan',
                    'tanggal_perubahan' => now(),
                    'jumlah_cashboan' => $cashbon->jumlah_cashboan,
                    'perubahan_cashboan' => $penguranganCashbon,
                    'bukti_transfer' => $path,
                ]);

                $potonganCashbon = $penguranganCashbon;
            }
        }
        $awalMingguIni = now()->startOfWeek();

        $hasilBelumMasukPendapatan = HasilCutting::join('spk_cutting', 'hasil_cutting.spk_cutting_id', '=', 'spk_cutting.id')
            ->where('spk_cutting.tukang_cutting_id', $tukang->id)
            ->where('hasil_cutting.created_at', '<', $awalMingguIni)
            ->whereNotIn('hasil_cutting.id', function ($query) {
                $query->select('hasil_cutting_id')->from('hasil_pendapatan_cutting');
            })
            ->get();

        $totalPendapatanBelumDibayar = $hasilBelumMasukPendapatan->sum('total_bayar');

        $totalPendapatanGabungan = $totalPendapatan + $totalPendapatanBelumDibayar;

        $totalTransfer = $totalPendapatanGabungan - $potonganHutang - $potonganCashbon;

        $pendapatan = PendapatanCutting::create([
            'tukang_cutting_id' => $tukang->id,
            'total_pendapatan' => $totalPendapatanGabungan,
            'tanggal_pendapatan' => now(),
            'total_transfer' => $totalTransfer,
            'total_hutang' => $potonganHutang,
            'total_cashbon' => $potonganCashbon,
            'status_pembayaran' => 'sudah_dibayar',
            'bukti_transfer' => $path,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pendapatan cutting berhasil ditambahkan!',
            'data' => $pendapatan
        ], 201);
    }
    public function showPengiriman($id)
    {
        $pendapatan = PendapatanCutting::find($id);

        if (!$pendapatan) {
            return response()->json(['message' => 'Pendapatan tidak ditemukan.'], 404);
        }
        $pengiriman = HasilCutting::join('spk_cutting', 'hasil_cutting.spk_cutting_id', '=', 'spk_cutting.id')
            ->where('spk_cutting.tukang_cutting_id', $pendapatan->tukang_cutting_id)
            ->whereBetween('hasil_cutting.created_at', [$pendapatan->periode_awal, $pendapatan->periode_akhir])
            ->get();

        return response()->json([
            'pendapatan' => $pendapatan,
            'pengiriman' => $pengiriman,
        ]);
    }

}
