<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SpkCmt;
use App\Models\Penjahit;
use App\Models\Warna;
use App\Models\LogDeadline;
use App\Models\LogStatus;
use App\Models\Pengiriman;
use App\Models\PengirimanWarna;
use App\Models\Produk;
use App\Models\SpkCuttingDistribusi;
use App\Models\SpkJasa;
use App\Models\SpkCmtItem;
use App\Models\SpkCmtWarna;
use App\Models\LogStatusSpkCmt;
use App\Models\ProdukSku;
use App\Models\Sku;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;


class SpkCmtController extends Controller

{
    private function parseExcelDate($val)
    {
        if ($val === null || $val === '') {
            return null;
        }
        
        $val = trim((string)$val);
        if ($val === '') {
            return null;
        }

        // Jika berupa angka serial Excel (misal 45778 atau 45778.123)
        if (is_numeric($val) && (float)$val > 10000 && (float)$val < 100000) {
            try {
                $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$val);
                return Carbon::instance($dt);
            } catch (\Throwable $e) {
                // fallback
            }
        }

        // Coba deteksi format DD/MM/YYYY atau DD-MM-YYYY yang sering dipakai di Indonesia
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $val, $matches)) {
            try {
                return Carbon::createFromDate((int)$matches[3], (int)$matches[2], (int)$matches[1]);
            } catch (\Throwable $e) {
                // fallback
            }
        }

        try {
            return Carbon::parse($val);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function index(Request $request)
    {
        $user = auth()->user();

        $status = $request->query('status');
        $idPenjahit = $request->query('id_penjahit');
        $sourceType = $request->query('source_type'); // cutting | jasa
        $sortBy = $request->query('sortBy', 'created_at');
        $sortOrder = $request->query('sortOrder', 'desc');
        $allData = $request->query('allData') === 'true';
        $idProduk = $request->query('id_produk');
        $kategoriProduk = $request->query('kategori_produk');
        $sisaHari = $request->query('sisa_hari');
        $deadlineStatus = $request->query('deadline_status'); // 'masih_deadline' | 'over_deadline'
        $kirimMingguIni = $request->query('kirim_minggu_ini'); // 'true' | null

        $sortColumn = $sortBy === 'sisa_hari' ? 'deadline' : $sortBy;

        $query = SpkCmt::with([
            'sku',
            'warna',
            'pengiriman.warna',
            'penjahit',
            'spkCuttingDistribusi.detail',
            'spkCuttingDistribusi.spkCutting.produk',
            'spkCuttingDistribusi.spkCutting.productList.productListImage',
            'spkJasa.spkCuttingDistribusi.detail',
            'spkJasa.spkCuttingDistribusi.spkCutting.produk',
            'spkJasa.spkCuttingDistribusi.spkCutting.productList.productListImage',
            'spkCuttingDistribusi',
            'items.sku.productList.productListImage',
        ]);

        // 🔐 role penjahit
        if ($user->hasRole('penjahit')) {
            $query->where('id_penjahit', $user->id_penjahit);
        }

        // 🔎 filter
        $query->when($status, fn($q) => $q->where('status', $status))
            ->when($idPenjahit, fn($q) => $q->where('id_penjahit', $idPenjahit))
            ->when($sourceType, fn($q) => $q->where('source_type', $sourceType))
            ->when($idProduk, function ($q) use ($idProduk) {
                // Filter berdasarkan produk melalui relasi sumber_pekerjaan
                $q->where(function ($subQ) use ($idProduk) {
                    // Untuk source_type = cutting
                    $subQ->where(function ($cuttingQ) use ($idProduk) {
                        $cuttingQ->where('source_type', 'cutting')
                            ->whereHas('spkCuttingDistribusi.detail', function ($detailQ) use ($idProduk) {
                                $detailQ->where('id_produk', $idProduk);
                            });
                    })
                        // Untuk source_type = jasa
                        ->orWhere(function ($jasaQ) use ($idProduk) {
                            $jasaQ->where('source_type', 'jasa')
                                ->whereHas('spkJasa.spkCuttingDistribusi.detail', function ($detailQ) use ($idProduk) {
                                    $detailQ->where('id_produk', $idProduk);
                                });
                        });
                });
            })
            ->when($kategoriProduk, function ($q) use ($kategoriProduk) {
                // Filter berdasarkan kategori produk
                $q->where(function ($subQ) use ($kategoriProduk) {
                    // Untuk source_type = cutting
                    $subQ->where(function ($cuttingQ) use ($kategoriProduk) {
                        $cuttingQ->where('source_type', 'cutting')
                            ->whereHas('spkCuttingDistribusi.detail.produk', function ($produkQ) use ($kategoriProduk) {
                                $produkQ->where('kategori_produk', $kategoriProduk);
                            });
                    })
                        // Untuk source_type = jasa
                        ->orWhere(function ($jasaQ) use ($kategoriProduk) {
                            $jasaQ->where('source_type', 'jasa')
                                ->whereHas('spkJasa.spkCuttingDistribusi.detail.produk', function ($produkQ) use ($kategoriProduk) {
                                    $produkQ->where('kategori_produk', $kategoriProduk);
                                });
                        });
                });
            })
            ->when($sisaHari !== null, function ($q) use ($sisaHari) {
                // Filter berdasarkan sisa hari (range)
                if ($sisaHari === '0-3') {
                    $q->whereRaw('DATEDIFF(deadline, CURDATE()) BETWEEN 0 AND 3');
                } elseif ($sisaHari === '4-7') {
                    $q->whereRaw('DATEDIFF(deadline, CURDATE()) BETWEEN 4 AND 7');
                } elseif ($sisaHari === '8-14') {
                    $q->whereRaw('DATEDIFF(deadline, CURDATE()) BETWEEN 8 AND 14');
                } elseif ($sisaHari === '15+') {
                    $q->whereRaw('DATEDIFF(deadline, CURDATE()) >= 15');
                }
            })
            ->when($deadlineStatus === 'masih_deadline', function ($q) {
                // Filter SPK yang masih dalam deadline (deadline >= sekarang atau deadline null)
                // Hanya untuk status 'sudah_diambil' karena ini bagian dari "Sedang Dikerjakan"
                $q->where('status', 'sudah_diambil')
                    ->where(function ($subQ) {
                        $subQ->whereNull('deadline')
                            ->orWhere('deadline', '>=', Carbon::now()->startOfDay());
                    });
            })
            ->when($deadlineStatus === 'over_deadline', function ($q) {
                // Filter SPK yang over deadline (deadline < sekarang)
                // Hanya untuk status 'sudah_diambil' karena ini bagian dari "Sedang Dikerjakan"
                $q->where('status', 'sudah_diambil')
                    ->whereNotNull('deadline')
                    ->where('deadline', '<', Carbon::now()->startOfDay());
            })
            ->when($kirimMingguIni === 'true', function ($q) {
                // Filter SPK yang sudah kirim dalam minggu ini
                // Hanya untuk status 'sudah_diambil' karena ini bagian dari "Sedang Dikerjakan"
                $mingguIniStart = Carbon::now()->startOfWeek();
                $mingguIniEnd = Carbon::now()->endOfWeek();
                $q->where('status', 'sudah_diambil')
                    ->whereHas('pengiriman', function ($pengirimanQ) use ($mingguIniStart, $mingguIniEnd) {
                        $pengirimanQ->whereBetween('tanggal_pengiriman', [$mingguIniStart, $mingguIniEnd]);
                    });
            })
            ->orderBy($sortColumn, $sortOrder);

        // --- SUMMARY CALCULATION START ---
        $summaryBaseQuery = SpkCmt::query();

        // Terapkan filter yang sama untuk summary (kecuali status)
        if ($user->hasRole('penjahit')) {
            $summaryBaseQuery->where('id_penjahit', $user->id_penjahit);
        }
        $summaryBaseQuery->when($idPenjahit, fn($q) => $q->where('id_penjahit', $idPenjahit))
            ->when($sourceType, fn($q) => $q->where('source_type', $sourceType))
            ->when($idProduk, function ($q) use ($idProduk) {
                 $q->where(function ($subQ) use ($idProduk) {
                    $subQ->where(function ($cuttingQ) use ($idProduk) {
                        $cuttingQ->where('source_type', 'cutting')
                            ->whereHas('spkCuttingDistribusi.detail', function ($detailQ) use ($idProduk) {
                                $detailQ->where('id_produk', $idProduk);
                            });
                    })
                        ->orWhere(function ($jasaQ) use ($idProduk) {
                            $jasaQ->where('source_type', 'jasa')
                                ->whereHas('spkJasa.spkCuttingDistribusi.detail', function ($detailQ) use ($idProduk) {
                                    $detailQ->where('id_produk', $idProduk);
                                });
                        });
                });
            })
             ->when($kategoriProduk, function ($q) use ($kategoriProduk) {
                $q->where(function ($subQ) use ($kategoriProduk) {
                    $subQ->where(function ($cuttingQ) use ($kategoriProduk) {
                        $cuttingQ->where('source_type', 'cutting')
                            ->whereHas('spkCuttingDistribusi.detail.produk', function ($produkQ) use ($kategoriProduk) {
                                $produkQ->where('kategori_produk', $kategoriProduk);
                            });
                    })
                        ->orWhere(function ($jasaQ) use ($kategoriProduk) {
                            $jasaQ->where('source_type', 'jasa')
                                ->whereHas('spkJasa.spkCuttingDistribusi.detail.produk', function ($produkQ) use ($kategoriProduk) {
                                    $produkQ->where('kategori_produk', $kategoriProduk);
                                });
                        });
                });
            });
            
        // Filter Tanggal untuk Summary (jika ada di request)
        if ($request->filled('start_date')) {
            $summaryBaseQuery->whereDate('spk_cmt.created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $summaryBaseQuery->whereDate('spk_cmt.created_at', '<=', $request->end_date);
        }

        // Hitung ID untuk berbagai kategori
        $allIds = (clone $summaryBaseQuery)->pluck('id_spk');
        $lewatIds = (clone $summaryBaseQuery)->whereRaw('DATEDIFF(deadline, CURDATE()) < 0')->pluck('id_spk');
        $mendekatiIds = (clone $summaryBaseQuery)->whereRaw('DATEDIFF(deadline, CURDATE()) BETWEEN 0 AND 7')->pluck('id_spk');
        $jauhIds = (clone $summaryBaseQuery)->whereRaw('DATEDIFF(deadline, CURDATE()) > 7')->pluck('id_spk');

        $totalSpk = $allIds->count();
        $spkMendekati = $mendekatiIds->count();
        $spkLewat = $lewatIds->count();

        // 2. Total Jml Produk, 3. Total Jml Kirim, 4. Total Jml Sisa
        $totalProduk = \DB::table('spk_cmt_warna')->whereIn('spk_cmt_id', $allIds)->sum('qty');
        $totalKirim = \DB::table('pengiriman')->whereIn('id_spk', $allIds)->sum('total_barang_dikirim');
        $totalSisa = $totalProduk - $totalKirim;

        // Total Jml Sisa Jauh Dari Deadline
        $jauhProduk = \DB::table('spk_cmt_warna')->whereIn('spk_cmt_id', $jauhIds)->sum('qty');
        $jauhKirim = \DB::table('pengiriman')->whereIn('id_spk', $jauhIds)->sum('total_barang_dikirim');
        $sisaJauh = $jauhProduk - $jauhKirim;

        // Total Jml Sisa Mendekati Dari Deadline
        $mendekatiProduk = \DB::table('spk_cmt_warna')->whereIn('spk_cmt_id', $mendekatiIds)->sum('qty');
        $mendekatiKirim = \DB::table('pengiriman')->whereIn('id_spk', $mendekatiIds)->sum('total_barang_dikirim');
        $sisaMendekati = $mendekatiProduk - $mendekatiKirim;

        // Total Jml Sisa Lewat Deadline
        $lewatProduk = \DB::table('spk_cmt_warna')->whereIn('spk_cmt_id', $lewatIds)->sum('qty');
        $lewatKirim = \DB::table('pengiriman')->whereIn('id_spk', $lewatIds)->sum('total_barang_dikirim');
        $sisaLewat = $lewatProduk - $lewatKirim;

        // Hitung untuk periode mingguan & harian (Progress)
        $progressStatusFilter = $request->get('progress_status', 'sudah_diambil');
        if ($progressStatusFilter === 'all' || $progressStatusFilter === '') {
            $progressStatusFilter = 'sudah_diambil';
        }
        // Map kemungkinan nilai bahasa Inggris dari frontend ke nilai yang digunakan di DB
        if ($progressStatusFilter === 'In Progress') {
            $progressStatusFilter = 'sudah_diambil';
        } elseif ($progressStatusFilter === 'Pending') {
            $progressStatusFilter = 'belum_diambil';
        } elseif ($progressStatusFilter === 'Completed') {
            $progressStatusFilter = 'completed';
        }

        $weeklyStart = $request->get('weekly_start');
        $weeklyEnd = $request->get('weekly_end');
        $dailyDate = $request->get('daily_date');

        $inProgressWeekly = [
            'count' => 0,
            'total_qty' => 0,
            'status' => $progressStatusFilter,
        ];
        $inProgressDaily = [
            'count' => 0,
            'total_qty' => 0,
            'status' => $progressStatusFilter,
        ];

        // Weekly Progress
        if ($weeklyStart && $weeklyEnd) {
            $weeklyQuery = (clone $summaryBaseQuery)
                ->where('status', $progressStatusFilter)
                ->whereDate('spk_cmt.created_at', '>=', $weeklyStart)
                ->whereDate('spk_cmt.created_at', '<=', $weeklyEnd);
            
            $inProgressWeekly['count'] = $weeklyQuery->count();
             $inProgressWeekly['total_qty'] = (int) (clone $weeklyQuery)
                ->join('spk_cmt_warna', 'spk_cmt.id_spk', '=', 'spk_cmt_warna.spk_cmt_id')
                ->sum('spk_cmt_warna.qty');
        }

        // Daily Progress
        if ($dailyDate) {
            $dailyQuery = (clone $summaryBaseQuery)
                ->where('status', $progressStatusFilter)
                ->whereDate('spk_cmt.created_at', $dailyDate);
            
            $inProgressDaily['count'] = $dailyQuery->count();
             $inProgressDaily['total_qty'] = (int) (clone $dailyQuery)
                ->join('spk_cmt_warna', 'spk_cmt.id_spk', '=', 'spk_cmt_warna.spk_cmt_id')
                ->sum('spk_cmt_warna.qty');
        }
        
        $summary = [
            'total_spk' => $totalSpk,
            'total_produk' => (int) $totalProduk,
            'total_kirim' => (int) $totalKirim,
            'total_sisa' => (int) $totalSisa,
            'spk_mendekati' => $spkMendekati,
            'sisa_jauh' => (int) $sisaJauh,
            'sisa_mendekati' => (int) $sisaMendekati,
            'sisa_lewat' => (int) $sisaLewat,
            
            // Masih dipertahankan untuk kebutuhan lain jika ada
            'in_progress_weekly' => $inProgressWeekly,
            'in_progress_daily' => $inProgressDaily,
        ];
        // --- SUMMARY CALCULATION END ---

        // Fungsi transform untuk item
        $transformItem = function ($item) {
            // Ambil informasi produk dari sumber_pekerjaan
            $sumberPekerjaan = $item->sumber_pekerjaan;
            $produk = null;
            $nomorSeri = null;

            if ($sumberPekerjaan) {
                if ($item->source_type === 'cutting') {
                    // Dari SpkCuttingDistribusi
                    $produk = $sumberPekerjaan->spkCutting->produk ?? null;
                    $nomorSeri = $sumberPekerjaan->kode_seri; // kode_seri bisa digunakan sebagai nomor_seri
                } else if ($item->source_type === 'jasa') {
                    // Dari SpkJasa -> SpkCuttingDistribusi
                    $distribusi = $sumberPekerjaan->spkCuttingDistribusi;
                    $produk = $distribusi->spkCutting->produk ?? null;
                    $nomorSeri = $distribusi->kode_seri; // kode_seri bisa digunakan sebagai nomor_seri
                }
            } else {
                $nomorSeri = $item->nomor_seri;
            }

            // Hitung jumlah_produk dari warna
            $jumlahProduk = $item->warna->sum('qty');

            $skus = $item->items->map(fn ($i) => [
                'id' => $i->sku->id,
                'sku' => $i->sku->sku,
            ]);


            return [
                'id_spk' => $item->id_spk,
                'skus' => $skus,

                'deadline' => $item->deadline,
                'tanggal_ambil' => $item->tanggal_ambil,
                'status' => $item->status,

                'waktu_pengerjaan' => $item->waktu_pengerjaan,
                'sisa_hari' => $item->sisa_hari,
                'sisa_hari_status' => $item->sisa_hari_status,

                'penjahit' => $item->penjahit,
                'warna' => $item->warna,
                'pengiriman' => $item->pengiriman,

                'total_barang_dikirim' => $item->pengiriman->sum('total_barang_dikirim'),

                // Field untuk frontend
                'nama_produk' => $item->nama_produk,
                'nomor_seri' => $nomorSeri,
                'jumlah_produk' => $jumlahProduk,
                'product_size' => $item->product_size,
                'created_at' => $item->created_at, // Add created_at for frontend chart
                'kategori_produk' => $produk?->kategori_produk,

                // ✅ TAMBAHKAN INI UNTUK DETAIL POPUP
                'gambar_produk' => $item->gambar_produk, // Gambar dari produk
                'created_at' => $item->created_at, // Sebagai "Tanggal SPK"
                'merek' => $item->merek,
                'aksesoris' => $item->aksesoris,
                'catatan' => $item->catatan,
                'keterangan' => $item->keterangan, // Jika ingin ditampilkan juga
                'alasan_pending' => $item->alasan_pending, // Alasan pending
                'pending_until' => $item->pending_until, // Tanggal pending sampai

                // Field harga
                'harga_per_barang' => $item->harga_per_barang,
                'harga_per_jasa' => $item->harga_per_jasa,
                'total_harga' => $item->total_harga,
                'harga_barang_dasar' => $item->harga_barang_dasar,
                'jenis_harga_barang' => $item->jenis_harga_barang,
                'jenis_harga_jasa' => $item->jenis_harga_jasa,

                // 🔥 satu pintu sumber pekerjaan
                'source_type' => $item->source_type, 
                'sumber_pekerjaan' => $item->sumber_pekerjaan,

            ];
        };

        if ($allData) {
            // Jika allData = true, gunakan get() dan map()
            $spk = $query->get()->map($transformItem);
        } else {
            // Jika paginated, gunakan through()
            $perPage = $request->input('per_page', 50);
            $spk = $query->paginate($perPage)->through($transformItem);
        }

        return response()->json([
            'spk' => $spk,
            'summary' => $summary
        ]);
    }

    public function exportExcel(Request $request)
    {
        try {
            $filters = [
                'status' => $request->query('status'),
                'id_penjahit' => $request->query('id_penjahit'),
                'source_type' => $request->query('source_type'),
                'id_produk' => $request->query('id_produk'),
                'kategori_produk' => $request->query('kategori_produk'),
                'sisa_hari' => $request->query('sisa_hari'),
                'deadline_status' => $request->query('deadline_status'),
                'kirim_minggu_ini' => $request->query('kirim_minggu_ini'),
                'start_date' => $request->query('start_date'),
                'end_date' => $request->query('end_date'),
                'sortBy' => $request->query('sortBy', 'created_at'),
                'sortOrder' => $request->query('sortOrder', 'desc'),
            ];

            $fileName = 'spk-cmt-' . now()->format('Y-m-d') . '.xlsx';
            return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\SpkCmtExport($filters), $fileName);
        } catch (\Throwable $e) {
            Log::error('Error exporting SPK CMT to Excel: ' . $e->getMessage());
            return response()->json([
                'message' => 'Gagal export data ke Excel',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function create()
    {
        $penjahits = Penjahit::all();
        return response()->json($penjahits);
    }

    public function downloadTemplate()
    {
        $headers = [
            'Tgl SPK', 'Nama Penjahit', 'Nomor Seri', 'Qty', 'Warna', 'SKU', 'Deadline', 'Tanggal Ambil',
            'Harga Barang', 'Jenis Harga Barang', 'Harga Jasa', 'Jenis Harga Jasa',
            'Merek', 'Keterangan', 'Catatan'
        ];

        $rows = [
            [
                '2026-06-01', 'Sebutkan Nama Penjahit CMT', 'SR-001', '100', 'Merah', 'SKU-MERAH-001', '2026-06-15', '2026-06-02',
                '15000', 'per_pcs', '5000', 'per_barang',
                'Ilook', 'Keterangan SPK...', 'Catatan SPK...'
            ]
        ];

        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\ProductListExport($headers, $rows), 'template_spk_cmt.xlsx');
    }

    public function import(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($validated['file']->getRealPath());
            $rows = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);

            if (count($rows) < 2) {
                return response()->json([
                    'message' => 'File import kosong atau tidak memiliki data.',
                ], 422);
            }

            $headers = array_map(function ($value) {
                return strtolower(trim((string) $value));
            }, $rows[0]);

            $processed = 0;
            $created = 0;
            $skipped = 0;
            $errors = [];

            // Mapping header ke index
            $headerMap = [];
            foreach ($headers as $index => $header) {
                if ($header !== '') {
                    $headerMap[$header] = $index;
                }
            }

            DB::beginTransaction();

            foreach (array_slice($rows, 1) as $index => $row) {
                $rowNumber = $index + 2;

                // Cek baris kosong
                $hasContent = false;
                foreach ($row as $val) {
                    if (trim((string) $val) !== '') {
                        $hasContent = true;
                        break;
                    }
                }
                if (!$hasContent) {
                    continue;
                }

                $processed++;

                // Ambil data dari kolom
                $tglSpkVal = $row[$headerMap['tgl spk'] ?? -1] ?? null;
                $penjahitVal = $row[$headerMap['nama penjahit'] ?? -1] ?? null;
                $nomorSeriVal = $row[$headerMap['nomor seri'] ?? -1] ?? null;
                $deadlineVal = $row[$headerMap['deadline'] ?? -1] ?? null;
                $tanggalAmbilVal = $row[$headerMap['tanggal ambil'] ?? -1] ?? null;
                $hargaBarangVal = $row[$headerMap['harga barang'] ?? -1] ?? 0;
                $jenisHargaBarangVal = $row[$headerMap['jenis harga barang'] ?? -1] ?? 'per_pcs';
                $hargaJasaVal = $row[$headerMap['harga jasa'] ?? -1] ?? 0;
                $jenisHargaJasaVal = $row[$headerMap['jenis harga jasa'] ?? -1] ?? 'per_barang';
                $merekVal = $row[$headerMap['merek'] ?? -1] ?? null;
                $keteranganVal = $row[$headerMap['keterangan'] ?? -1] ?? null;
                $catatanVal = $row[$headerMap['catatan'] ?? -1] ?? null;

                if (!$penjahitVal || !$nomorSeriVal) {
                    $skipped++;
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => 'Nama Penjahit dan Nomor Seri wajib diisi.',
                    ];
                    continue;
                }

                // 1. Cari Penjahit
                $penjahit = \App\Models\Penjahit::where('nama_penjahit', 'like', trim($penjahitVal))->first();
                if (!$penjahit) {
                    $skipped++;
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => "Penjahit dengan nama '" . trim($penjahitVal) . "' tidak ditemukan.",
                    ];
                    continue;
                }

                // 2. Cari Nomor Seri (SpkCuttingDistribusi)
                $distribusi = \App\Models\SpkCuttingDistribusi::with('detail')
                    ->where('kode_seri', trim($nomorSeriVal))
                    ->first();

                $sourceType = 'cutting';
                $sourceId = null;

                if ($distribusi) {
                    $sourceId = $distribusi->id;
                } else {
                    // Cari di SpkJasa
                    $jasa = \App\Models\SpkJasa::whereHas('spkCuttingDistribusi', function ($q) use ($nomorSeriVal) {
                        $q->where('kode_seri', trim($nomorSeriVal));
                    })->first();

                    if ($jasa) {
                        $sourceType = 'jasa';
                        $sourceId = $jasa->id;
                        $distribusi = $jasa->spkCuttingDistribusi;
                    }
                }

                $qtyVal = $row[$headerMap['qty'] ?? -1] ?? ($row[$headerMap['jumlah'] ?? -1] ?? 0);
                $warnaVal = $row[$headerMap['warna'] ?? -1] ?? 'Mix / Custom';
                $skuVal = $row[$headerMap['sku'] ?? -1] ?? null;

                if (!$sourceId || !$distribusi || $distribusi->detail->isEmpty()) {
                    if ((int)$qtyVal > 0) {
                        $sourceType = 'migrasi';
                        $sourceId = 0; // migration fallback
                        $warnaData = collect([
                            [
                                'nama_warna' => $warnaVal,
                                'qty' => (int) $qtyVal,
                            ]
                        ]);
                        $jumlahBarang = (int) $qtyVal;
                    } else {
                        $skipped++;
                        $errors[] = [
                            'row' => $rowNumber,
                            'message' => "Nomor Seri '" . trim($nomorSeriVal) . "' tidak ditemukan dan tidak ada nilai di kolom 'qty' (jumlah) untuk data migrasi.",
                        ];
                        continue;
                    }
                } else {
                    // 3. Snapshot Warna
                    $warnaData = $distribusi->detail->groupBy('warna')->map(fn($details, $warna) => [
                        'nama_warna' => $warna,
                        'qty'        => (int) $details->sum('jumlah_produk'),
                    ])->values();

                    $jumlahBarang = $warnaData->sum('qty');
                }

                // 4. Hitung Harga & Total
                $cleanHargaBarang = (float) str_replace(',', '', (string)$hargaBarangVal);
                $cleanHargaJasa = (float) str_replace(',', '', (string)$hargaJasaVal);

                $hargaPerBarang = trim($jenisHargaBarangVal) === 'per_lusin'
                    ? $cleanHargaBarang / 12
                    : $cleanHargaBarang;

                $hargaPerJasa = trim($jenisHargaJasaVal) === 'per_lusin'
                    ? $cleanHargaJasa / 12
                    : $cleanHargaJasa;

                $totalHarga = ($hargaPerBarang + $hargaPerJasa) * $jumlahBarang;

                $tglSpkParsed = $this->parseExcelDate($tglSpkVal);
                $deadlineParsed = $this->parseExcelDate($deadlineVal);
                $tanggalAmbilParsed = $this->parseExcelDate($tanggalAmbilVal);

                // 5. Buat SPK CMT
                $spk = SpkCmt::create([
                    'source_type' => $sourceType,
                    'source_id'   => $sourceId,
                    'nomor_seri'  => $nomorSeriVal,
                    'deadline'    => $deadlineParsed ? $deadlineParsed->toDateString() : null,
                    'tanggal_ambil' => $tanggalAmbilParsed ? $tanggalAmbilParsed->toDateString() : null,
                    'id_penjahit' => $penjahit->id_penjahit,
                    'status'      => !empty($tanggalAmbilVal) ? 'sudah_diambil' : 'belum_diambil',
                    'keterangan'  => $keteranganVal,
                    'catatan'     => $catatanVal,
                    'merek'       => $merekVal,
                    'harga_barang_dasar' => (float) $hargaBarangVal,
                    'jenis_harga_barang' => $jenisHargaBarangVal ?: 'per_pcs',
                    'harga_per_barang'   => $hargaPerBarang,
                    'harga_per_jasa'     => $hargaPerJasa,
                    'jenis_harga_jasa'   => $jenisHargaJasaVal ?: 'per_barang',
                    'total_harga'        => $totalHarga,
                    'created_at'  => $tglSpkParsed ?: now(),
                ]);

                // Log Status
                \App\Models\LogStatusSpkCmt::create([
                    'spk_cmt_id' => $spk->id_spk,
                    'status'     => 'belum_diambil',
                    'keterangan' => 'SPK CMT diimport via Excel',
                ]);

                // Simpan SKU Items
                if ($distribusi && $distribusi->detail) {
                    $skuIds = collect();
                    foreach ($distribusi->detail as $detail) {
                        $skuName = $detail->productListSku->sku_name ?? $detail->produkSku->sku ?? null;
                        if ($skuName) {
                            $sku = \App\Models\Sku::firstOrCreate([
                                'sku' => $skuName,
                            ], [
                                'is_active' => true,
                            ]);
                            $skuIds->push($sku->id);
                        }
                    }
                    foreach ($skuIds->unique()->values() as $skuId) {
                        \App\Models\SpkCmtItem::create([
                            'spk_cmt_id' => $spk->id_spk,
                            'sku_id'     => $skuId,
                        ]);
                    }
                } else if ($skuVal) {
                    $sku = \App\Models\Sku::firstOrCreate([
                        'sku' => $skuVal,
                    ], [
                        'is_active' => true,
                    ]);
                    \App\Models\SpkCmtItem::create([
                        'spk_cmt_id' => $spk->id_spk,
                        'sku_id'     => $sku->id,
                    ]);
                }

                // Simpan Warna
                foreach ($warnaData as $w) {
                    \App\Models\SpkCmtWarna::create([
                        'spk_cmt_id' => $spk->id_spk,
                        'nama_warna' => $w['nama_warna'],
                        'qty'        => $w['qty'],
                    ]);
                }

                $created++;
            }

            DB::commit();

            return response()->json([
                'processed' => $processed,
                'created' => $created,
                'skipped' => $skipped,
                'total_errors' => count($errors),
                'errors' => $errors,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal mengimport file Excel.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

     public function getAvailableSources(Request $request)
    {
        $request->validate([
            'source_type' => 'required|in:cutting,jasa',
        ]);

        /**
         * Ambil SEMUA kode_seri yang SUDAH dipakai di SPK CMT
         * (resolve dari sumber aslinya)
         */
        $usedKodeSeri = SpkCmt::all()->map(function ($spk) {
            if ($spk->source_type === 'cutting') {
                return SpkCuttingDistribusi::find($spk->source_id)?->kode_seri;
            }

            if ($spk->source_type === 'jasa') {
                return SpkJasa::with('spkCuttingDistribusi')
                    ->find($spk->source_id)
                    ?->spkCuttingDistribusi
                    ?->kode_seri;
            }

            return null;
        })->filter()->unique()->values();

        /* ================= CUTTING ================= */
        if ($request->source_type === 'cutting') {
            $data = SpkCuttingDistribusi::with(['spkCutting.produk', 'detail.produkSku.produk'])
                ->whereNotNull('hasil_cutting_id')
                ->whereNotIn('kode_seri', $usedKodeSeri)
                ->orderBy('kode_seri')
                ->get()
                ->map(function ($item) {
                    $produk = optional($item->spkCutting)->produk
                        ?? optional(optional($item->detail->first())->produkSku)->produk;

                    $namaProduk = $produk?->nama_produk;
                    $subtitleParts = array_filter([
                        $namaProduk,
                        $item->jumlah_produk ? ($item->jumlah_produk . ' pcs') : null,
                    ]);

                    return [
                        'value' => $item->id,
                        'label' => $item->kode_seri,
                        'kode_seri' => $item->kode_seri,
                        'nama_produk' => $namaProduk,
                        'jumlah_produk' => $item->jumlah_produk,
                        'subtitle' => implode(' • ', $subtitleParts),
                        'search_text' => strtolower(implode(' ', array_filter([
                            $item->kode_seri,
                            $namaProduk,
                            $item->jumlah_produk,
                        ]))),
                    ];
                });
        }

        /* ================= JASA ================= */ else {
            $data = SpkJasa::whereHas('spkCuttingDistribusi', function ($q) use ($usedKodeSeri) {
                $q->whereNotNull('hasil_cutting_id')
                  ->whereNotIn('kode_seri', $usedKodeSeri);
            })
                ->with('spkCuttingDistribusi.spkCutting.produk')
                ->orderBy('id')
                ->get()
                ->map(function ($item) {
                    $kodeSeri = optional($item->spkCuttingDistribusi)->kode_seri;
                    $namaProduk = optional(optional($item->spkCuttingDistribusi)->spkCutting)->produk?->nama_produk;
                    $subtitleParts = array_filter([
                        $namaProduk,
                        $item->jumlah ? ($item->jumlah . ' pcs') : null,
                    ]);

                    return [
                        'value' => $item->id,
                        'label' => $kodeSeri,
                        'kode_seri' => $kodeSeri,
                        'nama_produk' => $namaProduk,
                        'jumlah_produk' => $item->jumlah,
                        'subtitle' => implode(' • ', $subtitleParts),
                        'search_text' => strtolower(implode(' ', array_filter([
                            $kodeSeri,
                            $namaProduk,
                            $item->jumlah,
                        ]))),
                    ];
                });
        }

        return response()->json($data);
    }


    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            // ===============================
            // 1️⃣ VALIDASI
            // ===============================
            $validated = $request->validate([
                'source_type' => 'required|in:cutting,jasa',
                'source_id'   => 'required|integer',

                'deadline'    => 'required|date',
                'tanggal_ambil' => 'nullable|date',
                'id_penjahit' => 'required|exists:penjahit_cmt,id_penjahit',

                'keterangan'  => 'nullable|string',
                'catatan'     => 'nullable|string',
                'markeran'    => 'nullable|string',
                'aksesoris'   => 'nullable|string',
                'handtag'     => 'nullable|string',
                'merek'       => 'nullable|string',

                // 🔴 BARU
                'harga_barang_dasar' => 'nullable|numeric|min:0',
                'jenis_harga_barang' => 'nullable|in:per_pcs,per_lusin',

                'harga_per_jasa'     => 'required|numeric|min:0',
                'jenis_harga_jasa'   => 'required|in:per_barang,per_lusin',
            ]);

            // ===============================
            // 2️⃣ RESOLVE DISTRIBUSI
            // ===============================
            if ($validated['source_type'] === 'cutting') {
                $distribusi = SpkCuttingDistribusi::with([
                    'detail.productListSku',
                    'detail.produkSku',
                ])->findOrFail($validated['source_id']);
            } else {
                $jasa = SpkJasa::with([
                    'spkCuttingDistribusi.detail.productListSku',
                    'spkCuttingDistribusi.detail.produkSku',
                ])->findOrFail($validated['source_id']);
                $distribusi = $jasa->spkCuttingDistribusi;
            }

            if (!$distribusi || $distribusi->detail->isEmpty()) {
                throw new \Exception('Distribusi tidak memiliki detail warna');
            }

            // ===============================
            // 3️⃣ SNAPSHOT WARNA
            // ===============================
            $warnaData = $distribusi->detail->groupBy('warna')->map(fn($details, $warna) => [
                'nama_warna' => $warna,
                'qty'        => (int) $details->sum('jumlah_produk'),
            ])->values();

            $jumlahBarang = $warnaData->sum('qty');

            // ===============================
            // 4️⃣ HITUNG HARGA BARANG
            // ===============================
            $hargaPerBarang = ($validated['jenis_harga_barang'] ?? 'per_pcs') === 'per_lusin'
                ? ($validated['harga_barang_dasar'] ?? 0) / 12
                : ($validated['harga_barang_dasar'] ?? 0);

            // ===============================
            // 5️⃣ HITUNG HARGA JASA
            // ===============================
            $hargaPerJasa = $validated['jenis_harga_jasa'] === 'per_lusin'
                ? $validated['harga_per_jasa'] / 12
                : $validated['harga_per_jasa'];

            // ===============================
            // 6️⃣ TOTAL HARGA
            // ===============================
            $totalHarga = ($hargaPerBarang + $hargaPerJasa) * $jumlahBarang;

            // ===============================
            // 7️⃣ SIMPAN SPK CMT
            // ===============================
            $spk = SpkCmt::create([
                'source_type' => $validated['source_type'],
                'source_id'   => $validated['source_id'],

                'deadline'    => $validated['deadline'],
                'tanggal_ambil' => $validated['tanggal_ambil'] ?? null,
                'id_penjahit' => $validated['id_penjahit'],
                'status'      => !empty($validated['tanggal_ambil']) ? 'sudah_diambil' : 'belum_diambil',

                'keterangan'  => $validated['keterangan'] ?? null,
                'catatan'     => $validated['catatan'] ?? null,
                'markeran'    => $validated['markeran'] ?? null,
                'aksesoris'   => $validated['aksesoris'] ?? null,
                'handtag'     => $validated['handtag'] ?? null,
                'merek'       => $validated['merek'] ?? null,

                // 🔴 HARGA BARANG
                'harga_barang_dasar' => $validated['harga_barang_dasar'] ?? 0,
                'jenis_harga_barang' => $validated['jenis_harga_barang'] ?? 'per_pcs',
                'harga_per_barang'   => $hargaPerBarang,

                // 🔴 HARGA JASA
                'harga_per_jasa'     => $hargaPerJasa,
                'jenis_harga_jasa'   => $validated['jenis_harga_jasa'],

                'total_harga'        => $totalHarga,
            ]);

            // ===============================
            // 8️⃣ LOG STATUS
            // ===============================
            LogStatusSpkCmt::create([
                'spk_cmt_id' => $spk->id_spk,
                'status'     => 'belum_diambil',
                'keterangan' => 'SPK CMT dibuat',
            ]);

                
            // ===============================
            // 8️⃣ SIMPAN SKU KE ITEMS (DARI DETAIL DISTRIBUSI)
            // ===============================
            $skuIds = collect();
            
            foreach ($distribusi->detail as $detail) {
                if ($detail->productListSku) {
                    // Cari atau buat SKU di tabel skus berdasarkan $detail->productListSku->sku_name
                    $sku = Sku::where('sku', $detail->productListSku->sku_name)->first();
                    if (!$sku) {
                        $sku = Sku::create([
                            'sku' => $detail->productListSku->sku_name,
                            'is_active' => true,
                        ]);
                    }
                    if ($sku) {
                        $skuIds->push($sku->id);
                    }
                } elseif ($detail->produkSku) {
                    // Cari atau buat SKU di tabel skus berdasarkan $detail->produkSku->sku
                    $sku = Sku::where('sku', $detail->produkSku->sku)->first();
                    if (!$sku) {
                        $sku = Sku::create([
                            'sku' => $detail->produkSku->sku,
                            'is_active' => true,
                        ]);
                    }
                    if ($sku) {
                        $skuIds->push($sku->id);
                    }
                }
            }
            
            $skuIds = $skuIds->unique()->values();

            foreach ($skuIds as $skuId) {
                SpkCmtItem::create([
                    'spk_cmt_id' => $spk->id_spk,
                    'sku_id'     => $skuId,
                ]);
            }

            // ===============================
            // 9️⃣ SIMPAN WARNA
            // ===============================
            foreach ($warnaData as $w) {
                SpkCmtWarna::create([
                    'spk_cmt_id' => $spk->id_spk,
                    'nama_warna' => $w['nama_warna'],
                    'qty'        => $w['qty'],
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'SPK CMT berhasil dibuat',
                'data'    => $spk->load([
                    'warna',
                    'items.sku.productList.productListImage',
                    'penjahit',
                    'spkCuttingDistribusi.spkCutting.produk',
                    'spkCuttingDistribusi.spkCutting.productList.productListImage',
                    'spkJasa.spkCuttingDistribusi.spkCutting.produk',
                    'spkJasa.spkCuttingDistribusi.spkCutting.productList.productListImage',
                ]),
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }




    public function show($id)
    {
        $spk = SpkCmt::with([
            'warna',
            'items.sku.productList.productListImage',
            'penjahit',
            'spkCuttingDistribusi.spkCutting.produk',
            'spkCuttingDistribusi.spkCutting.productList.productListImage',
            'spkJasa.spkCuttingDistribusi.spkCutting.produk',
            'spkJasa.spkCuttingDistribusi.spkCutting.productList.productListImage',
        ])->findOrFail($id);
        return response()->json($spk);
    }

    public function edit($id)
    {
        $spk = SpkCmt::findOrFail($id);
        $penjahits = Penjahit::all();
        return response()->json(['spk' => $spk, 'penjahits' => $penjahits]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'deadline'    => 'required|date',
            'tanggal_ambil' => 'nullable|date',
            'id_penjahit' => 'required|exists:penjahit_cmt,id_penjahit',

            'keterangan'  => 'nullable|string',
            'catatan'     => 'nullable|string',
            'markeran'    => 'nullable|string',
            'aksesoris'   => 'nullable|string',
            'handtag'     => 'nullable|string',
            'merek'       => 'nullable|string',

            'harga_barang_dasar' => 'nullable|numeric|min:0',
            'jenis_harga_barang' => 'nullable|in:per_pcs,per_lusin',

            'harga_per_jasa'     => 'required|numeric|min:0',
            'jenis_harga_jasa'   => 'required|in:per_barang,per_lusin',
        ]);

        $spk = SpkCmt::with('warna')->findOrFail($id);

        $jumlahBarang = $spk->warna->sum('qty');

        $hargaPerBarang = ($validated['jenis_harga_barang'] ?? 'per_pcs') === 'per_lusin'
            ? ($validated['harga_barang_dasar'] ?? 0) / 12
            : ($validated['harga_barang_dasar'] ?? 0);

        $hargaPerJasa = $validated['jenis_harga_jasa'] === 'per_lusin'
            ? $validated['harga_per_jasa'] / 12
            : $validated['harga_per_jasa'];

        $totalHarga = ($hargaPerBarang + $hargaPerJasa) * $jumlahBarang;

        $updateData = [
            'deadline'    => $validated['deadline'],
            'tanggal_ambil' => $validated['tanggal_ambil'] ?? null,
            'id_penjahit' => $validated['id_penjahit'],
            'status'      => !empty($validated['tanggal_ambil']) ? 'sudah_diambil' : ($spk->status === 'belum_diambil' ? 'belum_diambil' : $spk->status),
            'keterangan'  => $validated['keterangan'] ?? null,
            'catatan'     => $validated['catatan'] ?? null,
            'markeran'    => $validated['markeran'] ?? null,
            'aksesoris'   => $validated['aksesoris'] ?? null,
            'handtag'     => $validated['handtag'] ?? null,
            'merek'       => $validated['merek'] ?? null,
            'harga_barang_dasar' => $validated['harga_barang_dasar'] ?? 0,
            'jenis_harga_barang' => $validated['jenis_harga_barang'] ?? 'per_pcs',
            'harga_per_barang'   => $hargaPerBarang,
            'harga_per_jasa'     => $hargaPerJasa,
            'jenis_harga_jasa'   => $validated['jenis_harga_jasa'],
            'total_harga'        => $totalHarga,
        ];

        $spk->update($updateData);

        return response()->json([
            'message' => 'SPK berhasil diperbarui!',
            'data' => $spk->fresh([
                'warna',
                'items.sku.productList.productListImage',
                'items.sku.produk.productListImage',
                'penjahit',
                'spkCuttingDistribusi.spkCutting.produk.productListImage',
                'spkCuttingDistribusi.spkCutting.productList.productListImage',
                'spkJasa.spkCuttingDistribusi.spkCutting.produk.productListImage',
                'spkJasa.spkCuttingDistribusi.spkCutting.productList.productListImage',
            ])
        ], 200);
    }

    public function destroy($id)
    {
        $spk = SpkCmt::findOrFail($id);
        $spk->delete();

        return response()->json(['message' => 'SPK berhasil dihapus!']);
    }

    public function downloadPdf($id)
    {
        $spk = SpkCmt::with([
            'penjahit',
            'warna',
            'pengiriman',
            'spkCuttingDistribusi.spkCutting.produk',
            'spkCuttingDistribusi.spkCutting.productList',
            'spkCuttingDistribusi.detail.productListSku',
            'spkJasa.spkCuttingDistribusi.spkCutting.produk',
            'spkJasa.spkCuttingDistribusi.spkCutting.productList',
            'spkJasa.spkCuttingDistribusi.detail.productListSku',
        ])->find($id);

        if (!$spk) {
            return response()->json(['error' => 'SPK tidak ditemukan'], 404);
        }

        // Ambil produk dari sumber pekerjaan
        $produk = null;
        if ($spk->source_type === 'cutting' && $spk->spkCuttingDistribusi) {
            $produk = $spk->spkCuttingDistribusi->spkCutting->produk ?? null;
        } elseif ($spk->source_type === 'jasa' && $spk->spkJasa?->spkCuttingDistribusi) {
            $produk = $spk->spkJasa->spkCuttingDistribusi->spkCutting->produk ?? null;
        }

        $pdf = Pdf::loadView('pdf.spk_cmt', compact('spk', 'produk'));
        return $pdf->download("spk_cmt_{$id}.pdf");
    }

  public function downloadBarcodePdf($id)
{
    try {
        $spk = SpkCmt::with([
            'items.sku.productList.productListImage', // 🔥 MULTI SKU
            'spkCuttingDistribusi.detail.produkSku.produk',
            'spkCuttingDistribusi.hasilCutting.bahan.produkSku.produk',
            'spkJasa.spkCuttingDistribusi.detail.produkSku.produk',
            'spkJasa.spkCuttingDistribusi.hasilCutting.bahan.produkSku.produk',
        ])->find($id);

        if (!$spk) {
            return response()->json(['error' => 'SPK CMT tidak ditemukan'], 404);
        }

        // ===============================
        // RESOLVE KODE SERI (1x)
        // ===============================
        $kodeSeri = null;
        $distribusi = null;

        if ($spk->source_type === 'cutting' && $spk->spkCuttingDistribusi) {
            $distribusi = $spk->spkCuttingDistribusi;
            $kodeSeri = $distribusi->kode_seri;
        } elseif ($spk->source_type === 'jasa' && $spk->spkJasa?->spkCuttingDistribusi) {
            $distribusi = $spk->spkJasa->spkCuttingDistribusi;
            $kodeSeri = $distribusi->kode_seri;
        }

        if (!$kodeSeri) {
            Log::error('Barcode PDF Error: Kode seri tidak ditemukan', [
                'spk_id' => $id,
                'source_type' => $spk->source_type,
                'has_spkCuttingDistribusi' => $spk->spkCuttingDistribusi ? true : false,
                'has_spkJasa' => $spk->spkJasa ? true : false,
            ]);
            return response()->json(['error' => 'Kode seri tidak ditemukan. Pastikan SPK CMT memiliki distribusi yang valid.'], 422);
        }

        // ===============================
        // DATA QR (PER SKU)
        // ===============================
        $qrItems = collect();

        // Coba ambil dari items terlebih dahulu
        if ($spk->items && $spk->items->isNotEmpty()) {
            $qrItems = $spk->items->filter(function ($item) {
                return $item->sku && $item->sku->sku;
            })->map(function ($item) use ($kodeSeri) {
                $skuText = $item->sku->sku;
                // Cari ProdukSku berdasarkan SKU string
                $produkSku = ProdukSku::where('sku', $skuText)->with('produk')->first();
                
                // Format: "NAMA PRODUK - WARNA UKURAN"
                $skuDisplay = $skuText; // Default ke SKU string jika ProdukSku tidak ditemukan
                if ($produkSku) {
                    $namaProduk = strtoupper($produkSku->produk->nama_produk ?? '');
                    $warna = strtoupper($produkSku->warna ?? '');
                    $ukuran = strtoupper($produkSku->ukuran ?? '');
                    $skuDisplay = trim("{$namaProduk} - {$warna} {$ukuran}");
                }
                
                return [
                    'sku' => $skuText,
                    'sku_display' => $skuDisplay,
                    'kode_seri' => $kodeSeri,
                    'qr_value' => $skuText . ' | ' . $kodeSeri,
                ];
            });
        }

        // Jika items kosong, ambil dari distribusi detail sebagai fallback
        if ($qrItems->isEmpty() && $distribusi) {
            // Reload distribusi dengan detail jika belum ter-load
            if (!$distribusi->relationLoaded('detail')) {
                $distribusi->load('detail.produkSku');
            }

            // Coba ambil dari distribusi detail terlebih dahulu (prioritas)
            if ($distribusi->detail && $distribusi->detail->isNotEmpty()) {
                $produkSkuIds = $distribusi->detail
                    ->whereNotNull('produk_sku_id')
                    ->pluck('produk_sku_id')
                    ->unique()
                    ->values();

                if ($produkSkuIds->isNotEmpty()) {
                    foreach ($produkSkuIds as $produkSkuId) {
                        $produkSku = ProdukSku::with('produk')->find($produkSkuId);
                        if ($produkSku && $produkSku->sku) {
                            // Cari atau buat SKU di tabel skus
                            $sku = Sku::where('sku', $produkSku->sku)->first();
                            if (!$sku) {
                                $sku = Sku::create([
                                    'sku' => $produkSku->sku,
                                    'is_active' => true,
                                ]);
                            }
                            
                            // Format: "NAMA PRODUK - WARNA UKURAN"
                            $namaProduk = strtoupper($produkSku->produk->nama_produk ?? '');
                            $warna = strtoupper($produkSku->warna ?? '');
                            $ukuran = strtoupper($produkSku->ukuran ?? '');
                            $skuDisplay = trim("{$namaProduk} - {$warna} {$ukuran}");
                            
                            if ($sku) {
                                $qrItems->push([
                                    'sku' => $sku->sku,
                                    'sku_display' => $skuDisplay,
                                    'kode_seri' => $kodeSeri,
                                    'qr_value' => $sku->sku . ' | ' . $kodeSeri,
                                ]);
                            }
                        }
                    }
                }
            }

            // Jika masih kosong, coba ambil dari hasil cutting bahan sebagai fallback kedua
            if ($qrItems->isEmpty()) {
                // Reload distribusi dengan hasil cutting jika belum ter-load
                if (!$distribusi->relationLoaded('hasilCutting')) {
                    $distribusi->load('hasilCutting.bahan.produkSku');
                }

                if ($distribusi->hasilCutting && $distribusi->hasilCutting->bahan) {
                    $produkSkuIds = $distribusi->hasilCutting->bahan
                        ->whereNotNull('produk_sku_id')
                        ->pluck('produk_sku_id')
                        ->unique()
                        ->values();

                    if ($produkSkuIds->isEmpty()) {
                        Log::error('Barcode PDF Error: Tidak ada produk_sku_id di distribusi detail maupun hasil cutting', [
                            'spk_id' => $id,
                            'hasil_cutting_id' => $distribusi->hasilCutting->id ?? null,
                            'bahan_count' => $distribusi->hasilCutting->bahan->count() ?? 0,
                            'detail_count' => $distribusi->detail ? $distribusi->detail->count() : 0,
                        ]);
                    }

                    foreach ($produkSkuIds as $produkSkuId) {
                        $produkSku = ProdukSku::with('produk')->find($produkSkuId);
                        if ($produkSku && $produkSku->sku) {
                            // Cari atau buat SKU di tabel skus
                            $sku = Sku::where('sku', $produkSku->sku)->first();
                            if (!$sku) {
                                $sku = Sku::create([
                                    'sku' => $produkSku->sku,
                                    'is_active' => true,
                                ]);
                            }
                            
                            // Format: "NAMA PRODUK - WARNA UKURAN"
                            $namaProduk = strtoupper($produkSku->produk->nama_produk ?? '');
                            $warna = strtoupper($produkSku->warna ?? '');
                            $ukuran = strtoupper($produkSku->ukuran ?? '');
                            $skuDisplay = trim("{$namaProduk} - {$warna} {$ukuran}");
                            
                            if ($sku) {
                                $qrItems->push([
                                    'sku' => $sku->sku,
                                    'sku_display' => $skuDisplay,
                                    'kode_seri' => $kodeSeri,
                                    'qr_value' => $sku->sku . ' | ' . $kodeSeri,
                                ]);
                            }
                        }
                    }
                } else {
                    Log::error('Barcode PDF Error: Hasil cutting atau bahan tidak ditemukan', [
                        'spk_id' => $id,
                        'has_distribusi' => $distribusi ? true : false,
                        'has_detail' => $distribusi && $distribusi->detail ? true : false,
                        'has_hasilCutting' => $distribusi && $distribusi->hasilCutting ? true : false,
                        'has_bahan' => $distribusi && $distribusi->hasilCutting && $distribusi->hasilCutting->bahan ? true : false,
                    ]);
                }
            }
        }

        if ($qrItems->isEmpty()) {
            Log::error('Barcode PDF Error: SKU tidak ditemukan', [
                'spk_id' => $id,
                'items_count' => $spk->items ? $spk->items->count() : 0,
                'has_distribusi' => $distribusi ? true : false,
                'has_hasilCutting' => $distribusi && $distribusi->hasilCutting ? true : false,
            ]);
            return response()->json([
                'error' => 'SKU tidak ditemukan. Pastikan hasil cutting memiliki SKU yang dipilih di distribusi seri.',
                'debug' => [
                    'items_count' => $spk->items ? $spk->items->count() : 0,
                    'has_distribusi' => $distribusi ? true : false,
                    'has_hasilCutting' => $distribusi && $distribusi->hasilCutting ? true : false,
                ]
            ], 422);
        }

        // ===============================
        // GENERATE PDF
        // ===============================
        $pdf = Pdf::loadView('pdf.spk_cmt_barcode', [
            'spk'     => $spk,
            'qrItems'=> $qrItems,
        ])
        ->setPaper('a6', 'portrait');

        return $pdf->download("barcode_spk_cmt_{$spk->id_spk}.pdf");
    } catch (\Exception $e) {
        Log::error('Barcode PDF Error: Exception', [
            'spk_id' => $id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        return response()->json([
            'error' => 'Terjadi kesalahan saat membuat barcode PDF: ' . $e->getMessage()
        ], 500);
    }
}



    public function downloadStaffPdf($id)
    {
        $spk = SpkCmt::with('penjahit')->find($id);
        if (!$spk) {
            return response()->json(['error' => 'SPK not found'], 404);
        }

        $pdf = \App::make('snappy.pdf');
        $pdf->setOption('enable-local-file-access', true);

        $html = view('pdf.spk_cmt_staff', compact('spk'))->render();
        return response($pdf->getOutputFromHtml($html), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="spk_cmt_staff.pdf"'
        ]);
    }

    public function updateDeadline(Request $request, $id)
    {
        $validated = $request->validate([
            'deadline' => ['required', 'date'],
            'keterangan' => ['required', 'string', 'max:255'],
        ]);

        $spk = SpkCmt::findOrFail($id);
        $deadlineLama = $spk->deadline;

        if ($spk->deadline != $validated['deadline']) {

            $spk->deadline = $validated['deadline'];

            // 🔥 HITUNG ULANG SISA HARI
            $deadlineBaru = Carbon::parse($validated['deadline']);
            $spk->sisa_hari_terakhir = $deadlineBaru->isPast()
                ? 0
                : $deadlineBaru->diffInDays(now());

            $spk->save();

            LogDeadline::create([
                'id_spk' => $spk->id_spk,
                'deadline_lama' => $deadlineLama,
                'deadline_baru' => $validated['deadline'],
                'tanggal_aktivitas' => now(),
                'keterangan' => $validated['keterangan'],
            ]);
        }


        return response()->json([
            'message' => 'Deadline berhasil diperbarui',
            'data' => [
                'deadline' => $request->deadline,
                'keterangan' => $request->keterangan,
                'spk' => $spk,
            ]
        ]);
    }

    public function getLogDeadline($id)
    {
        $logs = LogDeadline::where('id_spk', $id)
            ->orderBy('tanggal_aktivitas', 'desc')
            ->get();

        return response()->json($logs);
    }

    public function getWarna($id)
    {
        $spk = SpkCmt::find($id);

        if (!$spk) {
            return response()->json(['message' => 'SPK not found'], 404);
        }

        $warna = $spk->warna; // SpkCmtWarna

        // Hitung total dikirim per warna (akumulasi semua pengiriman)
        $totalDikirimPerWarna = PengirimanWarna::whereHas('pengiriman', function ($q) use ($id) {
            $q->where('id_spk', $id);
        })
            ->selectRaw('warna, SUM(jumlah_dikirim) as total')
            ->groupBy('warna')
            ->pluck('total', 'warna');

        $totalDikirimSemua = $totalDikirimPerWarna->sum();

        $warnaWithSisa = $warna->map(function ($w) use ($totalDikirimPerWarna, $warna, $totalDikirimSemua) {
            $sudah = $totalDikirimPerWarna[$w->nama_warna] ?? 0;
            
            // Bypass fallback: Jika SPK cuma punya 1 target warna, kita anggap semua pengiriman masuk ke target ini
            if ($sudah === 0 && $warna->count() === 1) {
                $sudah = $totalDikirimSemua;
            }
            
            return [
                'id_warna' => $w->id,
                'nama_warna' => $w->nama_warna,
                'qty' => $w->qty,
                'sisa_qty' => max($w->qty - $sudah, 0),
            ];
        });

        return response()->json(['warna' => $warnaWithSisa]);
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:belum_diambil,sudah_diambil,pending,completed',
            'pending_until' => 'required_if:status,pending|date|after_or_equal:today',
            'alasan_pending' => 'nullable|string|max:500',
        ]);

        $spk = SpkCmt::findOrFail($id);

        if ($spk->status === $validated['status']) {
            return response()->json([
                'message' => 'Status sudah sama'
            ], 422);
        }
        $allowedTransitions = [
            'belum_diambil' => ['sudah_diambil'],
            'sudah_diambil' => ['pending', 'completed'],
            'pending'       => ['sudah_diambil'], // ✅ dibolehkan oleh sistem
            'completed'     => [],
        ];


        if (!in_array(
            $validated['status'],
            $allowedTransitions[$spk->status] ?? []
        )) {
            return response()->json([
                'message' => 'Transisi status tidak valid'
            ], 422);
        }

        // 🔥 LOGIC PENDING
        if ($validated['status'] === 'pending') {
            $spk->update([
                'status'        => 'pending',
                'pending_at'    => now(),
                'pending_until' => Carbon::parse($validated['pending_until'])->endOfDay(),
                'alasan_pending' => $validated['alasan_pending'] ?? null,
            ]);
        } else {
            $updateData = [
                'status' => $validated['status'],
                'pending_at' => null,
                'pending_until' => null,
                'alasan_pending' => null,
            ];
            if ($validated['status'] === 'sudah_diambil' && empty($spk->tanggal_ambil)) {
                $updateData['tanggal_ambil'] = Carbon::today()->toDateString();
            }
            $spk->update($updateData);
        }

        // Buat keterangan untuk log status
        $keterangan = 'Status diubah';
        if ($validated['status'] === 'pending') {
            $keterangan = 'Pending sampai ' . Carbon::parse($validated['pending_until'])->format('d-m-Y');
            if (!empty($validated['alasan_pending'])) {
                $keterangan .= ' | Alasan: ' . $validated['alasan_pending'];
            }
        }

        LogStatusSpkCmt::create([
            'spk_cmt_id' => $spk->id_spk,
            'status' => $validated['status'],
            'keterangan' => $keterangan,
        ]);


        return response()->json([
            'message' => 'Status SPK CMT berhasil diperbarui',
            'data' => $spk
        ]);
    }


    public function getAllLogDeadlines()
    {
        $logs = LogDeadline::orderBy('created_at', 'desc')->paginate(11);
        return response()->json($logs);
    }

    public function getAllLogStatus()
    {
        $logs = LogStatus::orderBy('created_at', 'desc')->paginate(11);
        return response()->json($logs);
    }

    public function debugDeadlines()
    {
        $spk = SpkCmt::all()->map(function ($item) {
            $deadline = \Carbon\Carbon::parse($item->deadline);
            return [
                'id_spk' => $item->id_spk,
                'deadline' => $item->deadline,
                'now' => now()->toDateString(),
                'sisa_hari' => $deadline->isPast() ? 0 : $deadline->diffInDays(now()),
            ];
        });

        return response()->json($spk);
    }

    public function getKinerjaCmt()
    {
        $spks = SpkCmt::with('penjahit')->get();

        $penjahitKinerja = [];

        foreach ($spks as $spk) {
            $status = $spk->status;
            if ($status === 'sudah_diambil') {
                continue;
            }

            $namaPenjahit = $spk->penjahit ? trim($spk->penjahit->nama_penjahit) : 'Tidak diketahui';
            $totalBarangDikirim = (int)$spk->total_barang_dikirim;
            $waktuPengerjaanTerakhir = $spk->waktu_pengerjaan_terakhir;

            \Log::info("SPK ID: {$spk->id_spk}, Penjahit: {$namaPenjahit}, Status: {$status}, Barang Dikirim: {$totalBarangDikirim}, Waktu Pengerjaan Terakhir: {$waktuPengerjaanTerakhir}");

            $kinerja = 0;
            if ($waktuPengerjaanTerakhir <= 7) {
                $kinerja = 100;
            } elseif ($waktuPengerjaanTerakhir <= 14) {
                $kinerja = 80;
            } elseif ($waktuPengerjaanTerakhir <= 21) {
                $kinerja = 60;
            } else {
                $kinerja = 40;
            }

            if (!isset($penjahitKinerja[$namaPenjahit])) {
                $penjahitKinerja[$namaPenjahit] = [
                    'total_kinerja' => 0,
                    'total_spk' => 0,
                    'rata_rata' => 0,
                    'kategori' => '',
                    'spks' => [],
                ];
            }
            $penjahitKinerja[$namaPenjahit]['spks'][] = [
                'id_spk' => $spk->id_spk,
                'total_barang_dikirim' => $spk->total_barang_dikirim,
                'waktu_pengerjaan_terakhir' => $waktuPengerjaanTerakhir,
                'kinerja' => $kinerja,
                'status' => $spk->status
            ];

            $penjahitKinerja[$namaPenjahit]['total_kinerja'] += $kinerja;
            $penjahitKinerja[$namaPenjahit]['total_spk']++;
        }

        foreach ($penjahitKinerja as $namaPenjahit => &$data) {
            $data['rata_rata'] = $data['total_spk'] > 0 ? $data['total_kinerja'] / $data['total_spk'] : 0;
            if ($data['rata_rata'] >= 90) {
                $data['kategori'] = 'A';
            } elseif ($data['rata_rata'] >= 80) {
                $data['kategori'] = 'B';
            } elseif ($data['rata_rata'] >= 70) {
                $data['kategori'] = 'C';
            } else {
                $data['kategori'] = 'D';
            }
        }
        return response()->json($penjahitKinerja);
    }


    public function tentukanKategori($spk)
    {
        $status = $spk->status;

        if (in_array($status, ['Completed', 'Pending'])) {
            $waktu = $spk->waktu_pengerjaan_terakhir;
        } else {

            return null;
        }

        if ($waktu <= 7) {
            return 'A';
        } elseif ($waktu > 7 && $waktu <= 14) {
            return 'B';
        } elseif ($waktu > 14 && $waktu <= 21) {
            return 'C';
        } else {
            return 'D';
        }
    }

    public function getKategoriCount()
    {
        $spks = SpkCmt::with('penjahit')->get();

        $kategoriCount = [
            'A' => 0,
            'B' => 0,
            'C' => 0,
            'D' => 0,
        ];

        foreach ($spks as $spk) {
            $namaPenjahit = $spk->penjahit ? trim($spk->penjahit->nama_penjahit) : 'Tidak diketahui';
            $waktuPengerjaanTerakhir = $spk->waktu_pengerjaan_terakhir;

            // Tentukan kinerja berdasarkan waktu pengerjaan
            if ($waktuPengerjaanTerakhir <= 7) {
                $kategori = 'A';
            } elseif ($waktuPengerjaanTerakhir <= 14) {
                $kategori = 'B';
            } elseif ($waktuPengerjaanTerakhir <= 21) {
                $kategori = 'C';
            } else {
                $kategori = 'D';
            }

            $kategoriCount[$kategori]++;
        }

        return response()->json($kategoriCount);
    }

    public function getKategoriCountByPenjahit()
    {
        $spks = SpkCmt::with('penjahit')->get();

        $penjahitKinerja = [];

        foreach ($spks as $spk) {
            $status = $spk->status;

            // Abaikan status 'In Progress'
            if ($status === 'In Progress') {
                continue;
            }

            $namaPenjahit = $spk->penjahit ? trim($spk->penjahit->nama_penjahit) : 'Tidak diketahui';
            $waktuPengerjaanTerakhir = $spk->waktu_pengerjaan_terakhir;

            if (!isset($penjahitKinerja[$namaPenjahit])) {
                $penjahitKinerja[$namaPenjahit] = [
                    'total_kinerja' => 0,
                    'total_spk' => 0,
                    'kategori' => '',
                ];
            }

            // Tentukan kinerja berdasarkan waktu pengerjaan
            if ($waktuPengerjaanTerakhir <= 7) {
                $kinerja = 100;
            } elseif ($waktuPengerjaanTerakhir <= 14) {
                $kinerja = 80;
            } elseif ($waktuPengerjaanTerakhir <= 21) {
                $kinerja = 60;
            } else {
                $kinerja = 40;
            }

            $penjahitKinerja[$namaPenjahit]['total_kinerja'] += $kinerja;
            $penjahitKinerja[$namaPenjahit]['total_spk']++;
        }

        $kategoriCount = [
            'A' => 0,
            'B' => 0,
            'C' => 0,
            'D' => 0,
        ];

        // Tentukan kategori untuk setiap penjahit
        foreach ($penjahitKinerja as $namaPenjahit => $data) {
            $rataRata = $data['total_spk'] > 0 ? $data['total_kinerja'] / $data['total_spk'] : 0;

            if ($rataRata >= 90) {
                $kategori = 'A';
            } elseif ($rataRata >= 80) {
                $kategori = 'B';
            } elseif ($rataRata >= 70) {
                $kategori = 'C';
            } else {
                $kategori = 'D';
            }

            $penjahitKinerja[$namaPenjahit]['kategori'] = $kategori;
            $kategoriCount[$kategori]++;
        }

        // Hitung persentase
        $totalPenjahit = array_sum($kategoriCount);
        foreach ($kategoriCount as $key => $count) {
            $kategoriCount[$key] = [
                'count' => $count,
                'percentage' => $totalPenjahit > 0 ? round(($count / $totalPenjahit) * 100, 2) : 0,
            ];
        }

        return response()->json($kategoriCount);
    }


    public function getKemampuanCmt()
    {
        $filterKategori = request()->input('kategori_sisa_produk');
        $filterKinerja = request()->input('kategori');

        // ✨ Tambahan untuk filter tanggal
        $startDate = request()->input('start_date'); // contoh: '2025-04-01'
        $endDate = request()->input('end_date');     // contoh: '2025-04-25'

        $penjahits = Penjahit::all();
        $kinerjaCmt = $this->getKinerjaCmt()->getData(true);
        $result = [];

        foreach ($penjahits as $penjahit) {
            if (empty($penjahit->nama_penjahit)) {
                continue;
            }

            $spks = SpkCmt::where('id_penjahit', $penjahit->id_penjahit)
                ->with('warna')
                ->get();

            $totalSisaProduk = 0;
            foreach ($spks as $spk) {
                $latestPengiriman = Pengiriman::where('id_spk', $spk->id_spk)
                    ->latest('tanggal_pengiriman')
                    ->first();

                if ($latestPengiriman) {
                    $totalSisaProduk += $latestPengiriman->sisa_barang;
                } else {
                    $totalSisaProduk += $spk->warna->sum('qty');
                }
            }

            // ✨ Ini bagian penting: Query pengiriman dengan filter tanggal
            $pengirimanQuery = Pengiriman::join('spk_cmt', 'pengiriman.id_spk', '=', 'spk_cmt.id_spk')
                ->where('spk_cmt.id_penjahit', $penjahit->id_penjahit)
                ->select('pengiriman.id_spk', 'pengiriman.total_barang_dikirim', 'pengiriman.tanggal_pengiriman')
                ->orderBy('pengiriman.tanggal_pengiriman');

            if (!empty($startDate) && !empty($endDate)) {
                $pengirimanQuery->whereBetween('pengiriman.tanggal_pengiriman', [$startDate, $endDate]);
            }

            $pengiriman = $pengirimanQuery->get();

            $totalSpk = $pengiriman->unique('id_spk')->count();
            $pengirimanPerMinggu = $pengiriman->groupBy(function ($item) {
                return Carbon::parse($item->tanggal_pengiriman)->year . '-M' . Carbon::parse($item->tanggal_pengiriman)->weekOfYear;
            });

            $jumlahMinggu = $pengirimanPerMinggu->count();
            $totalBarang = $pengiriman->sum('total_barang_dikirim');
            $rataRataPerminggu = $jumlahMinggu > 0 ? $totalBarang / $jumlahMinggu : 0;

            // (lanjutan kode kategori, result, dll tetap sama seperti punyamu...)

            $kemampuanPerMinggu = $pengirimanPerMinggu->map(function ($items, $minggu) {
                return [
                    'minggu' => $minggu,
                    'data' => $items->map(function ($item) {
                        return [
                            'id_spk' => $item->id_spk,
                            'total_barang_dikirim' => $item->total_barang_dikirim,
                        ];
                    })->values()
                ];
            })->values();

            $rataRata = $kinerjaCmt[$penjahit->nama_penjahit]['rata_rata'] ?? null;
            $kategori = $kinerjaCmt[$penjahit->nama_penjahit]['kategori'] ?? null;
            $spks = $kinerjaCmt[$penjahit->nama_penjahit]['spks'] ?? [];

            $kategoriSisaProduk = "Normal";
            if ($rataRataPerminggu == 0) {
                $kategoriSisaProduk = "-";
            } elseif ($totalSisaProduk > 2 * $rataRataPerminggu) {
                $kategoriSisaProduk = "Overload";
            } elseif ($totalSisaProduk >= $rataRataPerminggu && $totalSisaProduk <= 2 * $rataRataPerminggu) {
                $kategoriSisaProduk = "Underload";
            } else {
                $kategoriSisaProduk = "Normal";
            }
            if (empty($kategori)) {
                $kategori = "-";
            }

            if (!empty($filterKategori)) {
                if (strcasecmp($kategoriSisaProduk, $filterKategori) !== 0) {
                    continue;
                }
            }

            if (!empty($filterKinerja)) {
                if (strcasecmp($kategori, $filterKinerja) !== 0) {
                    continue;
                }
            }

            $result[$penjahit->nama_penjahit] = [
                'total_barang' => $totalBarang,
                'jumlah_minggu' => $jumlahMinggu,
                'rata_rata_perminggu' => round($rataRataPerminggu, 0),
                'total_spk' => $totalSpk,
                'total_sisa_produk' => $totalSisaProduk,
                'kategori_sisa_produk' => $kategoriSisaProduk,
                'rata_rata' => $rataRata,
                'kategori' => $kategori,
                'kemampuan_per_minggu' => $kemampuanPerMinggu,
                'spks' => $spks,
            ];
        }

        return response()->json($result);
    }

   
    public function getStatusCount(Request $request)
    {
        $user = auth()->user();

        $query = SpkCmt::query();

        // 🔐 role penjahit
        if ($user->hasRole('penjahit')) {
            $query->where('id_penjahit', $user->id_penjahit);
        }

        // 🔎 Filter berdasarkan CMT yang dipilih
        $idPenjahit = $request->query('id_penjahit');
        if ($idPenjahit) {
            $query->where('id_penjahit', $idPenjahit);
        }

        $allIds = (clone $query)->pluck('id_spk');
        $lewatIds = (clone $query)->whereRaw('DATEDIFF(deadline, CURDATE()) < 0')->pluck('id_spk');
        $mendekatiIds = (clone $query)->whereRaw('DATEDIFF(deadline, CURDATE()) BETWEEN 0 AND 7')->pluck('id_spk');
        $jauhIds = (clone $query)->whereRaw('DATEDIFF(deadline, CURDATE()) > 7')->pluck('id_spk');

        $totalProduk = \DB::table('spk_cmt_warna')->whereIn('spk_cmt_id', $allIds)->sum('qty');
        $totalKirim = \DB::table('pengiriman')->whereIn('id_spk', $allIds)->sum('total_barang_dikirim');
        $totalSisa = $totalProduk - $totalKirim;

        $jauhProduk = \DB::table('spk_cmt_warna')->whereIn('spk_cmt_id', $jauhIds)->sum('qty');
        $jauhKirim = \DB::table('pengiriman')->whereIn('id_spk', $jauhIds)->sum('total_barang_dikirim');
        $sisaJauh = $jauhProduk - $jauhKirim;

        $mendekatiProduk = \DB::table('spk_cmt_warna')->whereIn('spk_cmt_id', $mendekatiIds)->sum('qty');
        $mendekatiKirim = \DB::table('pengiriman')->whereIn('id_spk', $mendekatiIds)->sum('total_barang_dikirim');
        $sisaMendekati = $mendekatiProduk - $mendekatiKirim;

        $lewatProduk = \DB::table('spk_cmt_warna')->whereIn('spk_cmt_id', $lewatIds)->sum('qty');
        $lewatKirim = \DB::table('pengiriman')->whereIn('id_spk', $lewatIds)->sum('total_barang_dikirim');
        $sisaLewat = $lewatProduk - $lewatKirim;

        $counts = [
            'total_spk' => $allIds->count(),
            'total_produk' => (int) $totalProduk,
            'total_kirim' => (int) $totalKirim,
            'total_sisa' => (int) $totalSisa,
            'spk_mendekati' => $mendekatiIds->count(),
            'sisa_jauh' => (int) $sisaJauh,
            'sisa_mendekati' => (int) $sisaMendekati,
            'sisa_lewat' => (int) $sisaLewat,
        ];

        return response()->json($counts);
    }

    public function pendapatanSummary(Request $request)
    {
        $user = auth()->user();
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $status = $request->get('status', 'completed');

        $base = SpkCmt::query();
        if ($user && $user->hasRole('penjahit')) {
            $base->where('id_penjahit', $user->id_penjahit);
        }
        if ($status) {
            $statusList = is_array($status) ? $status : [$status];
            // Terima kedua kemungkinan istilah selesai
            $statusList = collect($statusList)->map(function ($s) {
                return $s === 'selesai' ? 'completed' : $s;
            })->push('selesai')->unique()->values()->all();
            $base->whereIn('status', $statusList);
        }
        if ($startDate) {
            $base->whereDate('spk_cmt.created_at', '>=', $startDate);
        }
        if ($endDate) {
            $base->whereDate('spk_cmt.created_at', '<=', $endDate);
        }

        try {
            $rows = (clone $base)
                ->leftJoin('penjahit_cmt as p', 'spk_cmt.id_penjahit', '=', 'p.id_penjahit')
                ->leftJoin('spk_cmt_warna as w', 'spk_cmt.id_spk', '=', 'w.spk_cmt_id')
                ->groupBy('spk_cmt.id_penjahit', 'p.nama_penjahit')
                ->selectRaw('
                    spk_cmt.id_penjahit,
                    COALESCE(p.nama_penjahit, "Tanpa Penjahit") as nama_penjahit,
                    COUNT(DISTINCT spk_cmt.id_spk) as total_spk,
                    SUM(COALESCE(w.qty,0)) as total_qty,
                    SUM(COALESCE(spk_cmt.total_harga,0)) as total_pendapatan
                ')
                ->orderByDesc('total_pendapatan')
                ->get();
        } catch (\Throwable $e) {
            \Log::error('Pendapatan CMT error', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Gagal menghitung pendapatan CMT',
            ], 500);
        }

        $summary = [
            'total_spk' => (int) ($rows->sum('total_spk')),
            'total_qty' => (int) ($rows->sum('total_qty')),
            'total_pendapatan' => (float) ($rows->sum('total_pendapatan')),
        ];

        return response()->json([
            'summary' => $summary,
            'rows' => $rows,
        ]);
    }
}
