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
use Illuminate\Support\Facades\DB;



class PendapatanJasaController extends Controller
{

   public function index(Request $request)
{
    $startDate = $request->start_date
        ? Carbon::parse($request->start_date)->startOfDay()
        : now()->startOfWeek();

    $endDate = $request->end_date
        ? Carbon::parse($request->end_date)->endOfDay()
        : now()->endOfWeek();

    $data = TukangJasa::all()->map(function ($tukang) use ($startDate, $endDate) {

        $hasilJasa = HasilJasa::whereHas('spkJasa', fn ($q) =>
                $q->where('tukang_jasa_id', $tukang->id)
            )
            ->where('status_bayar', 'belum_dibayar')
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->get();

        $totalPendapatan = $hasilJasa->sum('total_pendapatan');

        // Hutang
        $hutang = HutangJasa::where('tukang_jasa_id', $tukang->id)
            ->latest('tanggal_hutang')
            ->first();

        $potonganHutang = ($hutang && $hutang->jumlah_hutang > 0)
            ? (
                $hutang->is_potongan_persen
                    ? ($hutang->persentase_potongan / 100) * $totalPendapatan
                    : min($hutang->potongan_per_minggu, $hutang->jumlah_hutang)
            )
            : 0;

        // Cashbon
        $cashbon = CashboanJasa::where('tukang_jasa_id', $tukang->id)
            ->latest('tanggal_cashboan')
            ->first();

        $potonganCashbon = $cashbon
            ? min($cashbon->jumlah_cashboan, $totalPendapatan)
            : 0;

        return [
            'tukang_jasa_id' => $tukang->id,
            'nama_tukang_jasa' => $tukang->nama,
            'periode' => [
                'start' => $startDate->toDateString(),
                'end'   => $endDate->toDateString(),
            ],
            'jumlah_pengiriman' => $hasilJasa->count(),
            'total_pendapatan' => $totalPendapatan,
            'potongan_hutang'  => $potonganHutang,
            'potongan_cashbon' => $potonganCashbon,
            'total_transfer'   => $totalPendapatan - $potonganHutang - $potonganCashbon,
        ];
    });

    return response()->json($data);
}

 public function simulasiPendapatanJasa(Request $request)
{
    $request->validate([
        'tukang_jasa_id' => 'required|exists:tukang_jasa,id',
        'tanggal_awal'  => 'required|date',
        'tanggal_akhir' => 'required|date|after_or_equal:tanggal_awal',
        'kurangi_hutang'=> 'required|boolean',
        'kurangi_cashbon'=> 'required|boolean',
    ]);

    $hasilJasa = HasilJasa::whereHas('spkJasa', fn ($q) =>
            $q->where('tukang_jasa_id', $request->tukang_jasa_id)
        )
        ->where('status_bayar', 'belum_dibayar')
        ->whereBetween('tanggal', [
            $request->tanggal_awal,
            $request->tanggal_akhir
        ])
        ->get();

    $totalPendapatan = $hasilJasa->sum('total_pendapatan');

    // Hutang
    $potonganHutang = 0;
    if ($request->kurangi_hutang) {
        $hutang = HutangJasa::where('tukang_jasa_id', $request->tukang_jasa_id)
            ->latest('tanggal_hutang')
            ->first();

        if ($hutang && $hutang->jumlah_hutang > 0) {
            $potongan = $hutang->is_potongan_persen
                ? ($hutang->persentase_potongan / 100) * $totalPendapatan
                : $hutang->potongan_per_minggu;

            $potonganHutang = min($hutang->jumlah_hutang, $potongan);
        }
    }

    // Cashbon
    $potonganCashbon = 0;
    if ($request->kurangi_cashbon) {
        $cashbon = CashboanJasa::where('tukang_jasa_id', $request->tukang_jasa_id)
            ->latest('tanggal_cashboan')
            ->first();

        if ($cashbon) {
            $potonganCashbon = min($cashbon->jumlah_cashboan, $totalPendapatan);
        }
    }

    return response()->json([
        'total_pendapatan' => $totalPendapatan,
        'potongan_hutang'  => $potonganHutang,
        'potongan_cashbon' => $potonganCashbon,
        'total_transfer'   => $totalPendapatan - $potonganHutang - $potonganCashbon,
    ]);
}


      public function tambahPendapatanJasa(Request $request)
{
    $request->validate([
        'tukang_jasa_id' => 'required|exists:tukang_jasa,id',
        'tanggal_awal'   => 'required|date',
        'tanggal_akhir'  => 'required|date|after_or_equal:tanggal_awal',
        'kurangi_hutang' => 'required|boolean',
        'kurangi_cashbon'=> 'required|boolean',
        'bukti_transfer' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:200048',
    ]);

    DB::beginTransaction();

    try {
        $path = null;
        if ($request->hasFile('bukti_transfer')) {
            $path = $request->file('bukti_transfer')
                ->store('bukti_transfer_pendapatan_jasa', 'public');
        }

        // 🔹 Ambil hasil jasa BELUM dibayar
        $hasilJasa = HasilJasa::whereHas('spkJasa', function ($q) use ($request) {
                $q->where('tukang_jasa_id', $request->tukang_jasa_id);
            })
            ->where('status_bayar', 'belum_dibayar')
            ->whereBetween('tanggal', [
                $request->tanggal_awal,
                $request->tanggal_akhir
            ])
            ->lockForUpdate()
            ->get();

        if ($hasilJasa->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada hasil jasa yang bisa dibayarkan'
            ], 422);
        }

        $totalPendapatan = $hasilJasa->sum('total_pendapatan');

        // ===============================
        // 🔹 POTONGAN HUTANG
        // ===============================
        $potonganHutang = 0;

        if ($request->kurangi_hutang) {
            $hutang = HutangJasa::where('tukang_jasa_id', $request->tukang_jasa_id)
                ->orderBy('tanggal_hutang', 'desc')
                ->first();

            if ($hutang && $hutang->jumlah_hutang > 0) {
                $potongan = $hutang->is_potongan_persen
                    ? ($hutang->persentase_potongan / 100) * $totalPendapatan
                    : $hutang->potongan_per_minggu;

                $potonganHutang = min($hutang->jumlah_hutang, $potongan);
                $hutang->jumlah_hutang -= $potonganHutang;
                $hutang->save();

                HistoryHutangJasa::create([
                    'hutang_jasa_id' => $hutang->id,
                    'jenis_perubahan'=> 'pengurangan',
                    'tanggal_perubahan' => now(),
                    'jumlah_hutang' => $hutang->jumlah_hutang,
                    'perubahan_hutang' => $potonganHutang,
                    'bukti_transfer' => $path,
                ]);
            }
        }

        // ===============================
        // 🔹 POTONGAN CASHBON
        // ===============================
        $potonganCashbon = 0;

        if ($request->kurangi_cashbon) {
            $cashbon = CashboanJasa::where('tukang_jasa_id', $request->tukang_jasa_id)
                ->orderBy('tanggal_cashboan', 'desc')
                ->first();

            if ($cashbon && $cashbon->jumlah_cashboan > 0) {
                $potonganCashbon = min($cashbon->jumlah_cashboan, $totalPendapatan);
                $cashbon->jumlah_cashboan -= $potonganCashbon;
                $cashbon->save();

                HistoryCashboanJasa::create([
                    'cashboan_jasa_id' => $cashbon->id,
                    'jenis_perubahan' => 'pengurangan',
                    'tanggal_perubahan' => now(),
                    'jumlah_cashboan' => $cashbon->jumlah_cashboan,
                    'perubahan_cashboan' => $potonganCashbon,
                    'bukti_transfer' => $path,
                ]);
            }
        }

        // ===============================
        // 🔹 SIMPAN PENDAPATAN
        // ===============================
        $totalTransfer = $totalPendapatan - $potonganHutang - $potonganCashbon;

        $pendapatan = PendapatanJasa::create([
            'tukang_jasa_id' => $request->tukang_jasa_id,
            'tanggal_pendapatan' => now(),
            'total_pendapatan' => $totalPendapatan,
            'total_transfer' => $totalTransfer,
            'total_hutang' => $potonganHutang,
            'total_cashbon' => $potonganCashbon,
            'status_pembayaran' => 'sudah_dibayar',
            'bukti_transfer' => $path,
        ]);

        // 🔹 UPDATE HASIL JASA → SUDAH DIBAYAR
        HasilJasa::whereIn('id', $hasilJasa->pluck('id'))
            ->update([
                'status_bayar' => 'sudah_dibayar',
                'pendapatan_jasa_id' => $pendapatan->id,
            ]);

        DB::commit();

        return response()->json([
            'message' => 'Pendapatan jasa berhasil dibayarkan',
            'data' => $pendapatan
        ], 201);

    } catch (\Throwable $e) {
        DB::rollBack();
        throw $e;
    }
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
