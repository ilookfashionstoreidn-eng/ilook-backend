<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CashboanJasa;
use App\Models\HistoryCashboanJasa;

class CashboanJasaController extends Controller
{
    public function index()
    {
        $cashboans = CashboanJasa::with(['tukangJasa:id,nama'])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar Cashboan Jasa',
            'data' => $cashboans
        ], 200);
    }


    public function tambahCashboanJasa(Request $request)
    {
        $validated = $request->validate([
            'tukang_jasa_id' => 'required|exists:tukang_jasa,id',
            'jumlah_cashboan' => 'required|numeric|min:0',
            'bukti_transfer' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:20048',
        ]);

    
        if ($request->hasFile('bukti_transfer')) {
            $path = $request->file('bukti_transfer')->store('bukti_transfer_cashboan_jasa', 'public');
        } else {
            $path = null;
        }

        // Simpan ke tabel cashboan_jasa
        $cashboan = \App\Models\CashboanJasa::create([
            'tukang_jasa_id' => $validated['tukang_jasa_id'],
            'jumlah_cashboan' => $validated['jumlah_cashboan'],
            'status_pembayaran' => 'belum lunas',
            'tanggal_cashboan' => now(),
            'bukti_transfer' => $path,
        ]);

        
        HistoryCashboanJasa::create([
            'cashboan_jasa_id' => $cashboan->id,
            'jenis_perubahan' => 'penambahan',
            'tanggal_perubahan' => now(),
            'jumlah_cashboan' => $cashboan->jumlah_cashboan,
            'perubahan_cashboan' => $cashboan->jumlah_cashboan,
            'bukti_transfer' => $path,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cashboan jasa berhasil ditambahkan!',
            'data' => $cashboan
        ], 201);
    }   

    public function tambahCashboanLama(Request $request, $id)
    {
        $request->validate([
            'perubahan_cashboan' => 'required|numeric|min:1', 
            'bukti_transfer' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:20048',
        ]);

        // Ambil cashboan yang sudah ada
        $cashboan = CashboanJasa::findOrFail($id);

        // Update jumlah cashboan dengan nilai yang ditambahkan
        $cashboan->jumlah_cashboan += $request->perubahan_cashboan;

        // Simpan file
        if ($request->hasFile('bukti_transfer')) {
            $path = $request->file('bukti_transfer')->store('bukti_transfer_cashboan', 'public'); // Ganti dengan folder 'bukti_transfer_cashboan'
            $validated['bukti_transfer'] = $path;
        } else {
            $validated['bukti_transfer'] = null;
        }

        $cashboan->save();

        // Simpan perubahan ke history cashboan
        HistoryCashboanJasa::create([
            'cashboan_jasa_id' => $cashboan->id,
            'jenis_perubahan' => 'penambahan', 
            'tanggal_perubahan' => now(),
            'jumlah_cashboan' => $cashboan->jumlah_cashboan, 
            'perubahan_cashboan' => $request->perubahan_cashboan, 
            'bukti_transfer' => $path ?? null, 
        ]);

        return response()->json(['message' => 'Cashboan berhasil ditambahkan']);
    }

    public function getHistoryByCashboanId(Request $request, $id)
    {
        
        $jenisPerubahan = $request->query('jenis_perubahan');

     
        $query = HistoryCashboanJasa::where('cashboan_jasa_id', $id)->orderBy('tanggal_perubahan', 'desc');

        
        if ($jenisPerubahan) {
            $query->where('jenis_perubahan', $jenisPerubahan);
        }

        $history = $query->get();

        if ($history->isEmpty()) {
            return response()->json(['message' => 'History cashboan tidak ditemukan'], 404);
        }

        return response()->json($history);
    }



}
