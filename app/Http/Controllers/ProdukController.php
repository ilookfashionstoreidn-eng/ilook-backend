<?php

namespace App\Http\Controllers;

use App\Models\Produk;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ProdukController extends Controller
{
    
   
    public function index(Request $request)
    {
        $kategoriProduk = $request->query('kategori_produk');
        $statusProduk = $request->query('status_produk'); // 🆕 tambahkan ini
        $sortBy = $request->query('sortBy', 'created_at');
        $sortOrder = $request->query('sortOrder', 'desc');

        $query = Produk::with('komponen');
        if ($kategoriProduk) {
            $query->where('kategori_produk', $kategoriProduk);
        }
        if ($statusProduk) {
            $query->where('status_produk', $statusProduk);
        }

        $produk = $query->orderBy($sortBy, $sortOrder)->get();

        $produk->transform(function ($item) {
            $item->gambar_produk = asset('storage/' . $item->gambar_produk);
            $item->total_komponen = $item->komponen->sum('total_harga_bahan');
            return $item;
        });

        return response()->json(['data' => $produk], Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_produk' => 'required|string|max:255',
            'kategori_produk' => 'required|string|max:255',
            'gambar_produk' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:25000',
            'jenis_produk' => 'required|string|max:255',
            'komponen' => 'array', 
            'komponen.*.jenis_komponen' => 'required|string',
            'komponen.*.nama_bahan' => 'nullable|string',
            'komponen.*.harga_bahan' => 'nullable|numeric',
            'komponen.*.jumlah_bahan' => 'nullable|numeric',
            'komponen.*.satuan_bahan' => 'nullable|string',
            'harga_jasa_cutting' => 'nullable|numeric',
            'harga_jasa_cmt' => 'nullable|numeric',
            'harga_jasa_aksesoris' => 'nullable|numeric',
            'harga_overhead' => 'nullable|numeric',

           

        ]);
        if ($request->hasFile('gambar_produk')) {
            $file = $request->file('gambar_produk');
            $fileName = time() . '_' . preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $file->getClientOriginalName());
            $filePath = $file->storeAs('public/images', $fileName);
            $validated['gambar_produk'] = 'images/' . $fileName;
        }
        
        $validated['status_produk'] = 'Sementara';

        $produk = Produk::create($validated);

        $totalKomponen = 0;
        if ($request->has('komponen')) {
            foreach ($request->komponen as $komp) {
                $komp['total_harga_bahan'] = ($komp['harga_bahan'] ?? 0) * ($komp['jumlah_bahan'] ?? 0);
                $produk->komponen()->create($komp);
                $totalKomponen += $komp['total_harga_bahan'];
            }
        }

        $hpp = $totalKomponen
            + ($produk->harga_jasa_cutting ?? 0)
            + ($produk->harga_jasa_cmt ?? 0)
            + ($produk->harga_jasa_aksesoris ?? 0)
            + ($produk->harga_overhead ?? 0);

        $produk->update(['hpp' => $hpp]);

        return response()->json($produk->load('komponen'), Response::HTTP_CREATED);
    }
  
    public function show(Produk $produk)
    {
          return response()->json($produk->load('komponen'), Response::HTTP_OK);
    }

   public function update(Request $request, $id)
{
    $validated = $request->validate([
        'nama_produk' => 'required|string|max:255',
        'kategori_produk' => 'required|string|max:255',
        'gambar_produk' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:25000',
        'jenis_produk' => 'required|string|max:255',
        'status_produk' => 'nullable|string',
        'komponen' => 'array', 
        'komponen.*.jenis_komponen' => 'required|string',
        'komponen.*.nama_bahan' => 'nullable|string',
        'komponen.*.harga_bahan' => 'nullable|numeric',
        'komponen.*.jumlah_bahan' => 'nullable|numeric',
        'komponen.*.satuan_bahan' => 'nullable|string',
        'harga_jasa_cutting' => 'nullable|numeric',
        'harga_jasa_cmt' => 'nullable|numeric',
        'harga_jasa_aksesoris' => 'nullable|numeric',
        'harga_overhead' => 'nullable|numeric',
    ]);

    $produk = Produk::findOrFail($id);
    if ($request->hasFile('gambar_produk')) {
        if ($produk->gambar_produk && \Storage::exists('public/' . $produk->gambar_produk)) {
            \Storage::delete('public/' . $produk->gambar_produk);
        }

        $file = $request->file('gambar_produk');
        $fileName = time() . '_' . preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $file->getClientOriginalName());
        $file->storeAs('public/images', $fileName);
        $validated['gambar_produk'] = 'images/' . $fileName;
    }

    $produk->update($validated);

    if ($request->has('komponen')) {
        $produk->komponen()->delete();
        $totalKomponen = 0;

        foreach ($request->komponen as $komp) {
            $komp['total_harga_bahan'] = ($komp['harga_bahan'] ?? 0) * ($komp['jumlah_bahan'] ?? 0);
            $produk->komponen()->create($komp);
            $totalKomponen += $komp['total_harga_bahan'];
        }

        $hpp = $totalKomponen
            + ($produk->harga_jasa_cutting ?? 0)
            + ($produk->harga_jasa_cmt ?? 0)
            + ($produk->harga_jasa_aksesoris ?? 0)
            + ($produk->harga_overhead ?? 0);

        $produk->update(['hpp' => $hpp]);
    }

    return response()->json($produk->load('komponen'), Response::HTTP_OK);
}

    

    

    
    public function destroy(Produk $produk)
    {
        // Hapus gambar dari storage jika ada
        if ($produk->gambar_produk && Storage::exists('public/' . $produk->gambar_produk)) {
            Storage::delete('public/' . $produk->gambar_produk);
        }
    
        // Hapus data produk dari database
        $produk->delete();
    
        return response()->json(['message' => 'Produk berhasil dihapus'], Response::HTTP_OK);
    }
    
}
