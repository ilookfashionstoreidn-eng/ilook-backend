<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LogPembayaranHutang;
use App\Models\Hutang;

class LogPembayaranHutangController extends Controller
{
    
    public function index()
    {
        $logPembayaranHutang = LogPembayaranHutang::with('hutang')->get(); 
        return response()->json([
            'success' => true,
            'data' => $logPembayaranHutang,
        ]);
    } 
    public function show($id_hutang)
    {
        $logPembayaranHutang = LogPembayaranHutang::where('id_hutang', $id_hutang)->get();
    
        if ($logPembayaranHutang->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Log pembayaran hutang tidak ditemukan untuk ID hutang tersebut'
            ], 404);
        }
    
        return response()->json([
            'success' => true,
            'data' => $logPembayaranHutang
        ]);
    }
    
    
    public function createLogPembayaran(Request $request, $id_hutang)
    {
        $hutang = Hutang::find($id_hutang);
    
        if (!$hutang) {
            return response()->json([
                'success' => false,
                'message' => 'Hutang tidak ditemukan'
            ], 404);
        }
    
        $validated = $request->validate([
            'jumlah_dibayar' => 'required|numeric|min:1',
            'tanggal_bayar' => 'required|date',
            'catatan' => 'nullable|string',
        ]);

        $logPembayaranHutang = LogPembayaranHutang::create([
            'id_hutang' => $id_hutang,
            'jumlah_dibayar' => $validated['jumlah_dibayar'],
            'tanggal_bayar' => $validated['tanggal_bayar'],
            'catatan' => $validated['catatan'],
        ]);
    
        $totalPembayaranHutang = LogPembayaranHutang::where('id_hutang', $id_hutang)->sum('jumlah_dibayar');
    
        if ($totalPembayaranHutang >= $hutang->jumlah_hutang) {
            $hutang->status_pembayaran = 'lunas';
        } elseif ($totalPembayaranHutang > 0) {
            $hutang->status_pembayaran = 'dibayar sebagian';
        } else {
            $hutang->status_pembayaran = 'belum lunas';
        }
        $hutang->save();
    
        return response()->json([
            'success' => true,
            'message' => 'Log pembayaran hutang berhasil dibuat!',
            'data' => $logPembayaranHutang
        ], 201);
    }
    

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_hutang' => 'required|exists:hutang,id_hutang', 
            'jumlah_dibayar' => 'required|numeric|min:1',
            'tanggal_bayar' => 'required|date',
            'catatan' => 'nullable|string',
        ]);

    $logPembayaranHutang = LogPembayaranHutang::create([
        'id_hutang' => $validated['id_hutang'],
        'jumlah_dibayar' => $validated['jumlah_dibayar'],
        'tanggal_bayar' => $validated['tanggal_bayar'],
        'catatan' => $validated['catatan'],
    ]);

    $hutang= Hutang::find($validated['id_hutang']);

    $totalPembayaranHutang = LogPembayaranHutang::where('id_hutang', $validated['id_hutang'])->sum('jumlah_dibayar');

    if ($totalPembayaranHutang >= $hutang->jumlah_hutang ) {
        $hutang->status_pembayaran = 'lunas';
    } elseif ($totalPembayaranHutang > 0) {
        $hutang->status_pembayaran = 'dibayar sebagian';
    } else {
        $hutang->status_pembayaran = 'belum lunas';
    }
    $hutang->save();

    return response()->json([
        'success' => true,
        'message' => 'Log pembayaran hutang  berhasil disimpan!',
        'data' => $logPembayaranHutang
    ], 201);
}

    public function edit($id)
    {
        $logPembayaranHutang = LogPembayaranHutang::findOrFail($id); 
        return response()->json($logPembayaranHutang); 
    }

  
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'jumlah_dibayar' => 'required|numeric|min:1',
            'tanggal_bayar' => 'required|date',
            'catatan' => 'nullable|string',
        ]);

        $logPembayaranHutang = LogPembayaranHutang::findOrFail($id);
        $logPembayaranHutang->update($validated);

        $hutang = Hutang::find($logPembayaranHutang->id_hutang );
        $hutang ->status_pembayaran = $validated['status_pembayaran'];
        $hutang ->save();

        return response()->json([
            'success' => true,
            'message' => 'Log pembayaran hutang  berhasil diperbarui!',
            'data' => $logPembayaranHutang
        ]);
    }

   
    public function destroy($id)
    {
      
        $logPembayaranHutang = LogPembayaranHutang::findOrFail($id);
        $logPembayaranHutang->delete();

        return response()->json([
            'success' => true,
            'message' => 'Log pembayaran hutang  berhasil dihapus!'
        ]);
    }

   
    
}
