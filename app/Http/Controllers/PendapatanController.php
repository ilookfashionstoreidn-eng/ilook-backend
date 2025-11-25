<?php

namespace App\Http\Controllers;

use App\Models\Pendapatan;
use App\Models\Pengiriman;
use App\Models\Cashboan;
use App\Models\Hutang;
use App\Models\Penjahit;
use App\Models\HistoryHutang;
use App\Models\HistoryCashboan;
use App\Models\DetailPesananAksesoris;
use Illuminate\Http\Request;
use PDF; 
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


class PendapatanController extends Controller
{
    public function index(Request $request)
{
    // Ambil parameter query dari request
    $penjahitId = $request->query('penjahit_cmt');

    // Query dasar untuk mengambil data pendapatan dengan join ke tabel penjahit
    $query = Pendapatan::join('penjahit_cmt', 'pendapatan.id_penjahit', '=', 'penjahit_cmt.id_penjahit')
        ->select(
            'pendapatan.*', 
            'penjahit_cmt.bank', 
            'penjahit_cmt.no_rekening'
        );

    // Tambahkan kondisi filter jika ada parameter `penjahit`
    if (!empty($penjahitId)) {
        $query->where('pendapatan.id_penjahit', $penjahitId);
    }

    // Eksekusi query dan dapatkan data
    $pendapatans = $query->orderBy('pendapatan.created_at', 'desc')->paginate(11);

    return response()->json($pendapatans);
}

    
        public function showPengiriman($id)
    {
        // Cari pendapatan berdasarkan ID
        $pendapatan = Pendapatan::find($id);

        if (!$pendapatan) {
            return response()->json(['message' => 'Pendapatan tidak ditemukan.'], 404);
        }

        // Ambil data pengiriman terkait
        $pengiriman = Pengiriman::join('spk_cmt', 'pengiriman.id_spk', '=', 'spk_cmt.id_spk')
            ->where('spk_cmt.id_penjahit', $pendapatan->id_penjahit)
            ->whereBetween('pengiriman.tanggal_pengiriman', [$pendapatan->periode_awal, $pendapatan->periode_akhir])
            ->get();

        return response()->json([
            'pendapatan' => $pendapatan,
            'pengiriman' => $pengiriman,
        ]);
    }

    public function getPenjahitList()
{
    $penjahit = Penjahit::select('id_penjahit', 'nama_penjahit', 'bank', 'no_rekening')->get();
    return response()->json(['data' => $penjahit]);
}


public function downloadNota($id)
{
    // Ambil data pendapatan berdasarkan ID
    $pendapatan = Pendapatan::findOrFail($id);
    
    // Ambil data pengiriman terkait
    $pengiriman = Pengiriman::join('spk_cmt', 'pengiriman.id_spk', '=', 'spk_cmt.id_spk')
        ->where('spk_cmt.id_penjahit', $pendapatan->id_penjahit)
        ->whereBetween('pengiriman.tanggal_pengiriman', [$pendapatan->periode_awal, $pendapatan->periode_akhir])
        ->get();
     $penjahit = $pendapatan->penjahit;
 
    $pdf = PDF::loadView('pdf.nota', compact('pendapatan', 'pengiriman', 'penjahit'))
    ->setPaper('a4'); 
    
    // Kembalikan file PDF untuk diunduh
    return $pdf->download('Nota'.$pendapatan->id_penjahit.'.pdf');
}

public function getPendapatanMingguIni()
{
    $periodeAwal = now()->startOfWeek();
    $periodeAkhir = now()->endOfWeek();

    $dataPendapatan = Penjahit::all()->map(function ($penjahit) use ($periodeAwal, $periodeAkhir) {
        // Mengambil pengiriman minggu ini
        $pengiriman = $penjahit->getPendapatanMingguIni();
        $totalPendapatan = $pengiriman->total_pendapatan ?? 0;

        // Menghitung potongan hutang
        $potonganHutang = 0;
        $hutang = Hutang::where('id_penjahit', $penjahit->id_penjahit)
                        ->orderBy('tanggal_hutang', 'desc')
                        ->first();

        if ($hutang) {
            $potongan = $hutang->is_potongan_persen
                ? ($hutang->persentase_potongan / 100) * $totalPendapatan
                : $hutang->potongan_per_minggu;

            $potonganHutang = min($hutang->jumlah_hutang, $potongan);
        }

        // Menghitung potongan cashbon
        $potonganCashbon = 0;
        $cashbon = Cashboan::where('id_penjahit', $penjahit->id_penjahit)
                          ->orderBy('tanggal_cashboan', 'desc')
                          ->first();

        if ($cashbon) {
            $potongan = $cashbon->is_potongan_persen
                ? ($cashbon->persentase_potongan / 100) * $totalPendapatan
                : $cashbon->potongan_per_minggu;

            $potonganCashbon = min($cashbon->jumlah_cashboan, $potongan);
        }

        // Menghitung potongan aksesoris
        $potonganAksesoris = DetailPesananAksesoris::whereBetween('created_at', [$periodeAwal, $periodeAkhir])
            ->whereHas('petugasC', function ($query) use ($penjahit) {
                $query->where('penjahit_id', $penjahit->id_penjahit);
            })
            ->sum('total_harga');

        // Total claim dan refund
        $totalClaim = $pengiriman->total_claim ?? 0;
        $totalRefund = $pengiriman->total_refund_claim ?? 0;

        // Menghitung total transfer
        if ($totalPendapatan == 0) {
            $totalTransfer = 0;
        } else {
            $totalTransfer = $totalPendapatan + $totalRefund - $totalClaim - $potonganHutang - $potonganCashbon - $potonganAksesoris;
        }

        // Cek apakah sudah ada record pendapatan minggu ini
        $sudahDibayar = Pendapatan::where('id_penjahit', $penjahit->id_penjahit)
        ->whereBetween('created_at', [$periodeAwal, $periodeAkhir])
        ->where('status_pembayaran', 'sudah dibayar')
        ->exists();

        $awalMingguIni = Carbon::now()->startOfWeek(); // Senin jam 00:00

        $pengirimanBelumMasukPendapatan = Pengiriman::join('spk_cmt', 'pengiriman.id_spk', '=', 'spk_cmt.id_spk')
        ->where('spk_cmt.id_penjahit', $penjahit->id_penjahit)
            ->where('pengiriman.status_verifikasi', 'valid')
            ->where('pengiriman.tanggal_pengiriman', '<', $awalMingguIni)
            ->whereNotIn('pengiriman.id_pengiriman', function ($query) {
                $query->select('id_pengiriman')->from('pendapatan');
            })
            ->get();

       

        $totalPendapatanBelumDibayar = $pengirimanBelumMasukPendapatan->sum('total_bayar');

        $totalClaimSebelumnya = $pengirimanBelumMasukPendapatan->sum(function ($item) {
            return $item->claim;
        });
        
        $totalRefundSebelumnya = $pengirimanBelumMasukPendapatan->sum(function ($item) {
            return $item->refund_claim;
        });
        

        // Gabungkan total penghasilan minggu ini + minggu sebelumnya yang belum dibayar
        $totalTransfer = $totalPendapatan + $totalRefund - $totalClaim
        + $totalPendapatanBelumDibayar + $totalRefundSebelumnya - $totalClaimSebelumnya
        - $potonganHutang - $potonganCashbon - $potonganAksesoris;

      return [
          'id_penjahit' => $penjahit->id_penjahit,
          'nama_penjahit' => $penjahit->nama_penjahit,
          'bank' => $penjahit->bank,
          'no_rekening' => $penjahit->no_rekening,
          'total_pendapatan' => $totalPendapatan,
          'total_claim' => $pengiriman->total_claim ?? 0,
          'total_refund_claim' => $pengiriman->total_refund_claim ?? 0,
          'total_claim_sebelumnya' => $totalClaimSebelumnya,
          'total_refund_sebelumnya' => $totalRefundSebelumnya,
          'potongan_hutang' => $potonganHutang,
          'potongan_cashbon' => $potonganCashbon,
          'potongan_aksesoris' => $potonganAksesoris,
          'total_transfer' => $totalTransfer,
          'status_pembayaran' => $sudahDibayar ? 'sudah dibayar' : 'belum dibayar',
          'pendapatan_belum_dibayar_sebelumnya' => $totalPendapatanBelumDibayar,
      ];
      
    });

    return response()->json($dataPendapatan);
}

public function simulasiPendapatan(Request $request)
{
    $request->validate([
        'id_penjahit' => 'required|exists:penjahit_cmt,id_penjahit',
        'kurangi_hutang' => 'required|boolean',
        'kurangi_cashbon' => 'required|boolean',
        'detail_aksesoris_ids' => 'nullable|array',
        'detail_aksesoris_ids.*' => 'exists:detail_pesanan_aksesoris,id',

    ]);

    $periodeAwal = now()->startOfWeek();
    $periodeAkhir = now()->endOfWeek();

    $penjahit = Penjahit::findOrFail($request->id_penjahit);
    $pengiriman = $penjahit->getPendapatanMingguIni();
    $totalPendapatan = $pengiriman->total_pendapatan ?? 0;
    $totalClaim = $pengiriman->total_claim ?? 0;
    $totalRefund = $pengiriman->total_refund_claim ?? 0;

    // Potongan hutang
    $potonganHutang = 0;
    if ($request->kurangi_hutang) {
        $hutang = Hutang::where('id_penjahit', $penjahit->id_penjahit)
                        ->orderBy('tanggal_hutang', 'desc')
                        ->first();

        if ($hutang) {
            $potongan = $hutang->is_potongan_persen
                ? ($hutang->persentase_potongan / 100) * $totalPendapatan
                : $hutang->potongan_per_minggu;

            $potonganHutang = min($hutang->jumlah_hutang, $potongan);
        }
    }

    // Potongan cashbon
    $potonganCashbon = 0;
    if ($request->kurangi_cashbon) {
        $cashbon = Cashboan::where('id_penjahit', $penjahit->id_penjahit)
                          ->orderBy('tanggal_cashboan', 'desc')
                          ->first();

        if ($cashbon) {
            $potongan = $cashbon->is_potongan_persen
                ? ($cashbon->persentase_potongan / 100) * $totalPendapatan
                : $cashbon->potongan_per_minggu;

            $potonganCashbon = min($cashbon->jumlah_cashboan, $potongan);
        }
    }

    // Potongan aksesoris
    $potonganAksesoris = 0;
    if ($request->detail_aksesoris_ids) {
        $potonganAksesoris = DetailPesananAksesoris::whereIn('id', $request->detail_aksesoris_ids)
            ->whereHas('petugasC', function ($query) use ($request) {
                $query->where('penjahit_id', $request->id_penjahit);
            })
            ->sum('total_harga');
    }

    // Cek pengiriman valid dari minggu sebelumnya yang belum masuk pendapatan
    $awalMingguIni = Carbon::now()->startOfWeek();

    $pengirimanBelumMasukPendapatan = Pengiriman::join('spk_cmt', 'pengiriman.id_spk', '=', 'spk_cmt.id_spk')
        ->where('spk_cmt.id_penjahit', $penjahit->id_penjahit)
        ->where('pengiriman.status_verifikasi', 'valid')
        ->where('pengiriman.tanggal_pengiriman', '<', $awalMingguIni)
        ->whereNotIn('pengiriman.id_pengiriman', function ($query) {
            $query->select('id_pengiriman')->from('pendapatan');
        })
        ->get();

    $totalPendapatanBelumDibayar = $pengirimanBelumMasukPendapatan->sum('total_bayar');
    $totalClaimSebelumnya = $pengirimanBelumMasukPendapatan->sum('claim');
    $totalRefundSebelumnya = $pengirimanBelumMasukPendapatan->sum('refund_claim');

    // Total transfer disimulasikan
    $totalTransfer = $totalPendapatan + $totalRefund - $totalClaim
                   + $totalPendapatanBelumDibayar + $totalRefundSebelumnya - $totalClaimSebelumnya
                   - $potonganHutang - $potonganCashbon - $potonganAksesoris;

    return response()->json([
        'total_pendapatan' => $totalPendapatan,
        'total_refund_claim' => $totalRefund,
        'total_claim' => $totalClaim,
        'pendapatan_belum_dibayar_sebelumnya' => $totalPendapatanBelumDibayar,
        'total_refund_sebelumnya' => $totalRefundSebelumnya,
        'total_claim_sebelumnya' => $totalClaimSebelumnya,
        'potongan_hutang' => $potonganHutang,
        'potongan_cashbon' => $potonganCashbon,
        'potongan_aksesoris' => $potonganAksesoris,
        'total_transfer' => $totalTransfer,
    ]);
}


public function tambahPendapatan(Request $request)
{
    $request->validate([
        'id_penjahit' => 'required|exists:penjahit_cmt,id_penjahit',
        'kurangi_hutang' => 'required|boolean',
        'kurangi_cashbon' => 'required|boolean',
        'detail_aksesoris_ids' => 'nullable|array',
        'detail_aksesoris_ids.*' => 'exists:detail_pesanan_aksesoris,id',
        'bukti_transfer' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:200048',
    ]);
    
$path = null;
if ($request->hasFile('bukti_transfer')) {
    $path = $request->file('bukti_transfer')->store('bukti_transfer_pendapatan', 'public');
}

    $periodeAwal = now()->startOfWeek();
    $periodeAkhir = now()->endOfWeek();

    if (Pendapatan::where('id_penjahit', $request->id_penjahit)
        ->whereBetween('created_at', [$periodeAwal, $periodeAkhir])->exists()) {
     return response()->json([
            'success' => false,
            'message' => 'Pendapatan minggu ini sudah dihitung.',
        ], 422);
    }

    $penjahit = Penjahit::findOrFail($request->id_penjahit);
    $pengiriman = $penjahit->getPendapatanMingguIni();
    $totalPendapatan = $pengiriman->total_pendapatan ?? 0;    
  
    $totalClaim = $pengiriman->total_claim ?? 0;
    $totalRefund = $pengiriman->total_refund_claim ?? 0;

    $potonganHutang = 0;
    $potonganCashbon = 0;


    if ($request->kurangi_hutang) {
        $hutang = Hutang::where('id_penjahit', $request->id_penjahit)
                        ->orderBy('tanggal_hutang', 'desc')
                        ->first();

        if ($hutang) {
            $potongan = $hutang->is_potongan_persen
                ? ($hutang->persentase_potongan / 100) * $totalPendapatan
                : $hutang->potongan_per_minggu;

            $penguranganHutang = min($hutang->jumlah_hutang, $potongan);
            $hutang->jumlah_hutang -= $penguranganHutang;
            $hutang->save();

            HistoryHutang::create([
                'id_hutang' => $hutang->id_hutang,
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
        $cashbon = Cashboan::where('id_penjahit', $request->id_penjahit)
                          ->orderBy('tanggal_cashboan', 'desc')
                          ->first();
    
        if ($cashbon) {
            $potongan = $cashbon->is_potongan_persen
                ? ($cashbon->persentase_potongan / 100) * $totalPendapatan
                : $cashbon->potongan_per_minggu;
    
            $penguranganCashbon = min($cashbon->jumlah_cashboan, $potongan);
            $cashbon->jumlah_cashboan -= $penguranganCashbon;
            $cashbon->save();
    
            HistoryCashboan::create([
                'id_cashboan' => $cashbon->id_cashboan, // <== tambahin ini
                'jenis_perubahan' => 'pengurangan',
                'tanggal_perubahan' => now(),
                'jumlah_cashboan' => $cashbon->jumlah_cashboan,
                'perubahan_cashboan' => $penguranganCashbon,
                'bukti_transfer' => $path,
            ]);
            
    
            $potonganCashbon = $penguranganCashbon;
        }

    }


    // Potongan aksesoris 
    $potonganAksesoris = 0;
    if ($request->detail_aksesoris_ids) {
        $potonganAksesoris = DetailPesananAksesoris::whereIn('id', $request->detail_aksesoris_ids)
            ->whereHas('petugasC', function ($query) use ($request) {
                $query->where('penjahit_id', $request->id_penjahit);
            })
            ->sum('total_harga');
    }
    
    $awalMingguIni = Carbon::now()->startOfWeek();

    $pengirimanBelumMasukPendapatan = Pengiriman::join('spk_cmt', 'pengiriman.id_spk', '=', 'spk_cmt.id_spk')
        ->where('spk_cmt.id_penjahit', $penjahit->id_penjahit)
        ->where('pengiriman.status_verifikasi', 'valid')
        ->where('pengiriman.tanggal_pengiriman', '<', $awalMingguIni)
        ->whereNotIn('pengiriman.id_pengiriman', function ($query) {
            $query->select('id_pengiriman')->from('pendapatan');
        })
        ->get();
    $totalRefundSebelumnya = $pengirimanBelumMasukPendapatan->sum('refund');
    $totalClaimSebelumnya = $pengirimanBelumMasukPendapatan->sum('claim');

    $totalPendapatanBelumDibayar = $pengirimanBelumMasukPendapatan->sum('total_bayar');
    $totalPendapatanGabungan = $totalPendapatan + $totalPendapatanBelumDibayar;
    $totalRefundGabungan = $totalRefund + $totalRefundSebelumnya;
    $totalClaimGabungan = $totalClaim + $totalClaimSebelumnya;
    
    $totalTransfer = $totalPendapatanGabungan + $totalRefundGabungan
                   - $totalClaimGabungan
                   - $potonganHutang - $potonganCashbon - $potonganAksesoris;
    

    // Simpan data pendapatan
    $pendapatan = Pendapatan::create([
        'id_penjahit' => $request->id_penjahit,
        'total_pendapatan' => $totalPendapatanGabungan,
        'total_refund_claim' => $totalRefundGabungan,
        'total_claim' => $totalClaimGabungan,
        'tanggal_pendapatan' => now(),
        'total_transfer' => $totalTransfer,
        'total_hutang' => $potonganHutang,
        'total_cashbon' => $potonganCashbon,
        'potongan_aksesoris' => $potonganAksesoris,
        'status_pembayaran' => 'sudah dibayar',
        'bukti_transfer' => $path,
    ]);

    if ($request->detail_aksesoris_ids) {
        DetailPesananAksesoris::whereIn('id', $request->detail_aksesoris_ids)
            ->update([
                'sudah_dibayar' => true,
                'id_pendapatan' => $pendapatan->id_pendapatan,
            ]);
    }
    
    return response()->json([
        'success' => true,
        'message' => 'Pendapatan berhasil ditambahkan!',
        'data' => $pendapatan
    ], 201);
}


    


}
