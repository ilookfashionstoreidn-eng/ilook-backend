<?php

namespace App\Http\Controllers;

use App\Models\Pengiriman;
use App\Models\PengirimanWarna;
use App\Models\Warna;
use App\Models\SpkCmt;
use App\Models\SpkCmtWarna;
use App\Models\SpkCuttingDistribusi;
use App\Models\SpkJasa;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;

class PengirimanController extends Controller
{
    public function index(Request $request)
    {
        $idPenjahit = $request->query('id_penjahit');
        $sortBy = $request->query('sortBy', 'created_at');
        $sortOrder = $request->query('sortOrder', 'desc');
        $allData = $request->query('allData');
        $statusVerifikasi = $request->query('status_verifikasi');

        $query = Pengiriman::with([
            'warna',
            'penjahit',
            'spk:id_spk,id_penjahit,harga_per_jasa,harga_per_barang,source_type,source_id',
            'spk.penjahit:id_penjahit,nama_penjahit',
            'spk.spkCuttingDistribusi.spkCutting.produk:id,nama_produk',
            'spk.spkJasa.spkCuttingDistribusi.spkCutting.produk:id,nama_produk',
        ]);

        // filter CMT / penjahit
        $query->when($idPenjahit, function ($q) use ($idPenjahit) {
            $q->whereHas('spk.penjahit', function ($sub) use ($idPenjahit) {
                $sub->where('id_penjahit', $idPenjahit);
            });
        });

        // filter status verifikasi
        $query->when($statusVerifikasi, function ($q) use ($statusVerifikasi) {
            $q->where('status_verifikasi', $statusVerifikasi);
        });

        $query->orderBy($sortBy, $sortOrder);

        $pengirimans = $allData === 'true'
            ? $query->get()
            : $query->paginate(10);

        $pengirimans->transform(function ($pengiriman) {

            // master warna dari SPK
            $warnaSpk = SpkCmtWarna::where('spk_cmt_id', $pengiriman->id_spk)->get();

            // total dikirim per warna (akumulasi semua pengiriman)
            $totalDikirimPerWarna = PengirimanWarna::whereHas('pengiriman', function ($q) use ($pengiriman) {
                $q->where('id_spk', $pengiriman->id_spk);
            })
                ->selectRaw('warna, SUM(jumlah_dikirim) as total')
                ->groupBy('warna')
                ->pluck('total', 'warna');

            $sisaBarangPerWarna = [];

            foreach ($warnaSpk as $warna) {
                $sudah = $totalDikirimPerWarna[$warna->nama_warna] ?? 0;
                $sisaBarangPerWarna[$warna->nama_warna] = $warna->qty - $sudah;
            }

            // Ambil nama produk dari SPK
            $namaProduk = null;
            $spk = $pengiriman->spk;

            if ($spk && $spk->source_type) {
                try {
                    if ($spk->source_type === 'cutting' && $spk->source_id) {
                        // Load relasi untuk cutting
                        $distribusi = SpkCuttingDistribusi::with('spkCutting.produk')
                            ->find($spk->source_id);
                        $namaProduk = $distribusi->spkCutting->produk->nama_produk ?? null;
                    } else if ($spk->source_type === 'jasa' && $spk->source_id) {
                        // Load relasi untuk jasa
                        $jasa = SpkJasa::with('spkCuttingDistribusi.spkCutting.produk')
                            ->find($spk->source_id);
                        $namaProduk = $jasa->spkCuttingDistribusi->spkCutting->produk->nama_produk ?? null;
                    }
                } catch (\Exception $e) {
                    Log::error('Error getting nama_produk for pengiriman: ' . $e->getMessage());
                }

                if (empty($namaProduk)) {
                    $namaProduk = $spk->nama_produk;
                }
            } else if ($pengiriman->warna->count() > 0) {
                // Bypass flow: gabungkan nama SKU dari pengiriman_warna
                $namaProduk = $pengiriman->warna->pluck('warna')->implode(', ');
            }

            // detail warna pengiriman ini (dan pecah SKU ke produk/warna jika ada tanda strip)
            $warnaMapped = $pengiriman->warna->map(function ($w) use (&$namaProduk) {
                $warnaStr = $w->warna;
                
                // Jika mengandung strip ' - ', kita pecah
                if (strpos($warnaStr, ' - ') !== false) {
                    $parts = explode(' - ', $warnaStr, 2);
                    // Set nama produk dari bagian pertama jika belum ada
                    if (empty($namaProduk) || $namaProduk === '-') {
                        $namaProduk = trim($parts[0]);
                    }
                    $warnaStr = trim($parts[1]);
                }
                
                return [
                    'warna' => $warnaStr,
                    'jumlah_dikirim' => $w->jumlah_dikirim,
                    'sisa_barang_per_warna' => $w->sisa_barang_per_warna,
                ];
            });

            return [
                'id_pengiriman' => $pengiriman->id_pengiriman,
                'id_spk' => $pengiriman->id_spk,
                'no_seri_pengiriman' => $pengiriman->no_seri_pengiriman,
                'tanggal_pengiriman' => $pengiriman->tanggal_pengiriman,
                'total_barang_dikirim' => $pengiriman->total_barang_dikirim,
                'sisa_barang' => $pengiriman->sisa_barang,
                'status_verifikasi' => $pengiriman->status_verifikasi,
                'total_bayar' => $pengiriman->total_bayar,
                'foto_nota' => $pengiriman->foto_nota,
                'claim' => $pengiriman->claim,
                'refund_claim' => $pengiriman->refund_claim,
                'status_claim' => $pengiriman->status_claim,

                // relasi CMT (prioritaskan dari spk, jika tidak ada, dari penjahit langsung)
                'nama_penjahit' => $spk->penjahit->nama_penjahit ?? $pengiriman->penjahit->nama_penjahit ?? null,
                'id_penjahit' => $spk->id_penjahit ?? $pengiriman->id_penjahit,

                'nama_produk' => $namaProduk,

                'warna' => $warnaMapped,

                // agregat sisa warna SPK
                'sisa_barang_per_warna' => $sisaBarangPerWarna,
            ];
        });

        return response()->json($pengirimans, 200);
    }



    public function storePetugasBawah(Request $request)
    {
        // Decode items if it's a serialized JSON string from FormData
        if ($request->has('items') && is_string($request->input('items'))) {
            $decoded = json_decode($request->input('items'), true);
            if (is_array($decoded)) {
                $request->merge(['items' => $decoded]);
            }
        }

        $validated = $request->validate([
            'id_penjahit' => 'required|integer|exists:penjahit_cmt,id_penjahit',
            'no_seri' => 'nullable|string|max:100',
            'no_seri_pengiriman' => 'nullable|string|max:100',
            'tanggal_pengiriman' => 'required|date',
            'foto_nota' => 'required|file|mimes:jpeg,png,jpg,pdf|max:5120',
            'total_bayar' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.sku' => 'required|string|max:100',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.harga' => 'nullable|numeric|min:0',
        ]);

        $totalBarangDikirim = collect($validated['items'])->sum('qty');

        // Upload foto nota
        $fotoNotaPath = null;
        if ($request->hasFile('foto_nota')) {
            $fotoNotaPath = $request->file('foto_nota')->store('nota_pengiriman', 'public');
        }

        // Normalisasi tanggal
        $tanggalPengiriman = Carbon::parse($validated['tanggal_pengiriman'])->startOfDay();
        $hariIni = Carbon::today();

        if ($tanggalPengiriman->lt($hariIni)) {
            return response()->json([
                'error' => 'Tanggal pengiriman tidak boleh sebelum hari ini.'
            ], 400);
        }

        // Bypass: cari SPK berdasarkan no_seri (opsional)
        $idSpk = null;
        $noSeri = $validated['no_seri'] ?? null;

        // Coba cari di SpkCuttingDistribusi berdasarkan kode_seri
        $distribusi = SpkCuttingDistribusi::where('kode_seri', $noSeri)->first();
        if ($distribusi) {
            $spk = SpkCmt::where('source_type', 'cutting')
                ->where('source_id', $distribusi->id)
                ->first();
            if ($spk) {
                $idSpk = $spk->id_spk;
            }
        }

        // Jika tidak ditemukan via cutting, coba via jasa
        if (!$idSpk && $noSeri) {
            $jasaDistribusi = SpkCuttingDistribusi::where('kode_seri', $noSeri)->first();
            if ($jasaDistribusi) {
                $jasa = SpkJasa::where('id_spk_cutting_distribusi', $jasaDistribusi->id)->first();
                if ($jasa) {
                    $spk = SpkCmt::where('source_type', 'jasa')
                        ->where('source_id', $jasa->id_spk_jasa)
                        ->first();
                    if ($spk) {
                        $idSpk = $spk->id_spk;
                    }
                }
            }
        }

        // Jika masih tidak ditemukan (misal dari hasil import/migrasi)
        if (!$idSpk && $noSeri) {
            $spk = SpkCmt::where('nomor_seri', $noSeri)->first();
            if ($spk) {
                $idSpk = $spk->id_spk;
            }
        }

        // Hitung Sisa Barang SPK
        $totalSisaSpk = 0;
        if ($idSpk) {
            $totalTargetSpk = \DB::table('spk_cmt_warna')->where('spk_cmt_id', $idSpk)->sum('qty');
            $totalKirimSpkSebelumnya = \DB::table('pengiriman')->where('id_spk', $idSpk)->sum('total_barang_dikirim');
            $totalSisaSpk = max(0, $totalTargetSpk - $totalKirimSpkSebelumnya - $totalBarangDikirim);
        }

        // Simpan data pengiriman
        $pengiriman = Pengiriman::create([
            'id_spk' => $idSpk, // bisa null jika bypass
            'id_penjahit' => $validated['id_penjahit'],
            'no_seri_pengiriman' => $validated['no_seri_pengiriman'] ?? null,
            'tanggal_pengiriman' => $validated['tanggal_pengiriman'],
            'total_barang_dikirim' => $totalBarangDikirim,
            'total_bayar' => $validated['total_bayar'] ?? 0,
            'foto_nota' => $fotoNotaPath,
            'status_verifikasi' => 'pending',
            'sisa_barang' => $totalSisaSpk,
            'status_claim' => 'belum_dibayar',
        ]);

        // Simpan rincian item sebagai PengirimanWarna
        foreach ($validated['items'] as $item) {
            PengirimanWarna::create([
                'id_pengiriman' => $pengiriman->id_pengiriman,
                'warna' => $item['sku'],
                'jumlah_dikirim' => $item['qty'],
                'sisa_barang_per_warna' => 0,
            ]);
        }

        return response()->json([
            'message' => 'Pengiriman berhasil disimpan.',
            'data' => $pengiriman,
            'matched_spk' => $idSpk ? true : false,
        ], 201);
    }


    public function updatePetugasAtas(Request $request, $id_pengiriman)
    {
        $validated = $request->validate([
            'warna' => 'required|array|min:1',
            'warna.*.warna' => 'required|string|max:50',
            'warna.*.jumlah_dikirim' => 'required|integer|min:0',
        ]);

        $pengiriman = Pengiriman::findOrFail($id_pengiriman);
        $spk = SpkCmt::findOrFail($pengiriman->id_spk);

        // 🔑 SUMBER WARNA RESMI (SPK CMT WARNA)
        $warnaSpk = SpkCmtWarna::where('spk_cmt_id', $spk->id_spk)->get();

        if ($warnaSpk->isEmpty()) {
            return response()->json([
                'error' => 'SPK CMT tidak memiliki data warna.'
            ], 400);
        }

        // Semua pengiriman warna sebelumnya (kecuali pengiriman ini)
        $pengirimanWarnaSebelumnya = PengirimanWarna::whereHas('pengiriman', function ($q) use ($spk, $pengiriman) {
            $q->where('id_spk', $spk->id_spk)
                ->where('id_pengiriman', '!=', $pengiriman->id_pengiriman);
        })->get();

        $sudahDikirimPerWarna = $pengirimanWarnaSebelumnya
            ->groupBy('warna')
            ->map(fn($group) => $group->sum('jumlah_dikirim'));

        // Validasi total harus sama dengan input petugas bawah
        $totalDikirimPetugasAtas = collect($validated['warna'])->sum('jumlah_dikirim');

        if ($totalDikirimPetugasAtas !== $pengiriman->total_barang_dikirim) {
            return response()->json([
                'error' => 'Total per warna harus sama dengan total barang dikirim.'
            ], 400);
        }

        $sisaBarangPerWarna = [];

        foreach ($validated['warna'] as $item) {
            $namaWarna = $item['warna'];
            $jumlahDikirim = $item['jumlah_dikirim'];

            $warnaSpkItem = $warnaSpk->firstWhere('nama_warna', $namaWarna);

            // Bypass fallback: Jika item pakai nama SKU tapi SPK CMT cuma punya 1 target warna, kita anggap cocok
            if (!$warnaSpkItem && $warnaSpk->count() === 1) {
                $warnaSpkItem = $warnaSpk->first();
            }

            if (!$warnaSpkItem) {
                return response()->json([
                    'error' => "Warna/SKU {$namaWarna} tidak terdaftar di SPK CMT."
                ], 400);
            }

            $stokAwal = $warnaSpkItem->qty;
            $sudahDikirim = $sudahDikirimPerWarna[$namaWarna] ?? 0;
            $totalSetelah = $sudahDikirim + $jumlahDikirim;

            if ($totalSetelah > $stokAwal) {
                return response()->json([
                    'error' => "Pengiriman warna {$namaWarna} melebihi kapasitas SPK. Maks: {$stokAwal}, sudah dikirim: {$sudahDikirim}"
                ], 400);
            }

            $sisa = $stokAwal - $totalSetelah;

            PengirimanWarna::updateOrCreate(
                [
                    'id_pengiriman' => $pengiriman->id_pengiriman,
                    'warna' => $namaWarna,
                ],
                [
                    'jumlah_dikirim' => $jumlahDikirim,
                    'sisa_barang_per_warna' => $sisa,
                ]
            );

            $sisaBarangPerWarna[$namaWarna] = $sisa;
        }

        // Hitung total bayar & klaim
        $totalBayar = $totalDikirimPetugasAtas * $spk->harga_per_jasa;
        $totalSisa = array_sum($sisaBarangPerWarna);
        $claim = $totalSisa > 0 ? $totalSisa * $spk->harga_per_barang : 0;

        // Refund claim = claim pengiriman sebelumnya
        $pengirimanSebelumnya = Pengiriman::where('id_spk', $spk->id_spk)
            ->where('id_pengiriman', '<', $pengiriman->id_pengiriman)
            ->orderBy('id_pengiriman', 'desc')
            ->first();

        $refundClaim = $pengirimanSebelumnya ? $pengirimanSebelumnya->claim : 0;

        // Cek apakah SPK selesai
        $semuaWarnaSelesai = $warnaSpk->every(function ($warna) use ($sudahDikirimPerWarna, $validated) {
            $sudah = $sudahDikirimPerWarna[$warna->nama_warna] ?? 0;
            $dikirimSekarang = collect($validated['warna'])
                ->firstWhere('warna', $warna->nama_warna)['jumlah_dikirim'] ?? 0;

            return ($sudah + $dikirimSekarang) >= $warna->qty;
        });

        if ($semuaWarnaSelesai) {
            $spk->setStatus('Completed');
        }

        $pengiriman->update([
            'status_verifikasi' => 'valid',
            'sisa_barang' => $totalSisa,
            'total_bayar' => $totalBayar,
            'claim' => $claim,
            'refund_claim' => $refundClaim,
            'status_claim' => 'belum_dibayar', // Default status claim saat verifikasi
        ]);

        return response()->json([
            'message' => 'Pengiriman diverifikasi dan diperbarui.',
            'data' => $pengiriman,
            'sisa_barang_per_warna' => $sisaBarangPerWarna,
            'total_bayar' => $totalBayar,
            'claim' => $claim,
            'refund_claim' => $refundClaim,
        ], 200);
    }


    public function updateStatusClaim(Request $request, $id_pengiriman)
    {
        $validated = $request->validate([
            'status_claim' => 'required|in:belum_dibayar,sudah_dibayar',
        ]);

        $pengiriman = Pengiriman::findOrFail($id_pengiriman);

        $pengiriman->update([
            'status_claim' => $validated['status_claim'],
        ]);

        return response()->json([
            'message' => 'Status claim berhasil diperbarui.',
            'data' => $pengiriman,
        ], 200);
    }

    public function destroy($id_pengiriman)
    {
        $pengiriman = Pengiriman::find($id_pengiriman);
        if (!$pengiriman) {
            return response()->json(['error' => 'Data pengiriman tidak ditemukan.'], 404);
        }
        PengirimanWarna::where('id_pengiriman', $id_pengiriman)->delete();

        if ($pengiriman->foto_nota) {
            Storage::disk('public')->delete($pengiriman->foto_nota);
        }
        $pengiriman->delete();
        return response()->json(['message' => 'Data pengiriman berhasil dihapus.'], 200);
    }
    public function downloadTemplate()
    {
        $headers = [
            'TANGGAL PENGIRIMAN', 'NAMA PENJAHIT', 'NOMOR SERI PENGIRIMAN', 'NOMOR SERI SPK', 'WARNA', 'JUMLAH DIKIRIM', 'TOTAL BAYAR'
        ];
        $rows = [
            [
                '2026-06-17', 'Nama Penjahit Anda', 'INV-001', '3368', 'Merah', '50', '150000'
            ]
        ];

        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\ProductListExport($headers, $rows), 'template_pengiriman.xlsx');
    }

    private function parseExcelDate($val)
    {
        if (!$val) return null;
        if (is_numeric($val)) {
            try {
                $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$val);
                return Carbon::instance($dt);
            } catch (\Throwable $e) {}
        }
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $val, $matches)) {
            try {
                return Carbon::createFromDate((int)$matches[3], (int)$matches[2], (int)$matches[1]);
            } catch (\Throwable $e) {}
        }
        try {
            return Carbon::parse($val);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function import(Request $request)
    {
        ini_set('max_execution_time', 300);
        $validated = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($validated['file']->getRealPath());
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($validated['file']->getRealPath());
            $rows = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);

            if (count($rows) < 2) {
                return response()->json(['message' => 'File import kosong atau tidak memiliki data.'], 422);
            }

            $headers = array_map(function ($value) {
                return strtolower(trim((string) $value));
            }, $rows[0]);

            $headerMap = [];
            foreach ($headers as $index => $header) {
                if ($header !== '') {
                    $headerMap[$header] = $index;
                }
            }

            \DB::beginTransaction();

            $processed = 0;
            $skipped = 0;
            $errors = [];

            // Grupkan berdasarkan TANGGAL PENGIRIMAN, NAMA PENJAHIT, NOMOR SERI PENGIRIMAN, NOMOR SERI SPK
            // karena satu pengiriman bisa punya beberapa baris warna
            $groupedPengiriman = [];

            foreach (array_slice($rows, 1) as $index => $row) {
                $rowNumber = $index + 2;
                
                $hasContent = false;
                foreach ($row as $val) {
                    if (trim((string) $val) !== '') {
                        $hasContent = true; break;
                    }
                }
                if (!$hasContent) continue;

                $tglVal = $row[$headerMap['tanggal pengiriman'] ?? -1] ?? null;
                $penjahitVal = $row[$headerMap['nama penjahit'] ?? -1] ?? null;
                $noSeriPengirimanVal = $row[$headerMap['nomor seri pengiriman'] ?? -1] ?? null;
                $noSeriSpkVal = $row[$headerMap['nomor seri spk'] ?? -1] ?? null;
                $warnaVal = $row[$headerMap['warna'] ?? -1] ?? 'Mix / Custom';
                $qtyVal = $row[$headerMap['qty'] ?? -1] ?? ($row[$headerMap['jumlah dikirim'] ?? -1] ?? 0);
                $totalBayarVal = $row[$headerMap['total bayar'] ?? -1] ?? 0;

                if (!$penjahitVal || !$noSeriPengirimanVal || !$noSeriSpkVal) {
                    $skipped++;
                    $errors[] = ['row' => $rowNumber, 'message' => 'Nama Penjahit, Nomor Seri Pengiriman, dan Nomor Seri SPK wajib diisi.'];
                    continue;
                }
                
                $groupKey = md5(trim($tglVal) . trim($penjahitVal) . trim($noSeriPengirimanVal) . trim($noSeriSpkVal));
                
                if (!isset($groupedPengiriman[$groupKey])) {
                    $groupedPengiriman[$groupKey] = [
                        'rows' => [],
                        'tgl' => $tglVal,
                        'penjahit' => trim($penjahitVal),
                        'no_seri_pengiriman' => trim($noSeriPengirimanVal),
                        'no_seri_spk' => trim($noSeriSpkVal),
                        'total_bayar' => 0,
                    ];
                }
                
                $cleanTotalBayar = (float) str_replace(',', '', (string)$totalBayarVal);
                $groupedPengiriman[$groupKey]['total_bayar'] += $cleanTotalBayar;
                $groupedPengiriman[$groupKey]['rows'][] = [
                    'row' => $rowNumber,
                    'warna' => trim($warnaVal),
                    'qty' => (int) $qtyVal
                ];
            }

            foreach ($groupedPengiriman as $group) {
                $penjahit = \App\Models\Penjahit::where('nama_penjahit', 'like', $group['penjahit'])->first();
                if (!$penjahit) {
                    $skipped += count($group['rows']);
                    $errors[] = ['row' => $group['rows'][0]['row'], 'message' => "Penjahit dengan nama '{$group['penjahit']}' tidak ditemukan."];
                    continue;
                }

                $idSpk = null;
                $spk = \App\Models\SpkCmt::where('nomor_seri', $group['no_seri_spk'])
                    ->orWhereHas('spkCuttingDistribusi', function($q) use ($group) {
                        $q->where('kode_seri', $group['no_seri_spk']);
                    })->first();
                
                if ($spk) {
                    $idSpk = $spk->id_spk;
                } else {
                    $skipped += count($group['rows']);
                    $errors[] = ['row' => $group['rows'][0]['row'], 'message' => "Nomor Seri SPK '{$group['no_seri_spk']}' tidak ditemukan di database. Pastikan SPK sudah terdaftar."];
                    continue;
                }

                $tglParsed = $this->parseExcelDate($group['tgl']) ?: now();

                $totalDikirim = collect($group['rows'])->sum('qty');

                $pengiriman = \App\Models\Pengiriman::create([
                    'id_spk' => $idSpk,
                    'id_penjahit' => $penjahit->id_penjahit,
                    'no_seri_pengiriman' => $group['no_seri_pengiriman'],
                    'tanggal_pengiriman' => $tglParsed->format('Y-m-d'),
                    'total_barang_dikirim' => $totalDikirim,
                    'sisa_barang' => 0, // Akan dihitung dari SPK jika ada
                    'total_bayar' => $group['total_bayar'],
                    'foto_nota' => 'nota_pengiriman/default.jpg', // Dummy untuk import
                    'status_verifikasi' => 'pending',
                ]);

                // Hitung sisa barang keseluruhan SPK (jika ada SPK)
                if ($idSpk) {
                    $totalDikirimSebelumnya = \App\Models\Pengiriman::where('id_spk', $idSpk)
                        ->where('id_pengiriman', '<=', $pengiriman->id_pengiriman)
                        ->sum('total_barang_dikirim');
                    
                    $totalSpk = \App\Models\SpkCmtWarna::where('spk_cmt_id', $idSpk)->sum('qty');
                    $sisaBarangGlobal = max(0, $totalSpk - $totalDikirimSebelumnya);
                    $pengiriman->update(['sisa_barang' => $sisaBarangGlobal]);
                }

                foreach ($group['rows'] as $item) {
                    $sisaPerWarna = 0;
                    if ($idSpk) {
                        $qtyWarnaSpk = \App\Models\SpkCmtWarna::where('spk_cmt_id', $idSpk)
                            ->where('nama_warna', $item['warna'])
                            ->sum('qty');
                            
                        $dikirimWarnaSblm = \App\Models\PengirimanWarna::whereHas('pengiriman', function($q) use ($idSpk, $pengiriman) {
                                $q->where('id_spk', $idSpk)->where('id_pengiriman', '<=', $pengiriman->id_pengiriman);
                            })
                            ->where('warna', $item['warna'])
                            ->sum('jumlah_dikirim');
                            
                        $sisaPerWarna = max(0, $qtyWarnaSpk - $dikirimWarnaSblm);
                    }

                    \App\Models\PengirimanWarna::create([
                        'id_pengiriman' => $pengiriman->id_pengiriman,
                        'warna' => $item['warna'],
                        'jumlah_dikirim' => $item['qty'],
                        'sisa_barang_per_warna' => $sisaPerWarna,
                    ]);
                }

                $processed++;
            }

            \DB::commit();

            return response()->json([
                'message' => 'Import selesai.',
                'processed' => $processed,
                'skipped' => $skipped,
                'errors' => $errors,
            ]);

        } catch (\Throwable $e) {
            \DB::rollBack();
            \Log::error('Import Pengiriman Error: ' . $e->getMessage() . ' at line ' . $e->getLine());
            return response()->json([
                'message' => 'Gagal mengimport file Excel.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}

