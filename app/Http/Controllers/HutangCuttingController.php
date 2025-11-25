<?php

namespace App\Http\Controllers;

use App\Models\HutangCutting;
use App\Models\HistoryHutangCutting;
use Illuminate\Http\Request;

class HutangCuttingController extends Controller
{

    public function index()
    {
        $hutangs = HutangCutting::with(['tukangCutting:id,nama_tukang_cutting'])
            ->latest()
            ->get();  

        return response()->json([
            'success' => true,
            'message' => 'Daftar Hutang Cutting', 
            'data' => $hutangs
        ], 200);
    }
    public function tambahHutangCutting(Request $request)
    {
        $validated = $request->validate([
            'tukang_cutting_id' => 'required|exists:tukang_cutting,id',
            'jumlah_hutang' => 'required|numeric|min:0',
            'potongan_per_minggu' => 'nullable|numeric|min:0',
            'is_potongan_persen' => 'required|boolean',
            'persentase_potongan' => 'nullable|numeric|min:0|max:100',
            'bukti_transfer' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:20048',
        ]);

        // Validasi logika: hanya satu jenis potongan boleh diisi
        if ($validated['is_potongan_persen'] && is_null($validated['persentase_potongan'])) {
            return response()->json(['message' => 'Persentase potongan harus diisi'], 400);
        }

        if (!$validated['is_potongan_persen'] && is_null($validated['potongan_per_minggu'])) {
            return response()->json(['message' => 'Potongan tetap harus diisi'], 400);
        }

        // Simpan file jika ada
        if ($request->hasFile('bukti_transfer')) {
            $path = $request->file('bukti_transfer')->store('bukti_transfer', 'public');
            $validated['bukti_transfer'] = $path;
        } else {
            $validated['bukti_transfer'] = null;
        }

        // Simpan ke tabel hutang_cutting
        $hutang = HutangCutting::create([
            'tukang_cutting_id' => $validated['tukang_cutting_id'],
            'jumlah_hutang' => $validated['jumlah_hutang'],
            'status_pembayaran' => 'belum lunas',
            'tanggal_hutang' => now(),
            'potongan_per_minggu' => $validated['is_potongan_persen'] ? null : $validated['potongan_per_minggu'],
            'is_potongan_persen' => $validated['is_potongan_persen'],
            'persentase_potongan' => $validated['is_potongan_persen'] ? $validated['persentase_potongan'] : null,
            'bukti_transfer' => $validated['bukti_transfer'],
        ]);

        // Simpan ke tabel history_hutang_cutting
        HistoryHutangCutting::create([
            'hutang_cutting_id' => $hutang->id,
            'jenis_perubahan' => 'penambahan',
            'tanggal_perubahan' => now(),
            'jumlah_hutang' => $hutang->jumlah_hutang,
            'perubahan_hutang' => $hutang->jumlah_hutang,
            'bukti_transfer' => $validated['bukti_transfer'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Hutang Cutting berhasil ditambahkan!',
            'data' => $hutang
        ], 201);
    }

    public function tambahHutangLama(Request $request, $id)
    {
        $request->validate([
            'perubahan_hutang' => 'required|numeric|min:0', // Nilai hutang yang mau ditambahkan
            'bukti_transfer' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:20048',
        ]);

        // Ambil hutang yang sudah ada
        $hutang = HutangCutting::findOrFail($id);

            // Simpan file bukti transfer jika ada
        if ($request->hasFile('bukti_transfer')) {
            $path = $request->file('bukti_transfer')->store('bukti_transfer', 'public');
            $hutang->bukti_transfer = $path;
        }

        // Update jumlah hutang dengan nilai yang ditambahkan
        $hutang->jumlah_hutang += $request->perubahan_hutang;
        $hutang->save();

        // Simpan perubahan ke history_hutang
        HistoryHutangCutting::create([
            'hutang_cutting_id' => $hutang->id,
            'jenis_perubahan' => 'penambahan', // ENUM di database
            'tanggal_perubahan' => now(),
            'jumlah_hutang' => $hutang->jumlah_hutang, // Total hutang setelah ditambah
            'perubahan_hutang' => $request->perubahan_hutang, // Hutang yang baru ditambahkan
            'bukti_transfer' => $path ?? null, 
        ]);

        return response()->json(['message' => 'Hutang berhasil ditambahkan']);
    }

    public function getHistoryByHutangId(Request $request, $id)
{
    // Ambil parameter jenis_perubahan dari request
    $jenisPerubahan = $request->query('jenis_perubahan');

    // Query dasar
    $query = HistoryHutangCutting::where('hutang_cutting_id', $id)->orderBy('tanggal_perubahan', 'desc');

    // Jika ada filter jenis_perubahan, tambahkan ke query
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
