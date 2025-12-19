<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TukangPola;

class TukangPolaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $data = TukangPola::orderBy('created_at', 'desc')->get();
        return response()->json($data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
        ]);

        $tukangPola = TukangPola::create($validated);

        return response()->json([
            'message' => 'Tukang Pola berhasil ditambahkan.',
            'data' => $tukangPola
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\TukangPola  $tukangPola
     * @return \Illuminate\Http\Response
     */
    public function show(TukangPola $tukangPola)
    {
        return response()->json($tukangPola);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\TukangPola  $tukangPola
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, TukangPola $tukangPola)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
        ]);

        $tukangPola->update($validated);

        return response()->json([
            'message' => 'Tukang Pola berhasil diupdate.',
            'data' => $tukangPola
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\TukangPola  $tukangPola
     * @return \Illuminate\Http\Response
     */
    public function destroy(TukangPola $tukangPola)
    {
        $tukangPola->delete();

        return response()->json([
            'message' => 'Tukang Pola berhasil dihapus.'
        ]);
    }
}
