<?php

namespace App\Http\Controllers;

use App\Models\PetugasDVerif;
use App\Models\PetugasC;
use App\Models\PembelianA;
use App\Models\StokAksesoris;
use App\Models\DetailPesananAksesoris;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PetugasDVerifController extends Controller
{
    
    public function index()
    {
        $verifikasiList = PetugasDVerif::with([
            'user:id,name',
            'petugasC:id,penjahit_id,user_id',
            'petugasC.penjahit:id_penjahit,nama_penjahit'
        ])
        ->get()
        ->map(function ($item) {
            $item->barcodes = $item->barcode;
            $item->stok_aksesoris = StokAksesoris::whereIn('barcode', $item->barcode)->get(['barcode', 'aksesoris_id', 'status']);
            return $item;
        });
    
        return response()->json($verifikasiList);
    }
    
  public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'petugas_c_id' => 'required|exists:petugas_c,id',
            'barcode' => 'required|array|min:1',
            'barcode.*' => 'required|string|distinct',
            'bukti_nota' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048'
        ]);

        DB::beginTransaction();
        try {
            $barcodes = $validated['barcode'];

            $petugasC = PetugasC::with(['detailPesanan.aksesoris'])->findOrFail($validated['petugas_c_id']);

            $jumlahDipesan = $petugasC->detailPesanan->sum('jumlah_dipesan');

            if (count($barcodes) !== $jumlahDipesan) {
                return response()->json(['error' => 'Jumlah barcode tidak sesuai dengan jumlah yang dipesan'], 422);
            }

            $stokValid = StokAksesoris::whereIn('barcode', $barcodes)
                ->where('status', 'tersedia')
                ->get();

            if ($stokValid->count() !== count($barcodes)) {
                return response()->json(['error' => 'Beberapa barcode tidak ditemukan di stok aksesoris atau sudah terpakai'], 422);
            }

            $barcodeCountPerAksesoris = [];
            foreach ($stokValid as $stok) {
                if (!isset($barcodeCountPerAksesoris[$stok->aksesoris_id])) {
                    $barcodeCountPerAksesoris[$stok->aksesoris_id] = 0;
                }
                $barcodeCountPerAksesoris[$stok->aksesoris_id]++;
            }

            foreach ($petugasC->detailPesanan as $detail) {
                $aksId = $detail->aksesoris_id;
                $jumlahSeharusnya = $detail->jumlah_dipesan;
                $jumlahBarcodeMasuk = $barcodeCountPerAksesoris[$aksId] ?? 0;

                if ($jumlahBarcodeMasuk !== $jumlahSeharusnya) {
                    return response()->json([
                        'error' => "Jumlah barcode untuk aksesoris {$detail->aksesoris->nama_aksesoris} tidak sesuai",
                        'detail' => [
                            'dipesan' => $jumlahSeharusnya,
                            'barcode_masuk' => $jumlahBarcodeMasuk
                        ]
                    ], 422);
                }
            } 
            $verifikasi = PetugasDVerif::create([
                'user_id' => $validated['user_id'],
                'petugas_c_id' => $validated['petugas_c_id'],
                'barcode' => $barcodes,

            ]);

            if ($request->hasFile('bukti_nota')) {
                $path = $request->file('bukti_nota')->store('bukti_nota', 'public');
                $verifikasi->bukti_nota = $path;
                $verifikasi->save();
            }

            foreach ($petugasC->detailPesanan as $detail) {
                $detail->petugas_d_verif_id = $verifikasi->id;

                $pembelianTerbaru = PembelianA::where('aksesoris_id', $detail->aksesoris_id)
                    ->orderBy('created_at', 'desc')
                    ->first();

                $hargaSatuan = $pembelianTerbaru ? $pembelianTerbaru->harga_satuan : 0;
                $detail->total_harga = $hargaSatuan * $detail->jumlah_dipesan;
                $detail->save();
            }

           

            StokAksesoris::whereIn('barcode', $barcodes)->update(['status' => 'terpakai']);

          
            $petugasC->status = 'selesai';
            $petugasC->save();

            DB::commit();
            return response()->json($verifikasi, 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Gagal menyimpan verifikasi',
                'message' => $e->getMessage()
            ], 500);
        }
    }



    public function getDetailPesananAksesoris(Request $request)
    {
        $detailPesanan = DetailPesananAksesoris::with([
            'aksesoris:id,nama_aksesoris',
            'petugasC.penjahit:id_penjahit,nama_penjahit',
            'petugasC.user:id,name',
            'petugasC.petugasDVerif.user:id,name' // ini tambahan penting!
        ])->get();
        

        if ($detailPesanan->isEmpty()) {
        return response()->json(['message' => 'Tidak ada detail pesanan aksesoris.'], 404);
    }
    $data = $detailPesanan->map(function ($item) {
        return [
            'id' => $item->id,
            'petugas_c_id' => $item->petugas_c_id,
            'petugas_d_verif' => $item->petugas_d_verif_id,
            'aksesoris_id' => $item->aksesoris_id,
            'jumlah_dipesan' => $item->jumlah_dipesan,
            'total_harga' => $item->total_harga,
            'sudah_dibayar' => $item->sudah_dibayar,
            'created_at' => $item->created_at->toIso8601String(),
            'updated_at' => $item->updated_at->toIso8601String(),
            'id_pendapatan' => $item->id_pendapatan,
            'petugas_d_verif_id' => $item->petugas_d_verif_id,
            'aksesoris' => [
                'id' => $item->aksesoris->id,
                'nama_aksesoris' => $item->aksesoris->nama_aksesoris,
                'jumlah_stok' => $item->aksesoris->jumlah_stok
            ],
            'penjahit' => $item->petugasC ? [
                'penjahit_id' => $item->petugasC->penjahit->id_penjahit,
                'nama_penjahit' => $item->petugasC->penjahit->nama_penjahit
            ] : null,
            'petugas_c_user' => $item->petugasC ? [
                'user_id' => $item->petugasC->user->id,
                'name' => $item->petugasC->user->name
            ] : null,
            'petugas_d_verif_user' => $item->petugasC && $item->petugasC->petugasDVerif ? [
                'user_id' => $item->petugasC->petugasDVerif->user->id,
                'name' => $item->petugasC->petugasDVerif->user->name
            ] : null,
        ];
    });

        return response()->json($data);
    }



}