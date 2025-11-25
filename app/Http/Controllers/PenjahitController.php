<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Penjahit;

class PenjahitController extends Controller
{
    // Menampilkan semua penjahit
    public function index()
    {
        $penjahits = Penjahit::all();
        return response()->json($penjahits); // Mengembalikan data penjahit dalam format JSON
    }

    // Menampilkan form untuk membuat penjahit baru (untuk React, ini bisa digantikan dengan form di frontend)
    public function create()
    {
        return response()->json(['message' => 'Use the frontend to create a new penjahit.']); // Pesan bahwa form di frontend
    }

   // Menyimpan Penjahit baru
   public function store(Request $request)
   {
       \Log::info('🔵 Menerima request POST', ['data' => $request->all()]);
       if ($request->has('mesin') && is_string($request->mesin)) {
        $request->merge(['mesin' => json_decode($request->mesin, true)]);
    }
       try {
           // Validasi input data
           $validated = $request->validate([
               'nama_penjahit' => 'required|string|max:100',
               'kontak' => 'required|string|max:100',
               'alamat' => 'required|string',
               'ktp' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:15000',
               'kategori_penjahit'  => 'required|string|max:100',
               'jumlah_tim' => 'required|integer|min:1',
               'no_rekening' => 'required|string|max:50', // Gunakan string, bukan integer
               'bank' => 'required|string|max:100',
               'mesin' => 'nullable|array',
               'mesin.*.nama' => 'required|string',
               'mesin.*.jumlah' => 'required|integer|min:1',
           ]);
   
           \Log::info('🟢 Validasi sukses', ['validated' => $validated]);
   
           // Jika ada gambar KTP, simpan ke storage
           if ($request->hasFile('ktp')) {
               $validated['ktp'] = $request->file('ktp')->store('ktp_penjahit', 'public'); // Simpan di storage/app/public/ktp_penjahit
               \Log::info('📸 KTP berhasil disimpan', ['path' => $validated['ktp']]);
           }
          
   
           // Simpan data dengan mesin sebagai JSON
           $penjahit = Penjahit::create([
               'nama_penjahit' => $validated['nama_penjahit'],
               'kontak' => $validated['kontak'],
               'alamat' => $validated['alamat'],
               'ktp' => $validated['ktp'] ?? null,
               'kategori_penjahit' => $validated['kategori_penjahit'],
               'jumlah_tim' => $validated['jumlah_tim'],
               'no_rekening' => $validated['no_rekening'],
               'bank' => $validated['bank'],
               'mesin' => $validated['mesin'], // Laravel akan otomatis meng-cast ke JSON

        ]);
   
           \Log::info('✅ Data berhasil disimpan', ['penjahit' => $penjahit]);
   
           return response()->json($penjahit, 201);
       } catch (\Exception $e) {
           \Log::error('❌ Error saat menyimpan penjahit', ['message' => $e->getMessage()]);
           return response()->json(['error' => 'Terjadi kesalahan'], 500);
       }
   }
   

    // Menampilkan penjahit berdasarkan ID
    public function show($id)
    {
        $penjahit = Penjahit::findOrFail($id);
        return response()->json($penjahit); // Mengembalikan data penjahit berdasarkan ID
    }

    // Menampilkan form untuk mengedit penjahit (untuk React, ini bisa digantikan dengan form di frontend)
    public function edit($id)
    {
        $penjahit = Penjahit::findOrFail($id);
        return response()->json($penjahit); // Mengembalikan data penjahit yang akan diedit
    }

    // Memperbarui penjahit yang sudah ada
    public function update(Request $request, $id) {
        \Log::info('🔄 Menerima request PUT/PATCH', ['id' => $id, 'data' => $request->all()]);
        
        if ($request->has('mesin') && is_string($request->mesin)) {
            $request->merge(['mesin' => json_decode($request->mesin, true)]);
        }
        
        try {
            $validated = $request->validate([
                'nama_penjahit' => 'required|string|max:100',
                'kontak' => 'required|string|max:100',
                'alamat' => 'required|string',
                'ktp' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:15000',
                'kategori_penjahit' => 'required|string|max:100',
                'jumlah_tim' => 'required|integer|min:1',
                'no_rekening' => 'required|string|max:50',
                'bank' => 'required|string|max:100',
                'mesin' => 'nullable|array',
                'mesin.*.nama' => 'required|string',
                'mesin.*.jumlah' => 'required|integer|min:1',
            ]);
    
            $penjahit = Penjahit::findOrFail($id);
    
            if ($request->hasFile('ktp')) {
                $validated['ktp'] = $request->file('ktp')->store('ktp_penjahit', 'public');
                \Log::info('📸 KTP berhasil diperbarui', ['path' => $validated['ktp']]);
            }
    
            $penjahit->update($validated);
            \Log::info('✅ Data berhasil diperbarui', ['penjahit' => $penjahit]);
    
            return response()->json(['message' => 'Penjahit berhasil diperbarui!', 'data' => $penjahit]);
        } catch (\Exception $e) {
            \Log::error('❌ Error saat memperbarui penjahit', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Terjadi kesalahan'], 500);
        }
    }
}
