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
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;



class PendapatanJasaController extends Controller
{

    public function index(Request $request)
    {
        try {
            $startDate = $request->start_date
                ? Carbon::parse($request->start_date)->startOfDay()
                : now()->startOfMonth();

            $endDate = $request->end_date
                ? Carbon::parse($request->end_date)->endOfDay()
                : now()->endOfMonth();

            $data = TukangJasa::all()->map(function ($tukang) use ($startDate, $endDate) {
                try {
                    // Query hasil jasa dengan relasi spkJasa
                    // Gunakan join untuk menghindari masalah relasi
                    // Ambil data yang belum dibayar (status_bayar = 'belum_dibayar' atau NULL)
                    // Juga pastikan total_pendapatan tidak NULL dan > 0
                    $hasilJasa = HasilJasa::join('spk_jasa', 'hasil_jasa.spk_jasa_id', '=', 'spk_jasa.id')
                        ->where('spk_jasa.tukang_jasa_id', $tukang->id)
                        ->where(function ($q) {
                            $q->where('hasil_jasa.status_bayar', 'belum_dibayar')
                                ->orWhereNull('hasil_jasa.status_bayar');
                        })
                        ->whereNotNull('hasil_jasa.total_pendapatan')
                        ->where('hasil_jasa.total_pendapatan', '>', 0)
                        ->whereBetween('hasil_jasa.tanggal', [$startDate, $endDate])
                        ->select('hasil_jasa.*')
                        ->get();

                    $totalPendapatan = $hasilJasa->sum('total_pendapatan') ?? 0;

                    // Debug logging (hanya di development)
                    if (config('app.debug') && $tukang->id <= 3) {
                        Log::info("Tukang Jasa ID: {$tukang->id}, Nama: {$tukang->nama}");
                        Log::info("Periode: {$startDate->toDateString()} - {$endDate->toDateString()}");
                        Log::info("Jumlah hasil jasa: " . $hasilJasa->count());
                        Log::info("Total pendapatan: " . $totalPendapatan);
                    }

                    // Hutang
                    $hutang = HutangJasa::where('tukang_jasa_id', $tukang->id)
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

                    // Cashbon - SUM semua cashbon yang status belum lunas (untuk handle multiple records)
                    $totalCashbon = CashboanJasa::where('tukang_jasa_id', $tukang->id)
                        ->where('status_pembayaran', 'belum lunas')
                        ->sum('jumlah_cashboan');

                    $potonganCashbon = 0;
                    if ($totalCashbon && $totalCashbon > 0) {
                        $potonganCashbon = min((int) $totalCashbon, $totalPendapatan);

                        // Debug logging untuk cashbon
                        if (config('app.debug')) {
                            Log::info("Cashbon found for {$tukang->nama} (ID: {$tukang->id}):", [
                                'total_cashbon' => $totalCashbon,
                                'potongan_cashbon' => $potonganCashbon,
                                'total_pendapatan' => $totalPendapatan
                            ]);
                        }
                    }

                    // Hapus pengecekan pendapatan sudah dibayar untuk periode ini
                    // Sekarang pendapatan bisa dibayar kapan saja

                    return [
                        'tukang_jasa_id' => $tukang->id,
                        'nama_tukang_jasa' => $tukang->nama ?? '-',
                        'periode' => [
                            'start' => $startDate->toDateString(),
                            'end'   => $endDate->toDateString(),
                        ],
                        'jumlah_pengiriman' => $hasilJasa->count(),
                        'total_pendapatan' => $totalPendapatan,
                        'potongan_hutang'  => $potonganHutang,
                        'potongan_cashbon' => $potonganCashbon,
                        'total_transfer'   => $totalPendapatan - $potonganHutang - $potonganCashbon,
                        'pendapatan_id' => null, // Selalu null agar bisa dibayar kapan saja
                    ];
                } catch (\Exception $e) {
                    Log::error('Error processing tukang jasa ' . $tukang->id . ': ' . $e->getMessage());
                    Log::error('Stack trace: ' . $e->getTraceAsString());

                    return [
                        'tukang_jasa_id' => $tukang->id,
                        'nama_tukang_jasa' => $tukang->nama ?? '-',
                        'periode' => [
                            'start' => $startDate->toDateString(),
                            'end'   => $endDate->toDateString(),
                        ],
                        'jumlah_pengiriman' => 0,
                        'total_pendapatan' => 0,
                        'potongan_hutang'  => 0,
                        'potongan_cashbon' => 0,
                        'total_transfer'   => 0,
                        'pendapatan_id' => null, // Selalu null agar bisa dibayar kapan saja
                    ];
                }
            });

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error in PendapatanJasaController@index: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'error' => 'Gagal mengambil data pendapatan',
                'message' => config('app.debug') ? $e->getMessage() : 'Terjadi kesalahan pada server'
            ], 500);
        }
    }

    public function simulasiPendapatanJasa(Request $request)
    {
        try {
            $request->validate([
                'tukang_jasa_id' => 'required|exists:tukang_jasa,id',
                'tanggal_awal'  => 'required|date',
                'tanggal_akhir' => 'required|date|after_or_equal:tanggal_awal',
                'kurangi_hutang' => 'required|boolean',
                'kurangi_cashbon' => 'required|boolean',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error in simulasiPendapatanJasa:', $e->errors());
            return response()->json([
                'error' => 'Validation failed',
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
                'total_pendapatan' => 0,
                'potongan_hutang' => 0,
                'potongan_cashbon' => 0,
                'total_transfer' => 0,
            ], 422);
        }

        try {
            // Query hasil jasa dengan relasi spkJasa - sama seperti di index()
            $hasilJasa = HasilJasa::join('spk_jasa', 'hasil_jasa.spk_jasa_id', '=', 'spk_jasa.id')
                ->where('spk_jasa.tukang_jasa_id', $request->tukang_jasa_id)
                ->where(function ($q) {
                    $q->where('hasil_jasa.status_bayar', 'belum_dibayar')
                        ->orWhereNull('hasil_jasa.status_bayar');
                })
                ->whereNotNull('hasil_jasa.total_pendapatan')
                ->where('hasil_jasa.total_pendapatan', '>', 0)
                ->whereBetween('hasil_jasa.tanggal', [
                    Carbon::parse($request->tanggal_awal)->startOfDay(),
                    Carbon::parse($request->tanggal_akhir)->endOfDay()
                ])
                ->select('hasil_jasa.*')
                ->get();

            $totalPendapatan = $hasilJasa->sum('total_pendapatan') ?? 0;

            // Debug logging
            if (config('app.debug')) {
                Log::info("Simulasi Pendapatan Jasa:", [
                    'tukang_jasa_id' => $request->tukang_jasa_id,
                    'tanggal_awal' => $request->tanggal_awal,
                    'tanggal_akhir' => $request->tanggal_akhir,
                    'jumlah_hasil_jasa' => $hasilJasa->count(),
                    'total_pendapatan' => $totalPendapatan,
                    'kurangi_hutang' => $request->kurangi_hutang,
                    'kurangi_cashbon' => $request->kurangi_cashbon,
                ]);
            }

            // Hutang - ambil yang status belum (sama seperti di index())
            $potonganHutang = 0;
            if ($request->kurangi_hutang) {
                $hutang = HutangJasa::where('tukang_jasa_id', $request->tukang_jasa_id)
                    ->where('status_pembayaran', 'belum')
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
                $totalCashbon = CashboanJasa::where('tukang_jasa_id', $request->tukang_jasa_id)
                    ->where('status_pembayaran', 'belum lunas')
                    ->sum('jumlah_cashboan');

                if ($totalCashbon && $totalCashbon > 0) {
                    $potonganCashbon = min((int) $totalCashbon, $totalPendapatan);
                }
            }

            $totalTransfer = $totalPendapatan - $potonganHutang - $potonganCashbon;

            // Debug logging hasil
            if (config('app.debug')) {
                Log::info("Simulasi Result:", [
                    'total_pendapatan' => $totalPendapatan,
                    'potongan_hutang' => $potonganHutang,
                    'potongan_cashbon' => $potonganCashbon,
                    'total_transfer' => $totalTransfer,
                ]);
            }

            return response()->json([
                'total_pendapatan' => (float) $totalPendapatan,
                'potongan_hutang'  => (float) $potonganHutang,
                'potongan_cashbon' => (float) $potonganCashbon,
                'total_transfer'   => (float) $totalTransfer,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in simulasiPendapatanJasa: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'error' => 'Gagal mengambil data simulasi',
                'message' => config('app.debug') ? $e->getMessage() : 'Terjadi kesalahan pada server',
                'total_pendapatan' => 0,
                'potongan_hutang' => 0,
                'potongan_cashbon' => 0,
                'total_transfer' => 0,
            ], 500);
        }
    }


    public function tambahPendapatanJasa(Request $request)
    {
        $request->validate([
            'tukang_jasa_id' => 'required|exists:tukang_jasa,id',
            'tanggal_awal'   => 'required|date',
            'tanggal_akhir'  => 'required|date|after_or_equal:tanggal_awal',
            'kurangi_hutang' => 'required|boolean',
            'kurangi_cashbon' => 'required|boolean',
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
                ->where(function ($q) {
                    $q->where('status_bayar', 'belum_dibayar')
                        ->orWhereNull('status_bayar');
                })
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
                $totalCashbon = CashboanJasa::where('tukang_jasa_id', $request->tukang_jasa_id)
                    ->where('status_pembayaran', 'belum lunas')
                    ->sum('jumlah_cashboan');

                if ($totalCashbon && $totalCashbon > 0) {
                    $potonganCashbon = min((int) $totalCashbon, $totalPendapatan);

                    // Kurangi dari semua cashbon records (mulai dari yang paling lama)
                    $sisaPotongan = $potonganCashbon;
                    $cashbons = CashboanJasa::where('tukang_jasa_id', $request->tukang_jasa_id)
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
                        HistoryCashboanJasa::create([
                            'cashboan_jasa_id' => $cashbon->id,
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
            // 🔹 SIMPAN PENDAPATAN
            // ===============================
            $totalTransfer = $totalPendapatan - $potonganHutang - $potonganCashbon;

            $pendapatan = PendapatanJasa::create([
                'tukang_jasa_id' => $request->tukang_jasa_id,
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

        // Ambil data pengiriman terkait berdasarkan pendapatan_jasa_id
        $pengiriman = HasilJasa::join('spk_jasa', 'hasil_jasa.spk_jasa_id', '=', 'spk_jasa.id')
            ->where('hasil_jasa.pendapatan_jasa_id', $pendapatan->id)
            ->select('hasil_jasa.*')
            ->get();

        return response()->json([
            'pendapatan' => $pendapatan,
            'pengiriman' => $pengiriman,
        ]);
    }

    public function downloadInvoice($id)
    {
        $pendapatan = PendapatanJasa::with('tukangJasa')->findOrFail($id);

        // Ambil hasil jasa yang terkait dengan pendapatan ini
        $hasilJasa = HasilJasa::where('pendapatan_jasa_id', $pendapatan->id)
            ->with(['spkJasa.spkCuttingDistribusi.spkCutting.produk', 'spkJasa.tukangJasa'])
            ->get();

        $tukangJasa = $pendapatan->tukangJasa;

        // Hitung periode dari tanggal hasil jasa yang terkait
        if ($hasilJasa->isNotEmpty()) {
            $periodeAwal = Carbon::parse($hasilJasa->min('tanggal'))->startOfDay();
            $periodeAkhir = Carbon::parse($hasilJasa->max('tanggal'))->endOfDay();
        } else {
            // Fallback ke tanggal pendapatan jika tidak ada hasil jasa
            $periodeAwal = Carbon::parse($pendapatan->tanggal_pendapatan)->startOfDay();
            $periodeAkhir = Carbon::parse($pendapatan->tanggal_pendapatan)->endOfDay();
        }

        $pdf = PDF::loadView('pdf.nota_jasa', compact('pendapatan', 'hasilJasa', 'tukangJasa', 'periodeAwal', 'periodeAkhir'))
            ->setPaper('a4');

        return $pdf->download('Invoice-Pendapatan-Jasa-' . $pendapatan->id . '.pdf');
    }

    /**
     * Download PDF Preview Invoice untuk pendapatan jasa
     */
    public function downloadInvoicePreview(Request $request)
    {
        // Validasi request
        $request->validate([
            'tukang_jasa_id' => 'required|exists:tukang_jasa,id',
            'tanggal_awal' => 'required|date',
            'tanggal_akhir' => 'required|date|after_or_equal:tanggal_awal',
        ]);

        $tukangJasa = TukangJasa::findOrFail($request->tukang_jasa_id);

        $startDate = Carbon::parse($request->tanggal_awal)->startOfDay();
        $endDate = Carbon::parse($request->tanggal_akhir)->endOfDay();

        // Ambil hasil jasa yang belum dibayar
        $hasilJasa = HasilJasa::whereHas('spkJasa', function ($q) use ($request) {
            $q->where('tukang_jasa_id', $request->tukang_jasa_id);
        })
            ->where(function ($q) {
                $q->where('status_bayar', 'belum_dibayar')
                    ->orWhereNull('status_bayar');
            })
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->with(['spkJasa.spkCuttingDistribusi.spkCutting.produk', 'spkJasa.tukangJasa'])
            ->get();

        if ($hasilJasa->isEmpty()) {
            return response()->json(['message' => 'Tidak ada data untuk di-generate invoice'], 404);
        }

        $totalPendapatan = $hasilJasa->sum('total_pendapatan') ?? 0;

        // Hitung potongan hutang
        $hutang = HutangJasa::where('tukang_jasa_id', $request->tukang_jasa_id)
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

        // Hitung potongan cashbon - ambil yang status belum lunas
        $cashbon = CashboanJasa::where('tukang_jasa_id', $request->tukang_jasa_id)
            ->where('status_pembayaran', 'belum lunas')
            ->first();

        $potonganCashbon = 0;
        if ($cashbon && isset($cashbon->jumlah_cashboan) && $cashbon->jumlah_cashboan > 0) {
            $potonganCashbon = min((int) $cashbon->jumlah_cashboan, $totalPendapatan);
        }

        $totalTransfer = $totalPendapatan - $potonganHutang - $potonganCashbon;

        // Buat objek pendapatan dummy untuk preview
        $pendapatanPreview = (object) [
            'id' => 'PREVIEW',
            'total_pendapatan' => $totalPendapatan,
            'total_hutang' => $potonganHutang,
            'total_cashbon' => $potonganCashbon,
            'total_transfer' => $totalTransfer,
            'status_pembayaran' => 'belum_dibayar',
            'tanggal_pendapatan' => now(),
        ];

        $periodeAwal = Carbon::parse($request->tanggal_awal);
        $periodeAkhir = Carbon::parse($request->tanggal_akhir);

        // Format data untuk PDF
        $data = [
            'pendapatanPreview' => $pendapatanPreview,
            'hasilJasa' => $hasilJasa,
            'tukangJasa' => $tukangJasa,
            'periodeAwal' => $periodeAwal,
            'periodeAkhir' => $periodeAkhir,
        ];

        // Generate PDF
        $pdf = Pdf::loadView('pdf.nota_jasa_preview', $data);

        // Set nama file
        $fileName = 'Invoice-Preview-Pendapatan-Jasa-' . $tukangJasa->id . '_' . date('Y-m-d') . '.pdf';

        // Download PDF
        return $pdf->download($fileName);
    }
}
