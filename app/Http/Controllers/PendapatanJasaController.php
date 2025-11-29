<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TukangJasa;
use App\Models\HutangJasa;
use App\Models\CashboanJasa;
use App\Models\HasilJasa;
use App\Models\PendapatanJasa;
use App\Models\HistoryHutangJasa;
use App\Models\HistoryCashboanJasa;
use Carbon\Carbon;


class PendapatanJasaController extends Controller
{

     public function index()
    {
        $pendapatan = PendapatanJasa::with([
            'tukangJasa:id,nama',

        ])->get();
        return response()->json($pendapatan); 
    }
    public function getPendapatanMingguIni()
    {
        $periodeAwal = now()->startOfWeek();
        $periodeAkhir = now()->endOfWeek();

        $dataPendapatan = TukangJasa::all()->map(function ($tcutting) use ($periodeAwal, $periodeAkhir) {

            $hasil = $tcutting->getPendapatanMingguIni(); 
            $totalPendapatan = $hasil->total_pendapatan ?? 0;
            $potonganHutang = 0;
            $hutang = HutangJasa::where('tukang_jasa_id', $tcutting->id)
                ->orderBy('tanggal_hutang', 'desc')
                ->first();

            if ($hutang) {
                $potongan = $hutang->is_potongan_persen
                    ? ($hutang->persentase_potongan / 100) * $totalPendapatan
                    : $hutang->potongan_per_minggu;

                $potonganHutang = min($hutang->jumlah_hutang, $potongan);
            }

            $potonganCashbon = 0;
            $cashbon = CashboanJasa::where('tukang_jasa_id', $tcutting->id)
                ->orderBy('tanggal_cashboan', 'desc')
                ->first();
        if ($cashbon) {
        $potonganCashbon = $cashbon->jumlah_cashboan;
    }

            $awalMingguIni = now()->startOfWeek();

            $pengirimanBelumMasukPendapatan = HasilJasa::join('spk_jasa', 'hasil_jasa.spk_jasa_id', '=', 'spk_jasa.id')
                ->where('spk_jasa.tukang_jasa_id', $tcutting->id)
                ->where('hasil_jasa.created_at', '<', $awalMingguIni)
                ->whereNotIn('hasil_jasa.id', function ($query) {
                        $query->select('hasil_jasa_id')->from('hasil_pendapatan_jasa');
                })
                ->get();

            $totalPendapatanBelumDibayar = $pengirimanBelumMasukPendapatan->sum('total_bayar');

            $totalTransfer = $totalPendapatan + $totalPendapatanBelumDibayar
                - $potonganHutang - $potonganCashbon;

            $sudahDibayar = PendapatanJasa::where('tukang_jasa_id', $tcutting->id)
                ->whereBetween('created_at', [$periodeAwal, $periodeAkhir])
                ->where('status_pembayaran', 'sudah_dibayar')
                ->exists();

            return [
                'tukang_jasa_id' => $tcutting->id,
                'nama_tukang_jasa' => $tcutting->nama,
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
            'tukang_jasa_id' => 'required|exists:tukang_jasa,id',
            'kurangi_hutang' => 'required|boolean',
            'kurangi_cashbon' => 'required|boolean',
        ]);

        $periodeAwal = now()->startOfWeek();
        $periodeAkhir = now()->endOfWeek();

        $tukang = TukangJasa::findOrFail($request->tukang_jasa_id);
        $hasil = $tukang->getPendapatanMingguIni(); 
        $totalPendapatan = $hasil->total_pendapatan ?? 0;
        $potonganHutang = 0;

        if ($request->kurangi_hutang) {
            $hutang = HutangJasa::where('tukang_jasa_id', $tukang->id)
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
            $cashbon = CashboanJasa::where('tukang_jasa_id', $tukang->id)
                ->orderBy('tanggal_cashboan', 'desc')
                ->first();

            if ($cashbon) {
                $potonganCashbon = $cashbon->jumlah_cashboan;
            }
        }
        $awalMingguIni = now()->startOfWeek();

        $pengirimanBelumMasukPendapatan = HasilJasa::join('spk_jasa', 'hasil_jasa.spk_jasa_id', '=', 'spk_jasa.id')
            ->where('spk_jasa.tukang_jasa_id', $tukang->id)
            ->where('hasil_jasa.created_at', '<', $awalMingguIni)
            ->whereNotIn('hasil_jasa.id', function ($query) {
                $query->select('hasil_jasa_id')->from('hasil_pendapatan_jasa');
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

      public function tambahPendapatanJasa(Request $request)
    {
        $request->validate([
            'tukang_jasa_id' => 'required|exists:tukang_jasa,id',
            'kurangi_hutang' => 'required|boolean',
            'kurangi_cashbon' => 'required|boolean',
            'bukti_transfer' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:200048',
        ]);

        $path = null;
        if ($request->hasFile('bukti_transfer')) {
            $path = $request->file('bukti_transfer')->store('bukti_transfer_pendapatan_jasa', 'public');
        }

        $periodeAwal = now()->startOfWeek();
        $periodeAkhir = now()->endOfWeek();

        if (PendapatanJasa::where('tukang_jasa_id', $request->tukang_jasa_id)
            ->whereBetween('created_at', [$periodeAwal, $periodeAkhir])
            ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Pendapatan minggu ini sudah dihitung.',
            ], 422);
        }

        $tukang = TukangJasa::findOrFail($request->tukang_jasa_id);
        $hasil = $tukang->getPendapatanMingguIni(); 

        $totalPendapatan = $hasil->total_pendapatan ?? 0;

        $potonganHutang = 0;
        $potonganCashbon = 0;

        if ($request->kurangi_hutang) {
            $hutang = HutangJasa::where('tukang_jasa_id', $request->tukang_jasa_id)
                ->orderBy('tanggal_hutang', 'desc')
                ->first();

            if ($hutang) {
                $potongan = $hutang->is_potongan_persen
                    ? ($hutang->persentase_potongan / 100) * $totalPendapatan
                    : $hutang->potongan_per_minggu;

                $penguranganHutang = min($hutang->jumlah_hutang, $potongan);
                $hutang->jumlah_hutang -= $penguranganHutang;
                $hutang->save();

                HistoryHutangJasa::create([
                     'hutang_jasa_id' => $hutang->id,
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
            $cashbon = CashboanJasa::where('tukang_jasa_id', $request->tukang_jasa_id)
                ->orderBy('tanggal_cashboan', 'desc')
                ->first();

            if ($cashbon) {
               
                $penguranganCashbon = min($cashbon->jumlah_cashboan, $potongan);
                $cashbon->jumlah_cashboan -= $penguranganCashbon;
                $cashbon->save();

                HistoryCashboanJasa::create([
                    'cashboan_jasa_id' => $cashbon->id,
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

        $hasilBelumMasukPendapatan = HasilJasa::join('spk_jasa', 'hasil_jasa.spk_jasa_id', '=', 'spk_jasa.id')
            ->where('spk_jasa.tukang_jasa_id', $tukang->id)
            ->where('hasil_jasa.created_at', '<', $awalMingguIni)
            ->whereNotIn('hasil_jasa.id', function ($query) {
                $query->select('hasil_jasa_id')->from('hasil_pendapatan_jasa');
            })
            ->get();

        $totalPendapatanBelumDibayar = $hasilBelumMasukPendapatan->sum('total_bayar');

        $totalPendapatanGabungan = $totalPendapatan + $totalPendapatanBelumDibayar;

        $totalTransfer = $totalPendapatanGabungan - $potonganHutang - $potonganCashbon;

        $pendapatan = PendapatanJasa::create([
            'tukang_jasa_id' => $tukang->id,
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
            $pendapatan = PendapatanJasa::find($id);

            if (!$pendapatan) {
                return response()->json(['message' => 'Pendapatan tidak ditemukan.'], 404);
            }
            $periodeAwal = Carbon::parse($pendapatan->tanggal_pendapatan)->startOfWeek();
            $periodeAkhir = Carbon::parse($pendapatan->tanggal_pendapatan)->endOfWeek();
            // Ambil data pengiriman terkait 
            $pengiriman = HasilJasa::join('spk_jasa', 'hasil_jasa.spk_jasa_id', '=', 'spk_jasa.id')
                ->where('spk_jasa.tukang_jasa_id', $pendapatan->tukang_jasa_id)
                ->whereBetween('hasil_jasa.tanggal', [$periodeAwal, $periodeAkhir])
                ->get();

            return response()->json([
                'pendapatan' => $pendapatan,
                'pengiriman' => $pengiriman,
            ]);
    }
}
