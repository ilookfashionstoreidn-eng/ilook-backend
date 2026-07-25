<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TukangCutting;
use App\Models\HutangCutting;
use App\Models\CashboanCutting;
use App\Models\HasilCutting;
use App\Models\PendapatanCutting;
use App\Models\HistoryHutangCutting;
use App\Models\HistoryCashboanCutting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

class PendapatanCuttingController extends Controller
{
    public function history(Request $request)
    {
        try {
            $query = PendapatanCutting::with(['tukangCutting:id,nama_tukang_cutting'])
                ->whereIn('status_pembayaran', ['sudah_dibayar', 'sudah dibayar', 'lunas'])
                ->orderBy('created_at', 'desc');

            if ($request->filled('tukang_cutting_id')) {
                $query->where('tukang_cutting_id', $request->tukang_cutting_id);
            }

            if ($request->filled('search')) {
                $search = trim((string) $request->search);
                $query->whereHas('tukangCutting', function ($q) use ($search) {
                    $q->where('nama_tukang_cutting', 'like', "%{$search}%");
                });
            }

            if ($request->filled('start_date') && $request->filled('end_date')) {
                $startDate = Carbon::parse($request->start_date)->startOfDay();
                $endDate = Carbon::parse($request->end_date)->endOfDay();
                $query->whereBetween('created_at', [$startDate, $endDate]);
            }

            return response()->json($query->get(), 200);
        } catch (\Exception $e) {
            Log::error('Error in PendapatanCuttingController@history: ' . $e->getMessage());

            return response()->json([
                'error' => 'Gagal mengambil history pendapatan cutting',
                'message' => config('app.debug') ? $e->getMessage() : 'Terjadi kesalahan pada server'
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

            $data = TukangCutting::all()->map(function ($tukang) use ($startDate, $endDate) {
                try {
                    // Query hasil cutting dengan relasi spkCutting
                    // Gunakan join untuk menghindari masalah relasi
                    // Ambil data yang belum dibayar (tidak ada di hasil_pendapatan_cutting)
                    // Gunakan total_bayar sebagai nilai pendapatan (atau total_hasil_pendapatan jika tersedia)
                    // Gunakan created_at karena HasilCutting tidak punya field tanggal
                    $hasilCutting = HasilCutting::join('spk_cutting', 'hasil_cutting.spk_cutting_id', '=', 'spk_cutting.id')
                        ->where('spk_cutting.tukang_cutting_id', $tukang->id)
                        ->where(function ($q) {
                            // Gunakan total_bayar jika ada, jika tidak gunakan total_hasil_pendapatan
                            $q->where(function ($subQ) {
                                $subQ->whereNotNull('hasil_cutting.total_bayar')
                                    ->where('hasil_cutting.total_bayar', '>', 0);
                            })->orWhere(function ($subQ) {
                                $subQ->whereNotNull('hasil_cutting.total_hasil_pendapatan')
                                    ->where('hasil_cutting.total_hasil_pendapatan', '>', 0);
                            });
                        })
                        ->whereBetween('hasil_cutting.created_at', [$startDate, $endDate])
                        ->whereNotIn('hasil_cutting.id', function ($query) {
                            $query->select('hasil_cutting_id')
                                ->from('hasil_pendapatan_cutting');
                        })
                        ->select('hasil_cutting.*')
                        ->get();

                    $jumlahSpk = $hasilCutting->count();

                    $totalProduk = $hasilCutting->sum('total_produk');

                    $totalPendapatan = $hasilCutting->sum(function ($item) {
                        return $item->total_bayar ?? ($item->total_hasil_pendapatan ?? 0);
                    });

                    // Debug logging (hanya di development)
                    if (config('app.debug') && $tukang->id <= 3) {
                        Log::info("Tukang Cutting ID: {$tukang->id}, Nama: {$tukang->nama_tukang_cutting}");
                        Log::info("Periode: {$startDate->toDateString()} - {$endDate->toDateString()}");
                        Log::info("Jumlah hasil cutting: " . $hasilCutting->count());
                        Log::info("Total pendapatan: " . $totalPendapatan);
                    }

                    // Hutang - gunakan query yang sama seperti HutangCuttingController untuk konsistensi
                    // Ambil semua hutang yang belum lunas dan gunakan MAX untuk potongan_per_minggu
                    $hutangData = DB::table('hutang_cutting')
                        ->where('tukang_cutting_id', $tukang->id)
                        ->where('status_pembayaran', 'belum lunas')
                        ->select(
                            DB::raw('MAX(id) as hutang_id'),
                            DB::raw('COALESCE(SUM(jumlah_hutang), 0) as jumlah_hutang'),
                            DB::raw('MAX(potongan_per_minggu) as potongan_per_minggu'),
                            DB::raw('MAX(is_potongan_persen) as is_potongan_persen'),
                            DB::raw('MAX(persentase_potongan) as persentase_potongan')
                        )
                        ->first();

                    $potonganHutang = 0;
                    if ($hutangData && isset($hutangData->jumlah_hutang) && $hutangData->jumlah_hutang > 0) {
                        if (isset($hutangData->is_potongan_persen) && $hutangData->is_potongan_persen) {
                            $potonganHutang = (($hutangData->persentase_potongan ?? 0) / 100) * $totalPendapatan;
                        } else {
                            $potonganPerMinggu = $hutangData->potongan_per_minggu ?? 0;
                            // Potongan hutang = potongan per minggu (untuk display di index)
                            $potonganHutang = $potonganPerMinggu;
                        }

                        // Debug logging
                        if (config('app.debug')) {
                            Log::info("Potongan Hutang Cutting - Index:", [
                                'tukang_cutting_id' => $tukang->id,
                                'tukang_cutting_nama' => $tukang->nama_tukang_cutting,
                                'hutang_id' => $hutangData->hutang_id ?? null,
                                'jumlah_hutang' => $hutangData->jumlah_hutang ?? 0,
                                'potongan_per_minggu' => $hutangData->potongan_per_minggu ?? 0,
                                'is_potongan_persen' => $hutangData->is_potongan_persen ?? false,
                                'persentase_potongan' => $hutangData->persentase_potongan ?? 0,
                                'total_pendapatan' => $totalPendapatan,
                                'potongan_hutang' => $potonganHutang,
                            ]);
                        }
                    } else {
                        // Debug logging jika tidak ada hutang
                        if (config('app.debug')) {
                            Log::info("Tidak ada hutang untuk tukang cutting:", [
                                'tukang_cutting_id' => $tukang->id,
                                'tukang_cutting_nama' => $tukang->nama_tukang_cutting,
                                'hutang_data' => $hutangData ? 'found' : 'not found',
                                'jumlah_hutang' => $hutangData->jumlah_hutang ?? 0,
                            ]);
                        }
                    }

                    // Cashbon - SUM semua cashbon yang status belum lunas (untuk handle multiple records)
                    $totalCashbon = CashboanCutting::where('tukang_cutting_id', $tukang->id)
                        ->where('status_pembayaran', 'belum lunas')
                        ->sum('jumlah_cashboan');

                    $potonganCashbon = 0;
                    if ($totalCashbon && $totalCashbon > 0) {
                        $potonganCashbon = min((int) $totalCashbon, $totalPendapatan);

                        // Debug logging untuk cashbon
                        if (config('app.debug')) {
                            Log::info("Cashbon found for {$tukang->nama_tukang_cutting} (ID: {$tukang->id}):", [
                                'total_cashbon' => $totalCashbon,
                                'potongan_cashbon' => $potonganCashbon,
                                'total_pendapatan' => $totalPendapatan
                            ]);
                        }
                    }

                    // Hapus pengecekan pendapatan sudah dibayar untuk periode ini
                    // Sekarang pendapatan bisa dibayar kapan saja

                    return [
                        'tukang_cutting_id' => $tukang->id,
                        'nama_tukang_cutting' => $tukang->nama_tukang_cutting ?? '-',
                        'periode' => [
                            'start' => $startDate->toDateString(),
                            'end'   => $endDate->toDateString(),
                        ],
                        'jumlah_pengiriman' => $jumlahSpk,
                        'jumlah_spk' => $jumlahSpk,
                        'total_produk' => $totalProduk,
                        'total_pendapatan' => $totalPendapatan,
                        'potongan_hutang'  => $potonganHutang,
                        'potongan_cashbon' => $potonganCashbon,
                        'total_transfer'   => $totalPendapatan - $potonganHutang - $potonganCashbon,
                        'pendapatan_id' => null, // Selalu null agar bisa dibayar kapan saja
                    ];
                } catch (\Exception $e) {
                    Log::error('Error processing tukang cutting ' . $tukang->id . ': ' . $e->getMessage());
                    Log::error('Stack trace: ' . $e->getTraceAsString());

                    return [
                        'tukang_cutting_id' => $tukang->id,
                        'nama_tukang_cutting' => $tukang->nama_tukang_cutting ?? '-',
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
            Log::error('Error in PendapatanCuttingController@index: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'error' => 'Gagal mengambil data pendapatan',
                'message' => config('app.debug') ? $e->getMessage() : 'Terjadi kesalahan pada server'
            ], 500);
        }
    }

    public function simulasiPendapatanCutting(Request $request)
    {
        try {
            $request->validate([
                'tukang_cutting_id' => 'required|exists:tukang_cutting,id',
                'tanggal_awal'  => 'required|date',
                'tanggal_akhir' => 'required|date|after_or_equal:tanggal_awal',
                'kurangi_hutang' => 'required|boolean',
                'kurangi_cashbon' => 'required|boolean',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error in simulasiPendapatanCutting:', $e->errors());
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
            // Query hasil cutting dengan relasi spkCutting - sama seperti di index()
            $hasilCutting = HasilCutting::join('spk_cutting', 'hasil_cutting.spk_cutting_id', '=', 'spk_cutting.id')
                ->where('spk_cutting.tukang_cutting_id', $request->tukang_cutting_id)
                ->where(function ($q) {
                    // Gunakan total_bayar jika ada, jika tidak gunakan total_hasil_pendapatan
                    $q->where(function ($subQ) {
                        $subQ->whereNotNull('hasil_cutting.total_bayar')
                            ->where('hasil_cutting.total_bayar', '>', 0);
                    })->orWhere(function ($subQ) {
                        $subQ->whereNotNull('hasil_cutting.total_hasil_pendapatan')
                            ->where('hasil_cutting.total_hasil_pendapatan', '>', 0);
                    });
                })
                ->whereBetween('hasil_cutting.created_at', [
                    Carbon::parse($request->tanggal_awal)->startOfDay(),
                    Carbon::parse($request->tanggal_akhir)->endOfDay()
                ])
                ->whereNotIn('hasil_cutting.id', function ($query) {
                    $query->select('hasil_cutting_id')
                        ->from('hasil_pendapatan_cutting');
                })
                ->select('hasil_cutting.*')
                ->get();

            // Hitung total pendapatan: gunakan total_bayar jika ada, jika tidak gunakan total_hasil_pendapatan
            $totalPendapatan = $hasilCutting->sum(function ($item) {
                return $item->total_bayar ?? ($item->total_hasil_pendapatan ?? 0);
            });

            // Debug logging
            if (config('app.debug')) {
                Log::info("Simulasi Pendapatan Cutting:", [
                    'tukang_cutting_id' => $request->tukang_cutting_id,
                    'tanggal_awal' => $request->tanggal_awal,
                    'tanggal_akhir' => $request->tanggal_akhir,
                    'jumlah_hasil_cutting' => $hasilCutting->count(),
                    'total_pendapatan' => $totalPendapatan,
                    'kurangi_hutang' => $request->kurangi_hutang,
                    'kurangi_cashbon' => $request->kurangi_cashbon,
                ]);
            }

            // Hutang - ambil yang status belum lunas (sama seperti di index())
            $potonganHutang = 0;
            if ($request->kurangi_hutang) {
                $hutang = HutangCutting::where('tukang_cutting_id', $request->tukang_cutting_id)
                    ->where('status_pembayaran', 'belum lunas')
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
                $totalCashbon = CashboanCutting::where('tukang_cutting_id', $request->tukang_cutting_id)
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
            Log::error('Error in simulasiPendapatanCutting: ' . $e->getMessage());
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

    public function tambahPendapatanCutting(Request $request)
    {
        $request->validate([
            'tukang_cutting_id' => 'required|exists:tukang_cutting,id',
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
                    ->store('bukti_transfer_pendapatan_cutting', 'public');
            }

            // 🔹 Ambil hasil cutting BELUM dibayar (tidak ada di hasil_pendapatan_cutting)
            $hasilCutting = HasilCutting::whereHas('spkCutting', function ($q) use ($request) {
                $q->where('tukang_cutting_id', $request->tukang_cutting_id);
            })
                ->where(function ($q) {
                    // Gunakan total_bayar jika ada, jika tidak gunakan total_hasil_pendapatan
                    $q->where(function ($subQ) {
                        $subQ->whereNotNull('total_bayar')
                            ->where('total_bayar', '>', 0);
                    })->orWhere(function ($subQ) {
                        $subQ->whereNotNull('total_hasil_pendapatan')
                            ->where('total_hasil_pendapatan', '>', 0);
                    });
                })
                ->whereBetween('created_at', [
                    Carbon::parse($request->tanggal_awal)->startOfDay(),
                    Carbon::parse($request->tanggal_akhir)->endOfDay()
                ])
                ->whereNotIn('id', function ($query) {
                    $query->select('hasil_cutting_id')
                        ->from('hasil_pendapatan_cutting');
                })
                ->lockForUpdate()
                ->get();

            if ($hasilCutting->isEmpty()) {
                return response()->json([
                    'message' => 'Tidak ada hasil cutting yang bisa dibayarkan'
                ], 422);
            }

            // Hitung total pendapatan: gunakan total_bayar jika ada, jika tidak gunakan total_hasil_pendapatan
            $totalPendapatan = $hasilCutting->sum(function ($item) {
                return $item->total_bayar ?? ($item->total_hasil_pendapatan ?? 0);
            });

            // ===============================
            // 🔹 POTONGAN HUTANG
            // ===============================
            $potonganHutang = 0;

            if ($request->kurangi_hutang) {
                // lockForUpdate supaya dua request bayar bersamaan untuk tukang cutting
                // yang sama tidak sama-sama membaca jumlah_hutang lama lalu saling
                // menimpa hasil pengurangan satu sama lain (lost update).
                $hutang = HutangCutting::where('tukang_cutting_id', $request->tukang_cutting_id)
                    ->where('status_pembayaran', 'belum lunas')
                    ->orderBy('tanggal_hutang', 'desc')
                    ->lockForUpdate()
                    ->first();

                if ($hutang && $hutang->jumlah_hutang > 0) {
                    // Logika potongan: jika is_potongan_persen = true, gunakan persentase
                    // Jika false, gunakan potongan_per_minggu
                    if (isset($hutang->is_potongan_persen) && $hutang->is_potongan_persen) {
                        $potongan = (($hutang->persentase_potongan ?? 0) / 100) * $totalPendapatan;
                    } else {
                        $potongan = $hutang->potongan_per_minggu ?? 0;
                    }

                    $potonganHutang = min($hutang->jumlah_hutang, $potongan);

                    // Debug logging
                    if (config('app.debug')) {
                        Log::info("Potongan Hutang Cutting:", [
                            'tukang_cutting_id' => $request->tukang_cutting_id,
                            'is_potongan_persen' => $hutang->is_potongan_persen ?? false,
                            'potongan_per_minggu' => $hutang->potongan_per_minggu ?? 0,
                            'persentase_potongan' => $hutang->persentase_potongan ?? 0,
                            'jumlah_hutang' => $hutang->jumlah_hutang,
                            'total_pendapatan' => $totalPendapatan,
                            'potongan' => $potongan,
                            'potongan_hutang' => $potonganHutang,
                        ]);
                    }

                    $hutang->jumlah_hutang -= $potonganHutang;

                    // Update status jika sudah lunas
                    if ($hutang->jumlah_hutang <= 0) {
                        $hutang->status_pembayaran = 'lunas';
                    }

                    $hutang->save();

                    HistoryHutangCutting::create([
                        'hutang_cutting_id' => $hutang->id,
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
                // Kunci baris cashbon yang belum lunas sebelum dihitung & dikurangi,
                // supaya dua request bayar bersamaan tidak saling menimpa pengurangan.
                $cashbons = CashboanCutting::where('tukang_cutting_id', $request->tukang_cutting_id)
                    ->where('status_pembayaran', 'belum lunas')
                    ->orderBy('tanggal_cashboan', 'asc')
                    ->lockForUpdate()
                    ->get();
                $totalCashbon = $cashbons->sum('jumlah_cashboan');

                if ($totalCashbon && $totalCashbon > 0) {
                    $potonganCashbon = min((int) $totalCashbon, $totalPendapatan);

                    // Kurangi dari semua cashbon records (mulai dari yang paling lama)
                    $sisaPotongan = $potonganCashbon;

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
                        HistoryCashboanCutting::create([
                            'cashboan_cutting_id' => $cashbon->id,
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

            $pendapatan = PendapatanCutting::create([
                'tukang_cutting_id' => $request->tukang_cutting_id,
                'total_pendapatan' => $totalPendapatan,
                'total_transfer' => $totalTransfer,
                'total_hutang' => $potonganHutang,
                'total_cashbon' => $potonganCashbon,
                'status_pembayaran' => 'sudah_dibayar',
                'bukti_transfer' => $path,
            ]);

            // 🔹 UPDATE HASIL CUTTING → SUDAH DIBAYAR (link via pivot table)
            foreach ($hasilCutting as $hasil) {
                DB::table('hasil_pendapatan_cutting')->insert([
                    'hasil_cutting_id' => $hasil->id,
                    'pendapatan_cutting_id' => $pendapatan->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Pendapatan cutting berhasil dibayarkan',
                'data' => $pendapatan
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error in tambahPendapatanCutting: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    public function showPengiriman($id)
    {
        $pendapatan = PendapatanCutting::find($id);

        if (!$pendapatan) {
            return response()->json(['message' => 'Pendapatan tidak ditemukan.'], 404);
        }

        // Ambil data pengiriman terkait berdasarkan pendapatan_cutting_id via pivot table
        $hasilCuttingIds = DB::table('hasil_pendapatan_cutting')
            ->where('pendapatan_cutting_id', $pendapatan->id)
            ->pluck('hasil_cutting_id');

        $pengiriman = HasilCutting::whereIn('id', $hasilCuttingIds)
            ->with(['spkCutting'])
            ->get();

        return response()->json([
            'pendapatan' => $pendapatan,
            'pengiriman' => $pengiriman,
        ]);
    }

    public function downloadInvoice($id)
    {
        $pendapatan = PendapatanCutting::with('tukangCutting')->findOrFail($id);

        // Ambil hasil cutting yang terkait dengan pendapatan ini via pivot table
        $hasilCuttingIds = DB::table('hasil_pendapatan_cutting')
            ->where('pendapatan_cutting_id', $pendapatan->id)
            ->pluck('hasil_cutting_id');

        $hasilCutting = HasilCutting::whereIn('id', $hasilCuttingIds)
            ->with(['spkCutting.produk'])
            ->get();

        $tukangCutting = $pendapatan->tukangCutting;

        // Hitung periode dari tanggal hasil cutting yang terkait
        if ($hasilCutting->isNotEmpty()) {
            $periodeAwal = Carbon::parse($hasilCutting->min('created_at'))->startOfDay();
            $periodeAkhir = Carbon::parse($hasilCutting->max('created_at'))->endOfDay();
        } else {
            // Fallback ke tanggal pendapatan jika tidak ada hasil cutting
            $periodeAwal = Carbon::parse($pendapatan->created_at)->startOfDay();
            $periodeAkhir = Carbon::parse($pendapatan->created_at)->endOfDay();
        }

        $pdf = PDF::loadView('pdf.nota_cutting', compact('pendapatan', 'hasilCutting', 'tukangCutting', 'periodeAwal', 'periodeAkhir'))
            ->setPaper('a4');

        return $pdf->download('Invoice-Pendapatan-Cutting-' . $pendapatan->id . '.pdf');
    }

    /**
     * Download PDF Preview Invoice untuk pendapatan cutting
     */
    public function downloadInvoicePreview(Request $request)
    {
        // Validasi request
        $request->validate([
            'tukang_cutting_id' => 'required|exists:tukang_cutting,id',
            'tanggal_awal' => 'required|date',
            'tanggal_akhir' => 'required|date|after_or_equal:tanggal_awal',
        ]);

        $tukangCutting = TukangCutting::findOrFail($request->tukang_cutting_id);

        $startDate = Carbon::parse($request->tanggal_awal)->startOfDay();
        $endDate = Carbon::parse($request->tanggal_akhir)->endOfDay();

        // Ambil hasil cutting yang belum dibayar
        $hasilCutting = HasilCutting::whereHas('spkCutting', function ($q) use ($request) {
            $q->where('tukang_cutting_id', $request->tukang_cutting_id);
        })
            ->where(function ($q) {
                $q->where(function ($subQ) {
                    $subQ->whereNotNull('total_bayar')
                        ->where('total_bayar', '>', 0);
                })->orWhere(function ($subQ) {
                    $subQ->whereNotNull('total_hasil_pendapatan')
                        ->where('total_hasil_pendapatan', '>', 0);
                });
            })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotIn('id', function ($query) {
                $query->select('hasil_cutting_id')
                    ->from('hasil_pendapatan_cutting');
            })
            ->with(['spkCutting.produk'])
            ->get();

        if ($hasilCutting->isEmpty()) {
            return response()->json(['message' => 'Tidak ada data untuk di-generate invoice'], 404);
        }

        // Hitung total pendapatan: gunakan total_bayar jika ada, jika tidak gunakan total_hasil_pendapatan
        $totalPendapatan = $hasilCutting->sum(function ($item) {
            return $item->total_bayar ?? ($item->total_hasil_pendapatan ?? 0);
        });

        // Hitung potongan hutang
        $hutang = HutangCutting::where('tukang_cutting_id', $request->tukang_cutting_id)
            ->where('status_pembayaran', 'belum lunas')
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
        $totalCashbon = CashboanCutting::where('tukang_cutting_id', $request->tukang_cutting_id)
            ->where('status_pembayaran', 'belum lunas')
            ->sum('jumlah_cashboan');

        $potonganCashbon = 0;
        if ($totalCashbon && $totalCashbon > 0) {
            $potonganCashbon = min((int) $totalCashbon, $totalPendapatan);
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
            'created_at' => now(),
        ];

        $periodeAwal = Carbon::parse($request->tanggal_awal);
        $periodeAkhir = Carbon::parse($request->tanggal_akhir);

        // Format data untuk PDF
        $data = [
            'pendapatanPreview' => $pendapatanPreview,
            'hasilCutting' => $hasilCutting,
            'tukangCutting' => $tukangCutting,
            'periodeAwal' => $periodeAwal,
            'periodeAkhir' => $periodeAkhir,
        ];

        // Generate PDF
        $pdf = Pdf::loadView('pdf.nota_cutting_preview', $data);

        // Set nama file
        $fileName = 'Invoice-Preview-Pendapatan-Cutting-' . $tukangCutting->id . '_' . date('Y-m-d') . '.pdf';

        // Download PDF
        return $pdf->download($fileName);
    }

    // Method untuk backward compatibility dengan route yang menggunakan getPendapatanMingguIni
    public function getPendapatanMingguIni(Request $request)
    {
        // Redirect ke index dengan parameter tanggal minggu ini
        $startDate = $request->start_date ?? now()->startOfWeek()->toDateString();
        $endDate = $request->end_date ?? now()->endOfWeek()->toDateString();

        $request->merge([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return $this->index($request);
    }
}
