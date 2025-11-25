<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pabrik;

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
}
