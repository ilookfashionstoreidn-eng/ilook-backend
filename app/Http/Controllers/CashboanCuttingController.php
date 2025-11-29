<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CashboanCutting;
use App\Models\HistoryCashboanCutting;
use App\Models\TukangCutting;
class CashboanCuttingController extends Controller
{
    public function index()
    {
        $cashboans = CashboanCutting::with(['tukangCutting:id,nama_tukang_cutting'])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar Cashboan Cutting',
            'data' => $cashboans
        ], 200);
    }


    public function tambahCashboanCutting(Request $request)
    {
        $validated = $request->validate([
            'tukang_cutting_id' => 'required|exists:tukang_cutting,id',
            'jumlah_cashboan' => 'required|numeric|min:0',
            'bukti_transfer' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:20048',
        ]);

      
        if ($request->hasFile('bukti_transfer')) {
            $path = $request->file('bukti_transfer')->store('bukti_transfer_cashboan_cutting', 'public');
        } else {
            $path = null;
        }

        $cashboan = CashboanCutting::create([
            'tukang_cutting_id' => $validated['tukang_cutting_id'],
            'jumlah_cashboan' => $validated['jumlah_cashboan'],
            'status_pembayaran' => 'belum lunas',
            'tanggal_cashboan' => now(),
            'bukti_transfer' => $path,
        ]);

        HistoryCashboanCutting::create([
            'cashboan_cutting_id' => $cashboan->id,
            'jenis_perubahan' => 'penambahan',
            'tanggal_perubahan' => now(),
            'jumlah_cashboan' => $cashboan->jumlah_cashboan,
            'perubahan_cashboan' => $cashboan->jumlah_cashboan,
            'bukti_transfer' => $path,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cashboan cutting berhasil ditambahkan!',
            'data' => $cashboan
        ], 201);
    }

  public function tambahCashboanLama(Request $request, $id)
    {
        $request->validate([
            'perubahan_cashboan' => 'required|numeric|min:1', 
            'bukti_transfer' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:20048',
        ]);

       
        $cashboan = CashboanCutting::findOrFail($id);

        
        $cashboan->jumlah_cashboan += $request->perubahan_cashboan;

        
        if ($request->hasFile('bukti_transfer')) {
            $path = $request->file('bukti_transfer')->store('bukti_transfer_cashboan', 'public'); // Ganti dengan folder 'bukti_transfer_cashboan'
            $validated['bukti_transfer'] = $path;
        } else {
            $validated['bukti_transfer'] = null;
        }

        $cashboan->save();

        
        HistoryCashboanCutting::create([
            'cashboan_cutting_id' => $cashboan->id,
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

        $query = HistoryCashboanCutting::where('cashboan_cutting_id', $id)->orderBy('tanggal_perubahan', 'desc');

        if ($jenisPerubahan) {
            $query->where('jenis_perubahan', $jenisPerubahan);
        }

        $history = $query->get();

        if ($history->isEmpty()) {
            return response()->json(['message' => 'History cashboantidak ditemukan'], 404);
        }

        return response()->json($history);
    }


}
