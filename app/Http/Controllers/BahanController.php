<?php

namespace App\Http\Controllers;

use App\Models\Bahan;
use Illuminate\Http\Request;

class BahanController extends Controller
{
    public function index()
    {
        return response()->json(Bahan::orderBy('nama_bahan')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_bahan' => 'required|string|max:255|unique:bahan,nama_bahan',
            'deskripsi' => 'nullable|string',
            'harga' => 'required|numeric|min:0',
            'satuan' => 'required|string|max:50',
        ]);
        $bahan = Bahan::create($validated);
        return response()->json($bahan, 201);
    }

    public function show($id)
    {
        $bahan = Bahan::findOrFail($id);
        return response()->json($bahan);
    }

    public function update(Request $request, $id)
    {
        $bahan = Bahan::findOrFail($id);
        $validated = $request->validate([
            'nama_bahan' => 'required|string|max:255|unique:bahan,nama_bahan,' . $bahan->id,
            'deskripsi' => 'nullable|string',
            'harga' => 'required|numeric|min:0',
            'satuan' => 'required|string|max:50',
        ]);
        $bahan->update($validated);
        return response()->json($bahan);
    }

    public function destroy($id)
    {
        $bahan = Bahan::findOrFail($id);
        $bahan->delete();
        return response()->json(['message' => 'Bahan dihapus']);
    }
}
