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
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;


class PendapatanController extends Controller
{

    public function showPengiriman($id)
    {
        $pendapatan = Pendapatan::find($id);

        if (!$pendapatan) {
            return response()->json(['message' => 'Pendapatan tidak ditemukan.'], 404);
        }
        // Ambil pengiriman melalui pivot table
        $pengiriman = $pendapatan->pengiriman;

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

    /**
     * Ambil daftar claim yang belum dibayar untuk penjahit tertentu
     */
    public function getClaimBelumDibayar($id_penjahit)
    {
        try {
            $claimBelumDibayar = Pengiriman::join('spk_cmt', 'pengiriman.id_spk', '=', 'spk_cmt.id_spk')
                ->where('spk_cmt.id_penjahit', $id_penjahit)
                ->where('pengiriman.status_claim', 'belum_dibayar')
                ->where('pengiriman.claim', '>', 0)
                ->select('pengiriman.id_pengiriman', 'pengiriman.tanggal_pengiriman', 'pengiriman.claim', 'pengiriman.total_barang_dikirim')
                ->orderBy('pengiriman.tanggal_pengiriman', 'asc')
                ->get();

            return response()->json([
                'data' => $claimBelumDibayar
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error in getClaimBelumDibayar: ' . $e->getMessage());
            return response()->json([
                'error' => 'Gagal mengambil data claim belum dibayar',
                'message' => config('app.debug') ? $e->getMessage() : 'Terjadi kesalahan pada server'
            ], 500);
        }
    }


    public function downloadNota($id)
    {
        $pendapatan = Pendapatan::with('penjahit')->findOrFail($id);

        // Ambil pengiriman melalui pivot table
        $pengiriman = $pendapatan->pengiriman;
        $penjahit = $pendapatan->penjahit;

        // Hitung periode dari tanggal pengiriman
        if ($pengiriman->isNotEmpty()) {
            $periodeAwal = Carbon::parse($pengiriman->min('tanggal_pengiriman'))->startOfDay();
            $periodeAkhir = Carbon::parse($pengiriman->max('tanggal_pengiriman'))->endOfDay();
        } else {
            $periodeAwal = Carbon::now()->startOfDay();
            $periodeAkhir = Carbon::now()->endOfDay();
        }

        $pdf = Pdf::loadView('pdf.nota', compact('pendapatan', 'pengiriman', 'penjahit', 'periodeAwal', 'periodeAkhir'))
            ->setPaper('a4');

        return $pdf->download('Nota' . $pendapatan->id_penjahit . '.pdf');
    }

    /**
     * Download Invoice untuk pendapatan yang sudah dibayar
     */
    public function downloadInvoice($id)
    {
        $pendapatan = Pendapatan::with('penjahit')->findOrFail($id);

        // Ambil pengiriman yang terkait dengan pendapatan ini melalui pivot table
        // Query menggunakan join seperti downloadNota() untuk konsistensi
        // Ambil pengiriman melalui pivot table dengan relasi produk
        $pengiriman = $pendapatan->pengiriman()->with('spk.produk')->get();

        // Tambahkan nama_produk ke setiap item pengiriman untuk kompatibilitas dengan view
        $pengiriman = $pengiriman->map(function ($item) {
            $item->nama_produk = $item->spk && $item->spk->produk ? $item->spk->produk->nama_produk : null;
            return $item;
        });

        $penjahit = $pendapatan->penjahit;

        // Hitung periode dari tanggal pengiriman
        if ($pengiriman->isNotEmpty()) {
            $periodeAwal = Carbon::parse($pengiriman->min('tanggal_pengiriman'))->startOfDay();
            $periodeAkhir = Carbon::parse($pengiriman->max('tanggal_pengiriman'))->endOfDay();
        } else {
            $periodeAwal = Carbon::now()->startOfDay();
            $periodeAkhir = Carbon::now()->endOfDay();
        }

        $pdf = Pdf::loadView('pdf.nota', compact('pendapatan', 'pengiriman', 'penjahit', 'periodeAwal', 'periodeAkhir'))
            ->setPaper('a4');

        return $pdf->download('Invoice-Pendapatan-' . $pendapatan->id_pendapatan . '.pdf');
    }

    /**
     * Download PDF Preview Invoice untuk pendapatan yang belum dibayar
     */
    public function downloadInvoicePreview(Request $request)
    {
        try {
            // Validasi request
            $request->validate([
                'id_penjahit' => 'required|exists:penjahit_cmt,id_penjahit',
                'tanggal_awal' => 'required|date',
                'tanggal_akhir' => 'required|date|after_or_equal:tanggal_awal',
            ]);

            $penjahit = Penjahit::findOrFail($request->id_penjahit);

            $startDate = Carbon::parse($request->tanggal_awal)->startOfDay();
            $endDate = Carbon::parse($request->tanggal_akhir)->endOfDay();

            // Ambil pengiriman yang belum dibayar - menggunakan whereHas seperti PendapatanJasaController
            $pengiriman = Pengiriman::whereHas('spk', function ($query) use ($request) {
                $query->where('id_penjahit', $request->id_penjahit);
            })
                ->where('status_verifikasi', 'valid')
                ->whereBetween('tanggal_pengiriman', [$startDate, $endDate])
                ->whereNotIn('id_pengiriman', function ($query) {
                    $query->select('id_pengiriman')
                        ->from('pengiriman_pendapatan');
                })
                ->with(['spk.produk'])
                ->get();

            if ($pengiriman->isEmpty()) {
                return response()->json(['message' => 'Tidak ada data untuk di-generate invoice'], 404);
            }

            $totalPendapatan = $pengiriman->sum('total_bayar') ?? 0;
            $totalClaim = $pengiriman->sum('claim') ?? 0;
            $totalRefund = $pengiriman->sum('refund_claim') ?? 0;

            // Hitung potongan hutang
            $hutang = Hutang::where('id_penjahit', $request->id_penjahit)
                ->latest('tanggal_hutang')
                ->first();

            $potonganHutang = 0;
            if ($hutang && isset($hutang->jumlah_hutang) && $hutang->jumlah_hutang > 0) {
                if (isset($hutang->is_potongan_persen) && $hutang->is_potongan_persen) {
                    $potonganHutang = (($hutang->persentase_potongan ?? 0) / 100) * $totalPendapatan;
                } else {
                    $potonganPerMinggu = $hutang->potongan_per_minggu ?? 0;
                    $potonganHutang = min($potonganPerMinggu, $hutang->jumlah_hutang);
                }
            }

            // Hitung potongan cashbon - SUM semua yang status belum lunas
            $totalCashbon = Cashboan::where('id_penjahit', $request->id_penjahit)
                ->where('status_pembayaran', 'belum lunas')
                ->sum('jumlah_cashboan');

            $potonganCashbon = 0;
            if ($totalCashbon && $totalCashbon > 0) {
                $potonganCashbon = min((int) $totalCashbon, $totalPendapatan);
            }

            // Potongan aksesoris
            $potonganAksesoris = DetailPesananAksesoris::whereBetween('created_at', [$startDate, $endDate])
                ->whereHas('petugasC', function ($query) use ($request) {
                    $query->where('penjahit_id', $request->id_penjahit);
                })
                ->where('sudah_dibayar', false)
                ->sum('total_harga');

            $totalTransfer = $totalPendapatan + $totalRefund - $totalClaim - $potonganHutang - $potonganCashbon - $potonganAksesoris;

            // Buat objek pendapatan dummy untuk preview
            $pendapatanPreview = (object) [
                'id_pendapatan' => 'PREVIEW',
                'total_pendapatan' => $totalPendapatan,
                'total_claim' => $totalClaim,
                'total_refund_claim' => $totalRefund,
                'total_hutang' => $potonganHutang,
                'total_cashbon' => $potonganCashbon,
                'potongan_aksesoris' => $potonganAksesoris,
                'total_transfer' => $totalTransfer,
                'status_pembayaran' => 'belum dibayar',
                'handtag' => 0,
                'transportasi' => 0,
            ];

            $periodeAwal = Carbon::parse($request->tanggal_awal);
            $periodeAkhir = Carbon::parse($request->tanggal_akhir);

            // Format data untuk PDF
            $data = [
                'pendapatan' => $pendapatanPreview,
                'pengiriman' => $pengiriman,
                'penjahit' => $penjahit,
                'periodeAwal' => $periodeAwal,
                'periodeAkhir' => $periodeAkhir,
            ];

            // Generate PDF
            $pdf = Pdf::loadView('pdf.nota_preview', $data);

            // Set nama file
            $fileName = 'Invoice-Preview-Pendapatan-' . $penjahit->id_penjahit . '_' . date('Y-m-d') . '.pdf';

            // Download PDF
            return $pdf->download($fileName);
        } catch (\Exception $e) {
            Log::error('Error in downloadInvoicePreview: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'Gagal mengunduh preview invoice: ' . $e->getMessage()
            ], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $startDate = $request->start_date
                ? Carbon::parse($request->start_date)->startOfDay()
                : now()->startOfMonth();

            $endDate = $request->end_date
                ? Carbon::parse($request->end_date)->endOfDay()
                : now()->endOfMonth();

            $data = Penjahit::all()->map(function ($penjahit) use ($startDate, $endDate) {
                try {
                    // Query pengiriman yang belum dibayar (belum ada di pivot table pengiriman_pendapatan)
                    $pengiriman = Pengiriman::join('spk_cmt', 'pengiriman.id_spk', '=', 'spk_cmt.id_spk')
                        ->where('spk_cmt.id_penjahit', $penjahit->id_penjahit)
                        ->where('pengiriman.status_verifikasi', 'valid')
                        ->whereBetween('pengiriman.tanggal_pengiriman', [$startDate, $endDate])
                        ->whereNotIn('pengiriman.id_pengiriman', function ($query) {
                            $query->select('id_pengiriman')
                                ->from('pengiriman_pendapatan');
                        })
                        ->select('pengiriman.*')
                        ->get();

                    $totalPendapatan = $pengiriman->sum('total_bayar') ?? 0;
                    $totalClaim = $pengiriman->sum('claim') ?? 0;
                    $totalRefund = $pengiriman->sum('refund_claim') ?? 0;

                    // Hutang
                    $hutang = Hutang::where('id_penjahit', $penjahit->id_penjahit)
                        ->latest('tanggal_hutang')
                        ->first();

                    $potonganHutang = 0;
                    if ($hutang && isset($hutang->jumlah_hutang) && $hutang->jumlah_hutang > 0) {
                        if (isset($hutang->is_potongan_persen) && $hutang->is_potongan_persen) {
                            $potonganHutang = (($hutang->persentase_potongan ?? 0) / 100) * $totalPendapatan;
                        } else {
                            $potonganPerMinggu = $hutang->potongan_per_minggu ?? 0;
                            $potonganHutang = min($potonganPerMinggu, $hutang->jumlah_hutang);
                        }
                    }

                    // Cashbon - SUM semua cashbon yang status belum lunas
                    $totalCashbon = Cashboan::where('id_penjahit', $penjahit->id_penjahit)
                        ->where('status_pembayaran', 'belum lunas')
                        ->sum('jumlah_cashboan');

                    $potonganCashbon = 0;
                    if ($totalCashbon && $totalCashbon > 0) {
                        $potonganCashbon = min((int) $totalCashbon, $totalPendapatan);
                    }

                    // Potongan aksesoris
                    $potonganAksesoris = DetailPesananAksesoris::whereBetween('created_at', [$startDate, $endDate])
                        ->whereHas('petugasC', function ($query) use ($penjahit) {
                            $query->where('penjahit_id', $penjahit->id_penjahit);
                        })
                        ->where('sudah_dibayar', false)
                        ->sum('total_harga');

                    return [
                        'id_penjahit' => $penjahit->id_penjahit,
                        'nama_penjahit' => $penjahit->nama_penjahit ?? '-',
                        'bank' => $penjahit->bank ?? '-',
                        'no_rekening' => $penjahit->no_rekening ?? '-',
                        'periode' => [
                            'start' => $startDate->toDateString(),
                            'end'   => $endDate->toDateString(),
                        ],
                        'jumlah_pengiriman' => $pengiriman->count(),
                        'total_pendapatan' => $totalPendapatan,
                        'total_claim' => $totalClaim,
                        'total_refund_claim' => $totalRefund,
                        'potongan_hutang'  => $potonganHutang,
                        'potongan_cashbon' => $potonganCashbon,
                        'potongan_aksesoris' => $potonganAksesoris,
                        'total_transfer'   => $totalPendapatan + $totalRefund - $totalClaim - $potonganHutang - $potonganCashbon - $potonganAksesoris,
                        'pendapatan_id' => null, // Selalu null agar bisa dibayar kapan saja
                    ];
                } catch (\Exception $e) {
                    Log::error('Error processing penjahit ' . $penjahit->id_penjahit . ': ' . $e->getMessage());

                    return [
                        'id_penjahit' => $penjahit->id_penjahit,
                        'nama_penjahit' => $penjahit->nama_penjahit ?? '-',
                        'periode' => [
                            'start' => $startDate->toDateString(),
                            'end'   => $endDate->toDateString(),
                        ],
                        'jumlah_pengiriman' => 0,
                        'total_pendapatan' => 0,
                        'total_claim' => 0,
                        'total_refund_claim' => 0,
                        'potongan_hutang'  => 0,
                        'potongan_cashbon' => 0,
                        'potongan_aksesoris' => 0,
                        'total_transfer'   => 0,
                        'pendapatan_id' => null,
                    ];
                }
            });

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error in PendapatanController@index: ' . $e->getMessage());

            return response()->json([
                'error' => 'Gagal mengambil data pendapatan',
                'message' => config('app.debug') ? $e->getMessage() : 'Terjadi kesalahan pada server'
            ], 500);
        }
    }

    public function simulasiPendapatan(Request $request)
    {
        try {
            $request->validate([
                'id_penjahit' => 'required|exists:penjahit_cmt,id_penjahit',
                'tanggal_awal'  => 'required|date',
                'tanggal_akhir' => 'required|date|after_or_equal:tanggal_awal',
                'kurangi_hutang' => 'required|boolean',
                'kurangi_cashbon' => 'required|boolean',
                'detail_aksesoris_ids' => 'nullable|array',
                'detail_aksesoris_ids.*' => 'exists:detail_pesanan_aksesoris,id',
                'claim_ids' => 'nullable|array',
                'claim_ids.*' => 'exists:pengiriman,id_pengiriman',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error in simulasiPendapatan:', $e->errors());
            return response()->json([
                'error' => 'Validation failed',
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
                'total_pendapatan' => 0,
                'potongan_hutang' => 0,
                'potongan_cashbon' => 0,
                'potongan_aksesoris' => 0,
                'total_transfer' => 0,
            ], 422);
        }

        try {
            $startDate = Carbon::parse($request->tanggal_awal)->startOfDay();
            $endDate = Carbon::parse($request->tanggal_akhir)->endOfDay();

            // Query pengiriman yang belum dibayar
            $pengiriman = Pengiriman::join('spk_cmt', 'pengiriman.id_spk', '=', 'spk_cmt.id_spk')
                ->where('spk_cmt.id_penjahit', $request->id_penjahit)
                ->where('pengiriman.status_verifikasi', 'valid')
                ->whereBetween('pengiriman.tanggal_pengiriman', [$startDate, $endDate])
                ->whereNotIn('pengiriman.id_pengiriman', function ($query) {
                    $query->select('id_pengiriman')
                        ->from('pengiriman_pendapatan');
                })
                ->select('pengiriman.*')
                ->get();

            $totalPendapatan = $pengiriman->sum('total_bayar') ?? 0;

            // Hitung totalClaim hanya dari claim yang dipilih
            $totalClaim = 0;
            if ($request->claim_ids && count($request->claim_ids) > 0) {
                $totalClaim = Pengiriman::whereIn('id_pengiriman', $request->claim_ids)
                    ->where('status_claim', 'belum_dibayar')
                    ->sum('claim') ?? 0;
            }

            $totalRefund = $pengiriman->sum('refund_claim') ?? 0;

            // Hutang
            $potonganHutang = 0;
            if ($request->kurangi_hutang) {
                $hutang = Hutang::where('id_penjahit', $request->id_penjahit)
                    ->latest('tanggal_hutang')
                    ->first();

                if ($hutang && isset($hutang->jumlah_hutang) && $hutang->jumlah_hutang > 0) {
                    if (isset($hutang->is_potongan_persen) && $hutang->is_potongan_persen) {
                        $potonganHutang = (($hutang->persentase_potongan ?? 0) / 100) * $totalPendapatan;
                    } else {
                        $potonganPerMinggu = $hutang->potongan_per_minggu ?? 0;
                        $potonganHutang = min($potonganPerMinggu, $hutang->jumlah_hutang);
                    }
                }
            }

            // Cashbon - SUM semua cashbon yang status belum lunas
            $potonganCashbon = 0;
            if ($request->kurangi_cashbon) {
                $totalCashbon = Cashboan::where('id_penjahit', $request->id_penjahit)
                    ->where('status_pembayaran', 'belum lunas')
                    ->sum('jumlah_cashboan');

                if ($totalCashbon && $totalCashbon > 0) {
                    $potonganCashbon = min((int) $totalCashbon, $totalPendapatan);
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

            $totalTransfer = $totalPendapatan + $totalRefund - $totalClaim - $potonganHutang - $potonganCashbon - $potonganAksesoris;

            return response()->json([
                'total_pendapatan' => (float) $totalPendapatan,
                'total_claim' => (float) $totalClaim,
                'total_refund_claim' => (float) $totalRefund,
                'potongan_hutang'  => (float) $potonganHutang,
                'potongan_cashbon' => (float) $potonganCashbon,
                'potongan_aksesoris' => (float) $potonganAksesoris,
                'total_transfer'   => (float) $totalTransfer,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in simulasiPendapatan: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'error' => 'Gagal mengambil data simulasi',
                'message' => config('app.debug') ? $e->getMessage() : 'Terjadi kesalahan pada server',
                'total_pendapatan' => 0,
                'potongan_hutang' => 0,
                'potongan_cashbon' => 0,
                'potongan_aksesoris' => 0,
                'total_transfer' => 0,
            ], 500);
        }
    }


    public function tambahPendapatan(Request $request)
    {
        $request->validate([
            'id_penjahit' => 'required|exists:penjahit_cmt,id_penjahit',
            'tanggal_awal'   => 'required|date',
            'tanggal_akhir'  => 'required|date|after_or_equal:tanggal_awal',
            'kurangi_hutang' => 'required|boolean',
            'kurangi_cashbon' => 'required|boolean',
            'detail_aksesoris_ids' => 'nullable|array',
            'detail_aksesoris_ids.*' => 'exists:detail_pesanan_aksesoris,id',
            'claim_ids' => 'nullable|array',
            'claim_ids.*' => 'exists:pengiriman,id_pengiriman',
            'bukti_transfer' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:200048',
        ]);

        DB::beginTransaction();

        try {
            $path = null;
            if ($request->hasFile('bukti_transfer')) {
                $path = $request->file('bukti_transfer')
                    ->store('bukti_transfer_pendapatan', 'public');
            }

            $startDate = Carbon::parse($request->tanggal_awal)->startOfDay();
            $endDate = Carbon::parse($request->tanggal_akhir)->endOfDay();

            // 🔹 Ambil pengiriman BELUM dibayar (belum ada di pivot table)
            $pengiriman = Pengiriman::join('spk_cmt', 'pengiriman.id_spk', '=', 'spk_cmt.id_spk')
                ->where('spk_cmt.id_penjahit', $request->id_penjahit)
                ->where('pengiriman.status_verifikasi', 'valid')
                ->whereBetween('pengiriman.tanggal_pengiriman', [$startDate, $endDate])
                ->whereNotIn('pengiriman.id_pengiriman', function ($query) {
                    $query->select('id_pengiriman')
                        ->from('pengiriman_pendapatan');
                })
                ->select('pengiriman.*')
                ->lockForUpdate()
                ->get();

            if ($pengiriman->isEmpty()) {
                return response()->json([
                    'message' => 'Tidak ada pengiriman yang bisa dibayarkan'
                ], 422);
            }

            $totalPendapatan = $pengiriman->sum('total_bayar') ?? 0;

            // Hitung totalClaim hanya dari claim yang dipilih
            $totalClaim = 0;
            if ($request->claim_ids && count($request->claim_ids) > 0) {
                $totalClaim = Pengiriman::whereIn('id_pengiriman', $request->claim_ids)
                    ->where('status_claim', 'belum_dibayar')
                    ->sum('claim') ?? 0;
            }

            $totalRefund = $pengiriman->sum('refund_claim') ?? 0;

            // ===============================
            // 🔹 POTONGAN HUTANG
            // ===============================
            $potonganHutang = 0;

            if ($request->kurangi_hutang) {
                $hutang = Hutang::where('id_penjahit', $request->id_penjahit)
                    ->orderBy('tanggal_hutang', 'desc')
                    ->first();

                if ($hutang && $hutang->jumlah_hutang > 0) {
                    $potongan = $hutang->is_potongan_persen
                        ? ($hutang->persentase_potongan / 100) * $totalPendapatan
                        : $hutang->potongan_per_minggu;

                    $potonganHutang = min($hutang->jumlah_hutang, $potongan);
                    $hutang->jumlah_hutang -= $potonganHutang;
                    $hutang->save();

                    HistoryHutang::create([
                        'id_hutang' => $hutang->id_hutang,
                        'jenis_perubahan' => 'pengurangan',
                        'tanggal_perubahan' => now(),
                        'jumlah_hutang' => $hutang->jumlah_hutang,
                        'perubahan_hutang' => $potonganHutang,
                        'bukti_transfer' => $path,
                    ]);
                }
            }

            // ===============================
            // 🔹 POTONGAN CASHBON - ambil semua yang status belum lunas dan kurangi
            // ===============================
            $potonganCashbon = 0;

            if ($request->kurangi_cashbon) {
                // Ambil total cashbon yang belum lunas
                $totalCashbon = Cashboan::where('id_penjahit', $request->id_penjahit)
                    ->where('status_pembayaran', 'belum lunas')
                    ->sum('jumlah_cashboan');

                if ($totalCashbon && $totalCashbon > 0) {
                    $potonganCashbon = min((int) $totalCashbon, $totalPendapatan);

                    // Kurangi dari semua cashbon records (mulai dari yang paling lama)
                    $sisaPotongan = $potonganCashbon;
                    $cashbons = Cashboan::where('id_penjahit', $request->id_penjahit)
                        ->where('status_pembayaran', 'belum lunas')
                        ->orderBy('tanggal_cashboan', 'asc')
                        ->get();

                    foreach ($cashbons as $cashbon) {
                        if ($sisaPotongan <= 0) break;

                        $jumlahDikurangkan = min((int) $cashbon->jumlah_cashboan, $sisaPotongan);
                        $cashbon->jumlah_cashboan = (int) $cashbon->jumlah_cashboan - $jumlahDikurangkan;

                        // Update status jika sudah lunas
                        if ($cashbon->jumlah_cashboan <= 0) {
                            $cashbon->status_pembayaran = 'lunas';
                        }

                        $cashbon->save();

                        // Buat history untuk setiap pengurangan
                        HistoryCashboan::create([
                            'id_cashboan' => $cashbon->id_cashboan,
                            'jenis_perubahan' => 'pengurangan',
                            'tanggal_perubahan' => now(),
                            'jumlah_cashboan' => $cashbon->jumlah_cashboan,
                            'perubahan_cashboan' => $jumlahDikurangkan,
                            'bukti_transfer' => $path,
                        ]);

                        $sisaPotongan -= $jumlahDikurangkan;
                    }
                }
            }

            // ===============================
            // 🔹 POTONGAN AKSESORIS
            // ===============================
            $potonganAksesoris = 0;
            if ($request->detail_aksesoris_ids) {
                $potonganAksesoris = DetailPesananAksesoris::whereIn('id', $request->detail_aksesoris_ids)
                    ->whereHas('petugasC', function ($query) use ($request) {
                        $query->where('penjahit_id', $request->id_penjahit);
                    })
                    ->sum('total_harga');
            }

            // ===============================
            // 🔹 SIMPAN PENDAPATAN
            // ===============================
            $totalTransfer = $totalPendapatan + $totalRefund - $totalClaim - $potonganHutang - $potonganCashbon - $potonganAksesoris;

            $pendapatan = Pendapatan::create([
                'id_penjahit' => $request->id_penjahit,
                'total_pendapatan' => $totalPendapatan,
                'total_refund_claim' => $totalRefund,
                'total_claim' => $totalClaim,
                'total_transfer' => $totalTransfer,
                'total_hutang' => $potonganHutang,
                'total_cashbon' => $potonganCashbon,
                'potongan_aksesoris' => $potonganAksesoris,
                'status_pembayaran' => 'sudah dibayar',
                'bukti_transfer' => $path,
            ]);

            // 🔹 ATTACH PENGIRIMAN KE PIVOT TABLE
            $pendapatan->pengiriman()->attach($pengiriman->pluck('id_pengiriman'));

            // 🔹 UPDATE AKSESORIS SUDAH DIBAYAR
            if ($request->detail_aksesoris_ids) {
                DetailPesananAksesoris::whereIn('id', $request->detail_aksesoris_ids)
                    ->update([
                        'sudah_dibayar' => true,
                        'id_pendapatan' => $pendapatan->id_pendapatan,
                    ]);
            }

            // 🔹 UPDATE STATUS CLAIM MENJADI SUDAH DIBAYAR
            if ($request->claim_ids && count($request->claim_ids) > 0) {
                Pengiriman::whereIn('id_pengiriman', $request->claim_ids)
                    ->where('status_claim', 'belum_dibayar')
                    ->update([
                        'status_claim' => 'sudah_dibayar'
                    ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Pendapatan berhasil dibayarkan',
                'data' => $pendapatan
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
