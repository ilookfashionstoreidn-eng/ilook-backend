<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TukangPotongController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'kontak' => 'nullable|string|max:100',
            'bank' => 'nullable|string|max:100',
            'no_rekening' => 'nullable|string|max:100',
            'alamat' => 'nullable|string|max:255',
            'ktp' => 'nullable|image|mimes:jpeg,png,jpg|max:10240', 
            'jenis_jasa' => 'nullable|string|max:100',

        ]);

        // Simpan file KTP jika ada
        if ($request->hasFile('ktp')) {
            $validated['ktp'] = $request->file('ktp')->store('ktp_jasa', 'public');
            \Log::info('📸 KTP berhasil disimpan', ['path' => $validated['ktp']]);
        }

        // Simpan data tukang jasa
        $tukang = TukangPotong::create($validated);

        return response()->json([
            'message' => 'Tukang Jasa berhasil ditambahkan.',
            'data' => $tukang
        ], 201);
    }
}
