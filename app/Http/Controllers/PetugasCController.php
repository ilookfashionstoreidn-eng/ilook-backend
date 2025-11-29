<?php

namespace App\Http\Controllers;

use App\Models\PetugasC;
use App\Models\Aksesoris;
use App\Models\DetailPesananAksesoris;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PetugasCController extends Controller
{
   
    public function index()
    {
        $pesanan = PetugasC::with([
            'detailPesanan.aksesoris:id,nama_aksesoris',
            'user:id,name',
            'penjahit:id_penjahit,nama_penjahit'
        ])
         ->orderBy('created_at', 'desc')
         ->paginate(10);
    
        return response()->json($pesanan);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'penjahit_id' => 'required|exists:penjahit_cmt,id_penjahit',
            'detail_pesanan' => 'required|array|min:1',
            'detail_pesanan.*.aksesoris_id' => 'required|exists:aksesoris,id',
            'detail_pesanan.*.jumlah_dipesan' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();

        try {
            $jumlahDipesanTotal = collect($validated['detail_pesanan'])->sum('jumlah_dipesan');

        
            $pesanan = PetugasC::create([
                'user_id' => $validated['user_id'],
                'penjahit_id' => $validated['penjahit_id'],
                'jumlah_dipesan' => $jumlahDipesanTotal,
            ]);

            
        foreach ($validated['detail_pesanan'] as $item) {
                $aksesoris = Aksesoris::find($item['aksesoris_id']);
                $totalHarga = $aksesoris ? $aksesoris->harga_jual * $item['jumlah_dipesan'] : 0;

                DetailPesananAksesoris::create([
                    'petugas_c_id' => $pesanan->id,
                    'aksesoris_id' => $item['aksesoris_id'],
                    'jumlah_dipesan' => $item['jumlah_dipesan'],
                    'total_harga' => $totalHarga
                ]);
            }

        
            $totalHarga = DetailPesananAksesoris::where('petugas_c_id', $pesanan->id)
                ->sum('total_harga');

        
            $pesanan->update([
                'total_harga' => $totalHarga
            ]);

            DB::commit();
            return response()->json($pesanan->load('detailPesanan'), 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Gagal menyimpan pesanan',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        //
    }

  
    public function update(Request $request, $id)
    {
        //
    }

    
    public function destroy($id)
    {
        //
    }
}
