<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LaporanCmt;
use App\Models\SpkCmt;

class LaporanCmtController extends Controller
{    
    public function index()
    {
        $laporans = LaporanCmt::with('spk')->get();
        return response()->json($laporans, 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_spk' => 'required|exists:spk_cmt,id_spk',
            'tgl_pengiriman' => 'required|date',
            'jumlah_dikirim' => 'required|integer|min:1',
            'barang_rusak' => 'nullable|integer|min:0',
            'barang_hilang' => 'nullable|integer|min:0',
            'upah_per_barang' => 'required|numeric|min:0',
            'total_upah' => 'required|numeric|min:0',
            'potongan' => 'nullable|numeric|min:0',
            'cashbon' => 'nullable|numeric|min:0',
            'status_pembayaran' => 'required|in:Paid,Unpaid',
            'keterangan' => 'nullable|string',
        ]);

        $laporan = LaporanCmt::create($validated);

        return response()->json(['message' => 'Laporan berhasil dibuat!', 'data' => $laporan], 201);
    }

    public function show($id)
    {
        $laporan = LaporanCmt::with('spk')->findOrFail($id);
        return response()->json($laporan, 200);
    }

    public function update(Request $request, $id)
    {
        $laporan = LaporanCmt::findOrFail($id);

        $validated = $request->validate([
            'id_spk' => 'required|exists:spk_cmt,id_spk',
            'tgl_pengiriman' => 'required|date',
            'jumlah_dikirim' => 'required|integer|min:1',
            'barang_rusak' => 'nullable|integer|min:0',
            'barang_hilang' => 'nullable|integer|min:0',
            'upah_per_barang' => 'required|numeric|min:0',
            'total_upah' => 'required|numeric|min:0',
            'potongan' => 'nullable|numeric|min:0',
            'cashbon' => 'nullable|numeric|min:0',
            'status_pembayaran' => 'required|in:Paid,Unpaid',
            'keterangan' => 'nullable|string',
        ]);

        $laporan->update($validated);

        return response()->json(['message' => 'Laporan berhasil diperbarui!', 'data' => $laporan], 200);
    }

    public function destroy($id)
    {
        $laporan = LaporanCmt::findOrFail($id);
        $laporan->delete();

        return response()->json(['message' => 'Laporan berhasil dihapus!'], 200);
    }
}
