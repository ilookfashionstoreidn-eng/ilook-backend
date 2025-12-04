<?php

namespace App\Http\Controllers;

use App\Models\PembelianA;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PembelianAController extends Controller
{
  
  public function index()
{
    $pembelianAksesoris = PembelianA::with([
        'pembelianB',
        'aksesoris:id,nama_aksesoris'
    ])
    ->orderBy('created_at', 'desc')
    ->paginate(9);

    $pembelianAksesoris->map(function ($item) {
        $item->status_verifikasi = $item->pembelianB ? $item->pembelianB->status_verifikasi : 'belum';
        $item->pembelian_b_id = $item->pembelianB ? $item->pembelianB->id : null;
        $item->barcode_downloaded = $item->pembelianB ? $item->pembelianB->barcode_downloaded : 0; 
        return $item;
    });

    return response()->json($pembelianAksesoris);
}


       
    
    public function store(Request $request)
    {
        $request->validate([
            'user_id' =>'required|string',
            'aksesoris_id' =>'required|string',
            'jumlah' =>'required|integer',
            'harga_satuan' =>'required|numeric',
            'tanggal_pembelian' =>'required|date',
            'bukti_pembelian' => 'nullable|file|mimes:jpeg,png,pdf,jpg|max:5000'
        ]);

        $data = $request->all();

        $data['total_harga'] = $request->harga_satuan * $request->jumlah;

        if ($request->hasFile('bukti_pembelian')) {
            $data['bukti_pembelian'] = $request->file('bukti_pembelian')->store('bukti_pembelian', 'public');
        }
        $pembelianAksesoris = PembelianA::create($data);

        return response()->json($pembelianAksesoris, 201);
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
