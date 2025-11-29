<?php

namespace App\Http\Controllers;

use App\Models\Cashboan;
use App\Models\LogPembayaranCashboan;
use Illuminate\Http\Request;

class LogPembayaranCashbonController extends Controller
{
    public function index()
    {
        $logPembayaranCashboan = LogPembayaranCashboan::with('cashboan')->get(); 
        return response()->json([
            'success' => true,
            'data' => $logPembayaranCashboan,
        ]);
    }

    public function show($id_cashboan)
    {
        $logPembayaranCashboan = LogPembayaranCashboan::where('id_cashboan', $id_cashboan)->get();
    
        if ($logPembayaranCashboan->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Log pembayaran cashboan tidak ditemukan untuk ID cashbon tersebut'
            ], 404);
        }
    
        return response()->json([
            'success' => true,
            'data' => $logPembayaranCashboan
        ]);
    }
    
    
    public function createLogPembayaran(Request $request, $id_cashboan)
    {
        $cashboan = Cashboan::find($id_cashboan);
    
        if (!$cashboan) {
            return response()->json([
                'success' => false,
                'message' => 'cashboan tidak ditemukan'
            ], 404);
        }
    
        $validated = $request->validate([
            'jumlah_dibayar' => 'required|numeric|min:1',
            'tanggal_bayar' => 'required|date',
            'catatan' => 'nullable|string',
        ]);
    
        $logPembayaranCashboan = LogPembayaranCashboan::create([
            'id_cashboan' => $id_cashboan,
            'jumlah_dibayar' => $validated['jumlah_dibayar'],
            'tanggal_bayar' => $validated['tanggal_bayar'],
            'catatan' => $validated['catatan'],
        ]);
    
        $totalPembayaranCashboan = LogPembayaranCashboan::where('id_cashboan', $id_cashboan)->sum('jumlah_dibayar');
    
        if ($totalPembayaranCashboan>= $cashboan->jumlah_cashboan) {
            $cashboan->status_pembayaran = 'lunas';
        } elseif ($totalPembayaranCashboan > 0) {
            $cashboan->status_pembayaran = 'dibayar sebagian';
        } else {
            $cashboan->status_pembayaran = 'belum lunas';
        }
        $cashboan->save();
    
        return response()->json([
            'success' => true,
            'message' => 'Log pembayaran cashbon berhasil dibuat!',
            'data' => $logPembayaranCashboan
        ], 201);
    }
    

    public function create($id_cashboan)
    {
        $cashboan = Cashboan::find($id_cashboan);
        if (!$cashboan) {
            return response()->json([
                'success' => false,
                'message' => 'Cashboan tidak ditemukan'
            ], 404);
        }
        
    }
   
    public function store(Request $request)
{
    $validated = $request->validate([
        'id_cashboan' => 'required|exists:cashboan,id_cashboan',  
        'jumlah_dibayar' => 'required|numeric|min:1',
        'tanggal_bayar' => 'required|date',
        'catatan' => 'nullable|string',
    ]);

    $logPembayaran = LogPembayaranCashboan::create([
        'id_cashboan' => $validated['id_cashboan'],
        'jumlah_dibayar' => $validated['jumlah_dibayar'],
        'tanggal_bayar' => $validated['tanggal_bayar'],
        'catatan' => $validated['catatan'],
    ]);

    $cashboan = Cashboan::find($validated['id_cashboan']);

    $totalPembayaran = LogPembayaranCashboan::where('id_cashboan', $validated['id_cashboan'])->sum('jumlah_dibayar');

    if ($totalPembayaran >= $cashboan->jumlah_cashboan) {
        $cashboan->status_pembayaran = 'lunas';
    } elseif ($totalPembayaran > 0) {
        $cashboan->status_pembayaran = 'dibayar sebagian';
    } else {
        $cashboan->status_pembayaran = 'belum lunas';
    }
    $cashboan->save();

    return response()->json([
        'success' => true,
        'message' => 'Log pembayaran cashboan berhasil disimpan!',
        'data' => $logPembayaran
    ], 201);
}

       
    public function edit($id)
    {
        $logPembayaran = LogPembayaranCashboan::findOrFail($id); 
        return response()->json($logPembayaran); 
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'jumlah_dibayar' => 'required|numeric|min:1',
            'tanggal_bayar' => 'required|date',
            'catatan' => 'nullable|string',
        ]);

        $logPembayaran = LogPembayaranCashboan::findOrFail($id);
        $logPembayaran->update($validated);

        $cashboan = Cashboan::find($logPembayaran->id_cashboan);
        $cashboan->status_pembayaran = $validated['status_pembayaran'];
        $cashboan->save();

        return response()->json([
            'success' => true,
            'message' => 'Log pembayaran cashboan berhasil diperbarui!',
            'data' => $logPembayaran
        ]);
    }
    public function destroy($id)
    {
        $logPembayaran = LogPembayaranCashboan::findOrFail($id);
        $logPembayaran->delete();

        return response()->json([
            'success' => true,
            'message' => 'Log pembayaran cashboan berhasil dihapus!'
        ]);
    }




}
