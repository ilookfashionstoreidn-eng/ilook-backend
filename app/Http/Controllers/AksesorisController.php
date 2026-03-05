<?php

namespace App\Http\Controllers;


use App\Models\Aksesoris;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AksesorisController extends Controller
{
    public function index(Request $request)
    {
       
        $getAll = $request->get('all');
        $perPage = $request->get('per_page');

        if ($getAll === 'true' || $getAll === '1' || $getAll === true || ($perPage && intval($perPage) >= 1000)) {
            $aksesoris = Aksesoris::orderBy('created_at', 'desc')->get();
            return response()->json($aksesoris);
        }

        $aksesoris = Aksesoris::orderBy('created_at', 'desc')
            ->paginate($perPage ? intval($perPage) : 5);
        return response()->json($aksesoris);
    }
    
    public function store(Request $request)
    {
        $request->validate([
        'nama_aksesoris' => 'required|string',
        'jenis_aksesoris' => 'required|string|max:255',
        'satuan' => 'required|in:' . implode(',', array_keys(Aksesoris::getSatuanAksesorisOptions())),
        'harga_jual' => 'nullable|numeric|min:0',
        'foto_aksesoris' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        'jumlah_per_satuan' => 'required|integer|min:1',

        ]);

        $data = $request->all();

        if (isset($data['harga_jual']) && isset($data['jumlah_per_satuan'])) {
            $data['harga_per_biji'] = $data['jumlah_per_satuan'] > 0
                ? ($data['harga_jual'] / $data['jumlah_per_satuan'])
                : 0;
        }

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
            return response()->json(['error' => 'Aksesoris tidak ditemukan'], 404);
        }

        $request->validate([
            'nama_aksesoris' => 'sometimes|required|string',
            'jenis_aksesoris' => 'sometimes|required|string|max:255',
            'satuan' => 'sometimes|required|in:' . implode(',', array_keys(Aksesoris::getSatuanAksesorisOptions())),
            'harga_jual' => 'nullable|numeric|min:0',
            'jumlah_per_satuan' => 'sometimes|integer|min:1',
            'foto_aksesoris' => 'sometimes|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $aksesoris->update($request->except('foto_aksesoris'));

        if ($request->has('harga_jual') || $request->has('jumlah_per_satuan')) {
            $harga_jual = $request->input('harga_jual', $aksesoris->harga_jual);
            $jumlah_per_satuan = $request->input('jumlah_per_satuan', $aksesoris->jumlah_per_satuan);

            $aksesoris->harga_per_biji = $jumlah_per_satuan > 0
                ? ($harga_jual / $jumlah_per_satuan)
                : 0;

            $aksesoris->save();
        }

        if ($request->hasFile('foto_aksesoris')) {
            if ($aksesoris->foto_aksesoris && \Storage::exists('public/' . $aksesoris->foto_aksesoris)) {
                \Storage::delete('public/' . $aksesoris->foto_aksesoris);
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

    public function resetStok($id)
    {
        $aksesoris = Aksesoris::find($id);

        if (!$aksesoris) {
            return response()->json(['error' => 'Aksesoris tidak ditemukan'], 404);
        }

        // Set semua stok dengan status 'tersedia' menjadi 'terpakai'
        // sehingga jumlah_stok (yang dihitung dari status='tersedia') menjadi 0
        $aksesoris->stokAksesoris()->where('status', 'tersedia')->update(['status' => 'terpakai']);

        // Refresh agar appended attribute terhitung ulang
        $aksesoris->refresh();

        return response()->json([
            'message' => 'Stok berhasil dikosongkan',
            'aksesoris' => $aksesoris,
        ]);
    }
}
