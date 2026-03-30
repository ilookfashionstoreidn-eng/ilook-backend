<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pabrik;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\QueryException;

class PabrikController extends Controller
{
    public function index()
    {
        return response()->json(Pabrik::all());
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_pabrik' => 'required|string',
            'lokasi' => 'nullable|string',
            'kontak' => 'nullable|string',
            'ktp' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5000'
        ]);

        $data = $request ->all();

        if ($request->hasFile('ktp')) {
            $data['ktp'] = $request->file('ktp')->store('ktp_pabrik','public');
        }

        $pabrik = Pabrik::create($data);

        return response()->json($pabrik, 201);
    }

    public function show($id)
    {
        $pabrik = Pabrik::findOrFail($id);
        return response()->json($pabrik);
    }

    public function update(Request $request, $id)
    {
        $pabrik = Pabrik::findOrFail($id);

        $request->validate([
            'nama_pabrik' => 'required|string',
            'lokasi' => 'nullable|string',
            'kontak' => 'nullable|string',
            'ktp' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5000'
        ]);

        $data = $request->all();

        if ($request->hasFile('ktp')) {
            if ($pabrik->ktp && Storage::disk('public')->exists($pabrik->ktp)) {
                Storage::disk('public')->delete($pabrik->ktp);
            }
            $data['ktp'] = $request->file('ktp')->store('ktp_pabrik', 'public');
        }

        $pabrik->update($data);

        return response()->json($pabrik);
    }

    public function destroy($id)
    {
        $pabrik = Pabrik::findOrFail($id);

        try {
            if ($pabrik->ktp && Storage::disk('public')->exists($pabrik->ktp)) {
                Storage::disk('public')->delete($pabrik->ktp);
            }

            $pabrik->delete();

            return response()->json([
                'message' => 'Data pabrik berhasil dihapus.'
            ]);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Data pabrik tidak bisa dihapus karena masih digunakan pada transaksi lain.'
            ], 409);
        }
    }
}
