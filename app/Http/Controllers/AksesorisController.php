<?php

namespace App\Http\Controllers;


use App\Models\Aksesoris;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AksesorisController extends Controller
{
    public function index()
    {
        $aksesoris = Aksesoris::all();
        return response()->json($aksesoris);
    }
    

   
    public function store(Request $request)
    {
        $request->validate([
        'nama_aksesoris' => 'required|string',
        'jenis_aksesoris' => 'required|string|max:255',
        'satuan' => 'required|in:' . implode(',', array_keys(Aksesoris::getSatuanAksesorisOptions())),
        'harga_jual' => 'nullable|numeric|min:0',
        'foto_aksesoris' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048'
        ]);

        $data = $request->all();

        if ($request->hasFile('foto_aksesoris')) {
            $path = $request->file('foto_aksesoris')->store('foto_aksesoris', 'public');
            $data['foto_aksesoris'] = $path; // simpan path file ke data
        }

        $aksesoris = Aksesoris::create($data);

        return response()->json($aksesoris, 201);
    }

    public function getOptions()
    {
        
        Log::info('Mengambil opsi aksesoris: ', [
            'jenis_aksesoris' => Aksesoris::getJenisAksesorisOptions(),
            'satuan' => Aksesoris::getSatuanAksesorisOptions(),
        ]);
    
        return response()->json([
            'jenis_aksesoris' => Aksesoris::getJenisAksesorisOptions(),
            'satuan' => Aksesoris::getSatuanAksesorisOptions(),
        ]);
    }
    
    public function show($id)
    {
        //
    }

   
   public function update(Request $request, $id)
    {
        $aksesoris = Aksesoris::find($id);

        if (!$aksesoris) {
            return response()->json([
                'error' => 'Aksesoris tidak ditemukan'
            ], 404);
        }

        $request->validate([
            'nama_aksesoris' => 'sometimes|required|string',
            'jenis_aksesoris' => 'sometimes|required|string|max:255',
            'satuan' => 'sometimes|required|in:' . implode(',', array_keys(Aksesoris::getSatuanAksesorisOptions())),
            'harga_jual' => 'nullable|numeric|min:0',
            'foto_aksesoris' => 'sometimes|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        
        $aksesoris->update($request->except('foto_aksesoris'));

       
        if ($request->hasFile('foto_aksesoris')) {

           
            if ($aksesoris->foto_aksesoris && \Storage::exists('public/'.$aksesoris->foto_aksesoris)) {
                \Storage::delete('public/'.$aksesoris->foto_aksesoris);
            }

            
            $path = $request->file('foto_aksesoris')->store('aksesoris', 'public');
            $aksesoris->foto_aksesoris = $path;
            $aksesoris->save();
        }

        return response()->json($aksesoris);
    }


   
    public function destroy($id)
    {
        
    }
}
