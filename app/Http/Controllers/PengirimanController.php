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
            }

            return [
                'id_pengiriman' => $pengiriman->id_pengiriman,
                'id_spk' => $pengiriman->id_spk,
                'tanggal_pengiriman' => $pengiriman->tanggal_pengiriman,
                'total_barang_dikirim' => $pengiriman->total_barang_dikirim,
                'sisa_barang' => $pengiriman->sisa_barang,
                'status_verifikasi' => $pengiriman->status_verifikasi,
                'total_bayar' => $pengiriman->total_bayar,
                'claim' => $pengiriman->claim,
                'refund_claim' => $pengiriman->refund_claim,
                'status_claim' => $pengiriman->status_claim,

                // relasi CMT
                'nama_penjahit' => $pengiriman->spk->penjahit->nama_penjahit ?? null,
                'id_penjahit' => $pengiriman->spk->penjahit->id_penjahit ?? null,
                'nama_produk' => $namaProduk,

                // detail warna pengiriman ini
                'warna' => $pengiriman->warna->map(fn($w) => [
                    'warna' => $w->warna,
                    'jumlah_dikirim' => $w->jumlah_dikirim,
                    'sisa_barang_per_warna' => $w->sisa_barang_per_warna,
                ]),

                // agregat sisa warna SPK
                'sisa_barang_per_warna' => $sisaBarangPerWarna,
            ];
        });

        return response()->json($pengirimans, 200);
    }



    public function storePetugasBawah(Request $request)
    {
        $validated = $request->validate([
            'id_spk' => 'required|exists:spk_cmt,id_spk',
            'tanggal_pengiriman' => 'required|date',
            'total_barang_dikirim' => 'required|integer|min:1',
            'foto_nota' => 'required|file|mimes:jpeg,png,jpg,pdf|max:5120',
        ]);

        // Upload foto nota jika ada
        $fotoNotaPath = null;
        if ($request->hasFile('foto_nota')) {
            $fotoNotaPath = $request->file('foto_nota')->store('nota_pengiriman', 'public');
        }

        $spk = SpkCmt::findOrFail($validated['id_spk']);

        // Validasi deadline: tanggal pengiriman tidak boleh melewati deadline SPK CMT
        if ($spk->deadline) {
            $tanggalPengiriman = Carbon::parse($validated['tanggal_pengiriman']);
            $deadline = Carbon::parse($spk->deadline);

            if ($tanggalPengiriman->gt($deadline)) {
                return response()->json([
                    'error' => 'Tanggal pengiriman tidak boleh melewati deadline SPK. Deadline: ' . $deadline->format('d/m/Y')
                ], 400);
            }
        }

        $warnaSpk = SpkCmtWarna::where('spk_cmt_id', $validated['id_spk'])->get();


        // Hitung total barang yang tersisa dalam SPK sebelum pengiriman terbaru
        $totalBarangSisaSebelumnya = $warnaSpk->sum('qty') - Pengiriman::where('id_spk', $validated['id_spk'])->sum('total_barang_dikirim');

        if ($validated['total_barang_dikirim'] > $totalBarangSisaSebelumnya) {
            return response()->json(['error' => 'Jumlah barang dikirim melebihi sisa produk yang tersedia.'], 400);
        }

        // Hitung sisa barang setelah pengiriman terbaru
        $sisaBarangSetelahPengiriman = $totalBarangSisaSebelumnya - $validated['total_barang_dikirim'];

        // Simpan data pengiriman dengan status 'pending' (menunggu input petugas atas)
        $pengiriman = Pengiriman::create([
            'id_spk' => $validated['id_spk'],
            'tanggal_pengiriman' => $validated['tanggal_pengiriman'],
            'total_barang_dikirim' => $validated['total_barang_dikirim'],
            'foto_nota' => $fotoNotaPath,
            'status_verifikasi' => 'pending', // Status default
            'sisa_barang' => $sisaBarangSetelahPengiriman, // Tambahkan sisa barang terbaru
            'status_claim' => 'belum_dibayar', // Status claim default
        ]);

        return response()->json([
            'message' => 'Pengiriman berhasil disimpan. Menunggu input dari petugas atas.',
            'data' => $pengiriman,
            'sisa_barang' => $sisaBarangSetelahPengiriman, // Kirim sisa barang terbaru dalam response
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

            if (!$warnaSpkItem) {
                return response()->json([
                    'error' => "Warna {$namaWarna} tidak terdaftar di SPK CMT."
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
}
