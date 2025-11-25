<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Gudang;

class GudangController extends Controller
{
     public function index()
    {
        $gudang = Gudang::all();
        return response()->json($gudang);
    }
     public function store(Request $request)
    {
        $request->validate([
            'nama_gudang' => 'required|string|max:255',
            'alamat' => 'nullable|string',
            'pic' => 'nullable|string|max:255',
        ]);

        $gudang = Gudang::create($request->all());
        return response()->json($gudang, 201);
    }
     public function show($id)
    {
        $gudang = Gudang::findOrFail($id);
        return response()->json($gudang);
    }

    public function update(Request $request, $id)
    {
        $gudang = Gudang::findOrFail($id);

        $request->validate([
            'nama_gudang' => 'required|string|max:255',
            'alamat' => 'nullable|string',
            'pic' => 'nullable|string|max:255',
        ]);

        $gudang->update($request->all());
        return response()->json($gudang);
    }

    public function destroy($id)
    {
        $gudang = Gudang::findOrFail($id);
        $gudang->delete();

        return response()->json(['message' => 'Data gudang berhasil dihapus']);
    }
}
