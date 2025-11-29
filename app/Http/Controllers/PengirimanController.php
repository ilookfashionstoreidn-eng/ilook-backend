<?php

namespace App\Http\Controllers;

use App\Models\Pengiriman;
use App\Models\PengirimanWarna;
use App\Models\Warna; 
use App\Models\SpkCmt;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PengirimanController extends Controller
{
    public function index(Request $request)
    {
        $idPenjahit = $request->query('id_penjahit');
        $sortBy = $request->query('sortBy', 'created_at'); 
        $sortOrder = $request->query('sortOrder', 'desc');
        $allData = $request->query('allData');
        $namaProduk = $request->query('nama_produk');
        $statusVerifikasi = $request->query('status_verifikasi');

        $query = Pengiriman::with([
        'warna',
        'spk' => function ($q) {
            $q->select('id_spk', 'tgl_spk', 'jumlah_produk', 'total_harga', 'deadline', 'id_penjahit', 'id_produk');
        },
        'spk.penjahit' => function ($q) {
            $q->select('id_penjahit', 'nama_penjahit');
        },
        'spk.produk' => function ($q) {
            $q->select('id', 'nama_produk', 'kategori_produk');
        }
    ]);

        $query->when($idPenjahit, function ($q) use ($idPenjahit) {
            $q->whereHas('spk.penjahit', function ($subQuery) use ($idPenjahit) {
                $subQuery->where('id_penjahit', $idPenjahit);
            });
        });
 
        $query->when($namaProduk, function ($q) use ($namaProduk) {
            $q->whereHas('spk.produk', function ($subQuery) use ($namaProduk) {
                $subQuery->where('nama_produk', 'like', "%$namaProduk%");
            });
        });
        
        $query->when($statusVerifikasi, function ($q) use ($statusVerifikasi) {
            $q->where('status_verifikasi', $statusVerifikasi);
        });
        

        $query->orderBy($sortBy, $sortOrder);
        
        $pengiriman = $allData == 'true' ? $query->get() : $query->paginate(10);
   
        $pengiriman->transform(function ($pengiriman) {
            $sisaBarangPerWarna = [];
        
            foreach ($pengiriman->warna as $warna) {
                $warnaData = Warna::where('id_spk', $pengiriman->id_spk)
                    ->where('nama_warna', $warna->warna)
                    ->first();
        
                if ($warnaData) {
                    $totalSudahDikirim = PengirimanWarna::whereHas('pengiriman', function ($query) use ($pengiriman) {
                        $query->where('id_spk', $pengiriman->id_spk);
                    })
                    ->where('warna', $warna->warna)
                    ->sum('jumlah_dikirim');
        
                    $sisaBarangPerWarna[$warna->warna] = $warnaData->qty - $totalSudahDikirim;
                }
            }
        
            return [
                'id_pengiriman' => $pengiriman->id_pengiriman,
                'id_spk' => $pengiriman->id_spk,
                'tanggal_pengiriman' => $pengiriman->tanggal_pengiriman,
                'total_barang_dikirim' => $pengiriman->total_barang_dikirim,
                'sisa_barang' => $pengiriman->sisa_barang,
                'total_bayar' => $pengiriman->total_bayar,
                'status_verifikasi' => $pengiriman->status_verifikasi,
                'claim' => $pengiriman->claim,
                'refund_claim' => $pengiriman->refund_claim,
                'sisa_barang_per_warna' => $sisaBarangPerWarna,
                'nama_produk' => $pengiriman->spk->produk->nama_produk ?? null,
                'kategori_produk' => $pengiriman->spk->produk->kategori_produk ?? null,
                'nama_penjahit' => $pengiriman->spk->penjahit->nama_penjahit ?? null,
                'id_penjahit' => $pengiriman->spk->penjahit->id_penjahit ?? null,
                'status_verifikasi' =>  $pengiriman->status_verifikasi,
                'warna' => $pengiriman->warna->map(function ($warna) {
                    return [
                        'warna' => $warna->warna,
                        'jumlah_dikirim' => $warna->jumlah_dikirim
                    ];
                })
            ];
        });
        
    
        return response()->json($pengiriman, 200);

    }
    
    public function storePetugasBawah(Request $request)
    {
        $validated = $request->validate([
            'id_spk' => 'required|exists:spk_cmt,id_spk',
            'tanggal_pengiriman' => 'required|date',
            'total_barang_dikirim' => 'required|integer|min:1',
            'foto_nota' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        // Upload foto nota jika ada
        $fotoNotaPath = null;
        if ($request->hasFile('foto_nota')) {
            $fotoNotaPath = $request->file('foto_nota')->store('nota_pengiriman', 'public');
        }

            $spk = SpkCmt::findOrFail($validated['id_spk']);
        $warnaSpk = Warna::where('id_spk', $validated['id_spk'])->get();

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
            'warna' => 'required|array',
            'warna.*.warna' => 'required|string|max:50',
            'warna.*.jumlah_dikirim' => 'required|integer|min:0',
        ]);

        $pengiriman = Pengiriman::findOrFail($id_pengiriman);
        $spk = SpkCmt::findOrFail($pengiriman->id_spk);
        $warnaSpk = Warna::where('id_spk', $pengiriman->id_spk)->get();

        $pengirimanSebelumnya = PengirimanWarna::whereHas('pengiriman', function ($query) use ($pengiriman) {
            $query->where('id_spk', $pengiriman->id_spk);
        })->where('id_pengiriman', '!=', $pengiriman->id_pengiriman)->get();

        $sudahDikirimPerWarna = $pengirimanSebelumnya->groupBy('warna')->map(function ($group) {
            return $group->sum('jumlah_dikirim');
        });

        $totalDikirimPetugasAtas = collect($validated['warna'])->sum('jumlah_dikirim');


        if ($totalDikirimPetugasAtas !== $pengiriman->total_barang_dikirim) {
            return response()->json(['error' => 'Jumlah yang dikirim tidak sesuai.'], 400);
        }
        
        $statusVerifikasi = $totalDikirimPetugasAtas === $pengiriman->total_barang_dikirim ? 'valid' : 'invalid';
        $sisaBarangPerWarna = [];
        foreach ($validated['warna'] as $warnaData) {
            $warnaSpkItem = $warnaData['warna'];
            $jumlahDikirim = $warnaData['jumlah_dikirim'];
        
            $warnaDataSpk = $warnaSpk->where('nama_warna', $warnaSpkItem)->first();
            if (!$warnaDataSpk) {
                return response()->json(['error' => "Warna $warnaSpkItem tidak ditemukan untuk SPK ini"], 400);
            }
        
            $stokAwal = $warnaDataSpk->qty;
            $totalSudahDikirim = $sudahDikirimPerWarna[$warnaSpkItem] ?? 0;
            
            $totalSetelahDikirim = $totalSudahDikirim + $jumlahDikirim;

            if ($totalSetelahDikirim > $stokAwal) {
                return response()->json([
                    'error' => "Jumlah total dikirim untuk warna $warnaSpkItem melebihi stok SPK. Maksimal: $stokAwal, Sudah dikirim: $totalSudahDikirim, Sekarang: $jumlahDikirim"
                ], 400);
            }
            
            $sisaBarang = $stokAwal - $totalSetelahDikirim;
            
        
            PengirimanWarna::updateOrCreate(
                ['id_pengiriman' => $pengiriman->id_pengiriman, 'warna' => $warnaSpkItem],
                ['jumlah_dikirim' => $jumlahDikirim, 'sisa_barang_per_warna' => $sisaBarang]
            );
            \Log::info("Data Pengiriman Warna Disimpan: ", ['id_pengiriman' => $pengiriman->id_pengiriman, 'warna' => $warnaSpkItem, 'sisa_barang_per_warna' => $sisaBarang]);

            $sisaBarangPerWarna[$warnaSpkItem] = $sisaBarang;
        }
   
        $totalBayar = $totalDikirimPetugasAtas * $spk->harga_per_jasa;

        $claim = array_sum($sisaBarangPerWarna) > 0 ? array_sum($sisaBarangPerWarna) * $spk->harga_per_barang : 0;

        $pengirimanSebelumnya = Pengiriman::where('id_spk', $pengiriman->id_spk)
        ->where('id_pengiriman', '<', $pengiriman->id_pengiriman) 
        ->orderBy('id_pengiriman', 'desc') 
        ->first(); 

        $refundClaim = $pengirimanSebelumnya ? $pengirimanSebelumnya->claim : 0;

        $semuaWarnaSudahDikirim = $warnaSpk->every(function ($warnaItem) use ($sudahDikirimPerWarna, $validated) {
            $warnaNama = $warnaItem->nama_warna;
            $sudahDikirim = $sudahDikirimPerWarna[$warnaNama] ?? 0;

            $dikirimSekarang = collect($validated['warna'])
                ->firstWhere('warna', $warnaNama)['jumlah_dikirim'] ?? 0;

            return ($sudahDikirim + $dikirimSekarang) >= $warnaItem->qty;
        });

        if ($semuaWarnaSudahDikirim) {
            $spk->setStatus('Completed');
            \Log::info("SPK ID {$spk->id_spk} diupdate menjadi COMPLETED karena semua barang sudah dikirim.");
        }
        $updated = $pengiriman->update([
            'status_verifikasi' => $statusVerifikasi,
            'sisa_barang' => array_sum($sisaBarangPerWarna),
            'total_bayar' => $totalBayar,
            'claim' => $claim,
            'refund_claim' => $refundClaim,
        ]);
        \Log::info('Update Pengiriman:', ['updated' => $updated, 'data' => $pengiriman->toArray()]);

        return response()->json([
            'message' => 'Data pengiriman telah diperbarui oleh petugas atas.',
            'data' => array_merge($pengiriman->toArray(), ['sisa_barang_per_warna' => $sisaBarangPerWarna]),
            'sisa_barang_per_warna' => $sisaBarangPerWarna,
            'total_bayar' => $totalBayar,
            'claim' => $claim,
            'refund_claim' => $refundClaim
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
            \Storage::disk('public')->delete($pengiriman->foto_nota);
        }
        $pengiriman->delete();
        return response()->json(['message' => 'Data pengiriman berhasil dihapus.'], 200);
    }




}