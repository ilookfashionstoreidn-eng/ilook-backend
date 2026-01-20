<?php

namespace App\Http\Controllers;

use App\Models\SpkBahan;
use Illuminate\Http\Request;

class SpkBahanController extends Controller
{
    public function index()
    {
        $data = SpkBahan::with(['pabrik', 'bahan'])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'pabrik_id' => 'required|integer',
            'bahan_id' => 'required|integer',
            'jumlah' => 'required|integer|min:1',
            'jenis_pembayaran' => 'required|string',
            'tanggal_pembayaran' => 'required|date',
            'status' => 'required|string',
        ]);

        $spkBahan = SpkBahan::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'SPK Bahan berhasil disimpan',
            'data' => $spkBahan
        ], 201);
    }
}
