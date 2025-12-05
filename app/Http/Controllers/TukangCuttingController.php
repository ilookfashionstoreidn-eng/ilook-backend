<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TukangCutting;


class TukangCuttingController extends Controller
{
   public function index()
    {
        $data = TukangCutting::all();
        return response()->json($data);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_tukang_cutting' => 'required|string|max:255',
            'kontak' => 'nullable|string|max:100',
            'bank' => 'nullable|string|max:100',
            'no_rekening' => 'nullable|string|max:100',
            'alamat' => 'nullable|string|max:255',
        ]);

        $tukang = TukangCutting::create($validated);

        return response()->json([
            'message' => 'Tukang Cutting berhasil ditambahkan.',
            'data' => $tukang
        ], 201);
    }
    public function show(TukangCutting $tukangCutting)
    {
        return response()->json($tukangCutting);
    }

    public function update(Request $request, TukangCutting $tukangCutting)
    {
        $validated = $request->validate([
            'nama_tukang_cutting' => 'required|string|max:255',
        ]);

        $tukangCutting->update($validated);

        return response()->json([
            'message' => 'Tukang Cutting berhasil diupdate.',
            'data' => $tukangCutting
        ]);
    }
    public function destroy(TukangCutting $tukangCutting)
    {
        $tukangCutting->delete();

        return response()->json([
            'message' => 'Tukang Cutting berhasil dihapus.'
        ]);
    }
}