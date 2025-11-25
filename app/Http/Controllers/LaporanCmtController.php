<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LaporanCmt;
use App\Models\SpkCmt;

class LaporanCmtController extends Controller
{
    // Menampilkan semua laporan
    public function index()
    {
        $laporans = LaporanCmt::with('spk')->get();
        return response()->json($laporans, 200);
    }

    // Menyimpan laporan baru
    public function store(Request $request)
    {
        // Validasi input
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

        // Buat laporan baru
        $laporan = LaporanCmt::create($validated);

        return response()->json(['message' => 'Laporan berhasil dibuat!', 'data' => $laporan], 201);
    }

    // Menampilkan laporan berdasarkan ID
    public function show($id)
    {
        $laporan = LaporanCmt::with('spk')->findOrFail($id);
        return response()->json($laporan, 200);
    }

    // Memperbarui laporan
    public function update(Request $request, $id)
    {
        $laporan = LaporanCmt::findOrFail($id);

        // Validasi input
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

        // Update laporan
        $laporan->update($validated);

        return response()->json(['message' => 'Laporan berhasil diperbarui!', 'data' => $laporan], 200);
    }

    // Menghapus laporan
    public function destroy($id)
    {
        $laporan = LaporanCmt::findOrFail($id);
        $laporan->delete();

        return response()->json(['message' => 'Laporan berhasil dihapus!'], 200);
    }
}
