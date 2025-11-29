<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TukangJasa;

class TukangJasaController extends Controller
{
     public function index()
    {
        $data = TukangJasa::all();
        return response()->json($data);
    }
     public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'kontak' => 'nullable|string|max:100',
            'bank' => 'nullable|string|max:100',
            'no_rekening' => 'nullable|string|max:100',
            'alamat' => 'nullable|string|max:255',
          
            'jenis_jasa' => 'nullable|string|max:100',

        ]);
        $tukang = TukangJasa::create($validated);

        return response()->json([
            'message' => 'Tukang Jasa berhasil ditambahkan.',
            'data' => $tukang
        ], 201);
    }
}
