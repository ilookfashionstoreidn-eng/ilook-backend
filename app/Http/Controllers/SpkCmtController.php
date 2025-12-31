<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SpkCmt;
use App\Models\Penjahit;
use App\Models\Warna;
use App\Models\LogDeadline;
use App\Models\LogStatus;
use App\Models\Pengiriman;
use App\Models\Produk;
use App\Models\SpkCuttingDistribusi;
use App\Models\SpkJasa;
use App\Models\SpkCmtWarna;
use App\Models\LogStatusSpkCmt;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;



class SpkCmtController extends Controller

{
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

            $sortColumn = $sortBy === 'sisa_hari' ? 'deadline' : $sortBy;

            $query = SpkCmt::with([
                'warna',
                'pengiriman.warna',
                'penjahit',
                'spkCuttingDistribusi.detail',
                'spkCuttingDistribusi.spkCutting.produk',
                'spkJasa.spkCuttingDistribusi.detail',
                'spkJasa.spkCuttingDistribusi.spkCutting.produk',
                'spkCuttingDistribusi', 

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
                ->orderBy($sortColumn, $sortOrder);

            $spk = $allData
                ? $query->get()
                : $query->paginate(10);

            // 🧠 transform response
            $spk->through(function ($item) {
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
                }

                // Hitung jumlah_produk dari warna
                $jumlahProduk = $item->warna->sum('qty');

                return [
                    'id_spk' => $item->id_spk,
                    'deadline' => $item->deadline,
                    'status' => $item->status,

                    'waktu_pengerjaan' => $item->waktu_pengerjaan,
                    'sisa_hari' => $item->sisa_hari,
                    'sisa_hari_status' => $item->sisa_hari_status,

                    'penjahit' => $item->penjahit,
                    'warna' => $item->warna,
                    'pengiriman' => $item->pengiriman,

                    'total_barang_dikirim' => $item->pengiriman->sum('total_barang_dikirim'),

                    // Field untuk frontend
                    'nama_produk' => $produk?->nama_produk,
                    'nomor_seri' => $nomorSeri,
                    'jumlah_produk' => $jumlahProduk,
                    'kategori_produk' => $produk?->kategori_produk,

                    // ✅ TAMBAHKAN INI UNTUK DETAIL POPUP
                    'gambar_produk' => $produk?->gambar_produk, // Gambar dari produk
                    'created_at' => $item->created_at, // Sebagai "Tanggal SPK"
                    'merek' => $item->merek,
                    'aksesoris' => $item->aksesoris,
                    'catatan' => $item->catatan,
                    'keterangan' => $item->keterangan, // Jika ingin ditampilkan juga

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
            });

            return response()->json([
                'spk' => $spk,
            ]);
        }

        


    public function create()
    {
        $penjahits = Penjahit::all();
        return response()->json($penjahits);
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
                'id_penjahit' => 'required|exists:penjahit_cmt,id_penjahit',

                'keterangan'  => 'nullable|string',
                'catatan'     => 'nullable|string',
                'markeran'    => 'nullable|string',
                'aksesoris'   => 'nullable|string',
                'handtag'     => 'nullable|string',
                'merek'       => 'nullable|string',

                // 🔴 BARU
                'harga_barang_dasar' => 'required|numeric|min:0',
                'jenis_harga_barang' => 'required|in:per_pcs,per_lusin',

                'harga_per_jasa'     => 'required|numeric|min:0',
                'jenis_harga_jasa'   => 'required|in:per_barang,per_lusin',
            ]);

            // ===============================
            // 2️⃣ RESOLVE DISTRIBUSI
            // ===============================
            if ($validated['source_type'] === 'cutting') {
                $distribusi = SpkCuttingDistribusi::with('detail')
                    ->findOrFail($validated['source_id']);
            } else {
                $jasa = SpkJasa::with('spkCuttingDistribusi.detail')
                    ->findOrFail($validated['source_id']);
                $distribusi = $jasa->spkCuttingDistribusi;
            }

            if (!$distribusi || $distribusi->detail->isEmpty()) {
                throw new \Exception('Distribusi tidak memiliki detail warna');
            }

            // ===============================
            // 3️⃣ SNAPSHOT WARNA
            // ===============================
            $warnaData = $distribusi->detail->map(fn($d) => [
                'nama_warna' => $d->warna,
                'qty'        => (int) $d->jumlah_produk,
            ]);

            $jumlahBarang = $warnaData->sum('qty');

            // ===============================
            // 4️⃣ HITUNG HARGA BARANG
            // ===============================
            $hargaPerBarang = $validated['jenis_harga_barang'] === 'per_lusin'
                ? $validated['harga_barang_dasar'] / 12
                : $validated['harga_barang_dasar'];

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
                'id_penjahit' => $validated['id_penjahit'],
                'status'      => 'belum_diambil',

                'keterangan'  => $validated['keterangan'] ?? null,
                'catatan'     => $validated['catatan'] ?? null,
                'markeran'    => $validated['markeran'] ?? null,
                'aksesoris'   => $validated['aksesoris'] ?? null,
                'handtag'     => $validated['handtag'] ?? null,
                'merek'       => $validated['merek'] ?? null,

                // 🔴 HARGA BARANG
                'harga_barang_dasar' => $validated['harga_barang_dasar'],
                'jenis_harga_barang' => $validated['jenis_harga_barang'],
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
                'data'    => $spk->load('warna'),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }




    public function show($id)
    {
        $spk = SpkCmt::findOrFail($id);
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
        if (is_string($request->input('warna'))) {
            $request->merge([
                'warna' => json_decode($request->input('warna'), true),
            ]);
        }
        $validated = $request->validate([
            'id_produk' => 'required|exists:produk,id',
            'deadline' => 'required|date',
            'id_penjahit' => 'required|exists:penjahit_cmt,id_penjahit',
            'keterangan' => 'nullable|string',
            'status' => 'required|string|in:Pending,In Progress,Completed',
            'catatan' => 'nullable|string',
            'markeran' => 'nullable|string',
            'aksesoris' => 'nullable|string',
            'handtag' => 'nullable|string',
            'merek' => 'nullable|string',
            'harga_per_barang' => 'required|numeric',
            'harga_per_jasa' => 'required|numeric',
            'jenis_harga_jasa' => 'required|in:per_barang,per_lusin',
            'warna' => 'required|array',
            'warna.*.id_warna' => 'nullable|exists:warna,id_warna',
            'warna.*.nama_warna' => 'required|string|max:50',
            'warna.*.qty' => 'required|integer|min:1',
        ]);
        $spk = SpkCmt::findOrFail($id);

        $harga_jasa_awal = $validated['harga_per_jasa'];
        $harga_per_jasa = $validated['jenis_harga_jasa'] === 'per_lusin'
            ? $harga_jasa_awal / 12
            : $harga_jasa_awal;

        $jumlahProduk = collect($validated['warna'])->sum('qty');
        $totalHarga = $validated['harga_per_barang'] * $jumlahProduk;

        $spk->update(array_merge($validated, [
            'jumlah_produk' => $jumlahProduk,
            'total_harga' => $totalHarga,
            'harga_per_jasa' => $harga_per_jasa,
            'harga_jasa_awal' => $harga_jasa_awal,
        ]));

        $warnaIds = collect($validated['warna'])->pluck('id_warna')->filter()->toArray();

        Warna::where('id_spk', $spk->id_spk)
            ->whereNotIn('id_warna', $warnaIds)
            ->delete();

        foreach ($validated['warna'] as $warna) {
            if (isset($warna['id_warna'])) {
                $existingWarna = Warna::find($warna['id_warna']);
                if ($existingWarna) {
                    $existingWarna->update([
                        'nama_warna' => $warna['nama_warna'],
                        'qty' => $warna['qty'],
                    ]);
                }
            } else {
                Warna::create([
                    'id_spk' => $spk->id_spk,
                    'nama_warna' => $warna['nama_warna'],
                    'qty' => $warna['qty'],
                ]);
            }
        }

        return response()->json([
            'message' => 'SPK berhasil diperbarui!',
            'data' => $spk
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
            'spkJasa.spkCuttingDistribusi.spkCutting.produk',
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
            $spk->update(['deadline' => $validated['deadline']]);

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
        $warna = $spk->warna;
        return response()->json(['warna' => $warna]);
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:belum_diambil,sudah_diambil,selesai',
        ]);

        $spk = SpkCmt::findOrFail($id);

        if ($spk->status === $validated['status']) {
            return response()->json([
                'message' => 'Status sudah sama'
            ], 422);
        }

        // Validasi transisi status - izinkan perubahan antara belum_diambil dan sudah_diambil
        $allowedTransitions = [
            'belum_diambil' => ['sudah_diambil'],
            'sudah_diambil' => ['belum_diambil', 'selesai'],
            'selesai' => [],
        ];

        if (!in_array(
            $validated['status'],
            $allowedTransitions[$spk->status] ?? []
        )) {
            return response()->json([
                'message' => 'Transisi status tidak valid. Dari "' . $spk->status . '" hanya bisa ke: ' . implode(', ', $allowedTransitions[$spk->status] ?? [])
            ], 422);
        }

        // ✅ CUKUP UPDATE STATUS SAJA
        $spk->update([
            'status' => $validated['status']
        ]);

        // ✅ SIMPAN LOG STATUS
        LogStatusSpkCmt::create([
            'spk_cmt_id' => $spk->id_spk,
            'status' => $validated['status'],
            'keterangan' => 'Status diubah'
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
            if ($status === 'In Progress') {
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
                ->select('id_spk', 'jumlah_produk')
                ->get();

            $totalSisaProduk = 0;
            foreach ($spks as $spk) {
                $latestPengiriman = Pengiriman::where('id_spk', $spk->id_spk)
                    ->latest('tanggal_pengiriman')
                    ->first();

                if ($latestPengiriman) {
                    $totalSisaProduk += $latestPengiriman->sisa_barang;
                } else {
                    $totalSisaProduk += $spk->jumlah_produk;
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
}
