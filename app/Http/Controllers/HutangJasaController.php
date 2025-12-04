<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\HutangJasa;
use App\Models\HistoryHutangJasa;

class HutangJasaController extends Controller
{

    public function index()
    {
        $hutangs = HutangJasa::with(['tukangJasa:id,nama'])
            ->latest()
            ->get();  

        return response()->json([
            'success' => true,
            'message' => 'Daftar Hutang Cutting',
            'data' => $hutangs
        ], 200);
    }
    public function tambahHutangJasa(Request $request)
    {
        $validated = $request->validate([
            'tukang_jasa_id' => 'required|exists:tukang_jasa,id',
            'jumlah_hutang' => 'required|numeric|min:0',
            'potongan_per_minggu' => 'nullable|numeric|min:0',
            'is_potongan_persen' => 'required|boolean',
            'persentase_potongan' => 'nullable|numeric|min:0|max:100',
            'bukti_transfer' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:20048',
        ]);

        //
        if ($validated['is_potongan_persen'] && is_null($validated['persentase_potongan'])) {
            return response()->json(['message' => 'Persentase potongan harus diisi'], 400);
        }

        if (!$validated['is_potongan_persen'] && is_null($validated['potongan_per_minggu'])) {
            return response()->json(['message' => 'Potongan tetap harus diisi'], 400);
        }

        
        if ($request->hasFile('bukti_transfer')) {
            $path = $request->file('bukti_transfer')->store('bukti_transfer_jasa', 'public');
            $validated['bukti_transfer'] = $path;
        } else {
            $validated['bukti_transfer'] = null;
        }

        
        $hutang = HutangJasa::create([
            'tukang_jasa_id' => $validated['tukang_jasa_id'],
            'jumlah_hutang' => $validated['jumlah_hutang'],
            'status_pembayaran' => 'belum',
            'tanggal_hutang' => now(),
            'potongan_per_minggu' => $validated['is_potongan_persen'] ? null : $validated['potongan_per_minggu'],
            'is_potongan_persen' => $validated['is_potongan_persen'],
            'persentase_potongan' => $validated['is_potongan_persen'] ? $validated['persentase_potongan'] : null,
            'bukti_transfer' => $validated['bukti_transfer'],
        ]);

        HistoryHutangJasa::create([
            'hutang_jasa_id' => $hutang->id,
            'jenis_perubahan' => 'penambahan',
            'tanggal_perubahan' => now(),
            'jumlah_hutang' => $hutang->jumlah_hutang,
            'perubahan_hutang' => $hutang->jumlah_hutang,
            'bukti_transfer' => $validated['bukti_transfer'],
        ]);


        return response()->json([
            'success' => true,
            'message' => 'Hutang Jasa berhasil ditambahkan!',
            'data' => $hutang
        ], 201);
    }

    public function tambahHutangLama(Request $request, $id)
    {
        $request->validate([
            'perubahan_hutang' => 'required|numeric|min:0', 
            'bukti_transfer' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:20048',
        ]);

        $hutang = HutangJasa::findOrFail($id);

        if ($request->hasFile('bukti_transfer')) {
            $path = $request->file('bukti_transfer')->store('bukti_transfer', 'public');
            $hutang->bukti_transfer = $path;
        }

        $hutang->jumlah_hutang += $request->perubahan_hutang;
        $hutang->save();

        HistoryHutangJasa::create([
            'hutang_jasa_id' => $hutang->id,
            'jenis_perubahan' => 'penambahan', 
            'tanggal_perubahan' => now(),
            'jumlah_hutang' => $hutang->jumlah_hutang, 
            'perubahan_hutang' => $request->perubahan_hutang, 
            'bukti_transfer' => $path ?? null, 
        ]);

        return response()->json(['message' => 'Hutang berhasil ditambahkan']);
    }

    public function getHistoryByHutangId(Request $request, $id)
    {
        
        $jenisPerubahan = $request->query('jenis_perubahan');

        $query = HistoryHutangJasa ::where('hutang_jasa_id', $id)->orderBy('tanggal_perubahan', 'desc');
   
        if ($jenisPerubahan) {
            $query->where('jenis_perubahan', $jenisPerubahan);
        }

        $history = $query->get();

        if ($history->isEmpty()) {
            return response()->json(['message' => 'History hutang tidak ditemukan'], 404);
        }
        return response()->json($history);

    }
}