<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Warna;
use App\Models\SpkCmt;

class WarnaController extends Controller
{
    // Tampilkan semua warna dalam SPK tertentu
    public function index($spkcmt)
    {
        $spk = SpkCmt::with('warna')->find($spkcmt);
        if (!$spk) {
            return response()->json(['message' => 'SPK tidak ditemukan'], 404);
        }
        return response()->json($spk->warna);
    }

    // Form untuk tambah warna (opsional, untuk frontend)
    public function create($spkcmt)
    {
        return response()->json(['message' => 'Form untuk menambahkan warna ke SPK']);
    }

    // Simpan warna baru dalam SPK tertentu
    public function store(Request $request, $spkcmt)
    {
        $validated = $request->validate([
            'nama_warna' => 'required|string|max:50',
            'qty' => 'required|integer|min:1',
        ]);

        $spk = SpkCmt::find($spkcmt);
        if (!$spk) {
            return response()->json(['message' => 'SPK tidak ditemukan'], 404);
        }

        // Buat warna baru dan hubungkan dengan SPK
        $warna = $spk->warna()->create($validated);

        return response()->json(['message' => 'Warna berhasil ditambahkan', 'data' => $warna], 201);
    }

    // Tampilkan warna tertentu (shallow route)
    public function show($warna)
    {
        $warna = Warna::find($warna);
        if (!$warna) {
            return response()->json(['message' => 'Warna tidak ditemukan'], 404);
        }
        return response()->json($warna);
    }

    // Form edit warna (opsional, untuk frontend)
    public function edit($spkcmt, $warna)
    {
        return response()->json(['message' => 'Form edit warna']);
    }

    // Update warna tertentu
    public function update(Request $request, $warna)
    {
        $validated = $request->validate([
            'nama_warna' => 'required|string|max:50',
            'qty' => 'required|integer|min:1',
        ]);

        $warna = Warna::find($warna);
        if (!$warna) {
            return response()->json(['message' => 'Warna tidak ditemukan'], 404);
        }

        $warna->update($validated);
        return response()->json(['message' => 'Warna berhasil diperbarui']);
    }

    // Hapus warna tertentu
    public function destroy($warna)
    {
        $warna = Warna::find($warna);
        if (!$warna) {
            return response()->json(['message' => 'Warna tidak ditemukan'], 404);
        }

        $warna->delete();
        return response()->json(['message' => 'Warna berhasil dihapus']);
    }
}
