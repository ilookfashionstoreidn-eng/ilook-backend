<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Penjahit;

class PenjahitController extends Controller
{
    public function index()
    {
        $penjahits = Penjahit::with(['spk.warna', 'spk.pengiriman'])->get()->map(function ($penjahit) {
            $totalSisa = 0;
            foreach ($penjahit->spk as $spk) {
                $qty = $spk->warna->sum('qty');
                $dikirim = $spk->pengiriman->sum('total_barang_dikirim');
                $sisa = $qty - $dikirim;
                if ($sisa > 0) {
                    $totalSisa += $sisa;
                }
            }
            $penjahit->total_sisa = $totalSisa;
            return $penjahit;
        });
        return response()->json($penjahits); 
    }
    public function create()
    {
        return response()->json(['message' => 'Use the frontend to create a new penjahit.']); // Pesan bahwa form di frontend
    }

   public function store(Request $request)
   {
       \Log::info('🔵 Menerima request POST', ['data' => $request->all()]);
       if ($request->has('mesin') && is_string($request->mesin)) {
        $request->merge(['mesin' => json_decode($request->mesin, true)]);
    }
       try {
           $validated = $request->validate([
               'id_penjahit' => 'nullable|integer|unique:penjahit_cmt,id_penjahit',
               'nama_penjahit' => 'required|string|max:100',
               'kontak' => 'required|string|max:100',
               'alamat' => 'required|string',
               'ktp' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:15000',
               'kategori_penjahit'  => 'required|string|max:100',
               'jumlah_tim' => 'required|integer|min:1',
               'no_rekening' => 'required|string|max:50', 
               'bank' => 'required|string|max:100',
               'mesin' => 'nullable|array',
               'mesin.*.nama' => 'required|string',
               'mesin.*.jumlah' => 'required|integer|min:1',
           ]);
   
           \Log::info('🟢 Validasi sukses', ['validated' => $validated]);

           if ($request->hasFile('ktp')) {
               $validated['ktp'] = $request->file('ktp')->store('ktp_penjahit', 'public');
               \Log::info('📸 KTP berhasil disimpan', ['path' => $validated['ktp']]);
           }

           $penjahit = Penjahit::create([
               'id_penjahit' => $validated['id_penjahit'] ?? null,
               'nama_penjahit' => $validated['nama_penjahit'],
               'kontak' => $validated['kontak'],
               'alamat' => $validated['alamat'],
               'ktp' => $validated['ktp'] ?? null,
               'kategori_penjahit' => $validated['kategori_penjahit'],
               'jumlah_tim' => $validated['jumlah_tim'],
               'no_rekening' => $validated['no_rekening'],
               'bank' => $validated['bank'],
               'mesin' => $validated['mesin'], 

        ]);
   
           \Log::info('✅ Data berhasil disimpan', ['penjahit' => $penjahit]);
   
           return response()->json($penjahit, 201);
       } catch (\Exception $e) {
           \Log::error('❌ Error saat menyimpan penjahit', ['message' => $e->getMessage()]);
           return response()->json(['error' => 'Terjadi kesalahan'], 500);
       }
   }
   
    public function show($id)
    {
        $penjahit = Penjahit::findOrFail($id);
        return response()->json($penjahit); 
    }

    public function edit($id)
    {
        $penjahit = Penjahit::findOrFail($id);
        return response()->json($penjahit); 
    }

    public function update(Request $request, $id) {
        \Log::info('🔄 Menerima request PUT/PATCH', ['id' => $id, 'data' => $request->all()]);
        
        if ($request->has('mesin') && is_string($request->mesin)) {
            $request->merge(['mesin' => json_decode($request->mesin, true)]);
        }

        try {
            $validated = $request->validate([
                'id_penjahit' => 'nullable|integer|unique:penjahit_cmt,id_penjahit,' . $id . ',id_penjahit',
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
            $newId = $validated['id_penjahit'] ?? null;

            \DB::beginTransaction();
            try {
                if ($newId && (int)$newId !== (int)$id) {
                    // Disable foreign key checks temporarily
                    \DB::statement('SET FOREIGN_KEY_CHECKS=0;');

                    // Define tables and columns referencing id_penjahit
                    $tables = [
                        'spk_cmt' => 'id_penjahit',
                        'users' => 'id_penjahit',
                        'cashboan' => 'id_penjahit',
                        'hutang' => 'id_penjahit',
                        'pendapatan' => 'id_penjahit',
                        'petugas_c' => 'penjahit_id',
                        'petugas_d_verif' => 'penjahit_id',
                        'pengiriman' => 'id_penjahit',
                    ];

                    foreach ($tables as $table => $column) {
                        if (\Schema::hasTable($table) && \Schema::hasColumn($table, $column)) {
                            \DB::table($table)->where($column, $id)->update([$column => $newId]);
                        }
                    }

                    // Update the penjahit record's primary key directly via SQL query
                    \DB::table('penjahit_cmt')->where('id_penjahit', $id)->update(['id_penjahit' => $newId]);

                    // Refresh model instance with the new ID
                    $penjahit = Penjahit::findOrFail($newId);

                    \DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                }

                // Unset id_penjahit from Eloquent update to avoid key update errors
                unset($validated['id_penjahit']);

                if ($request->hasFile('ktp')) {
                    $validated['ktp'] = $request->file('ktp')->store('ktp_penjahit', 'public');
                    \Log::info('📸 KTP berhasil diperbarui', ['path' => $validated['ktp']]);
                }

                $penjahit->update($validated);
                \DB::commit();
            } catch (\Exception $transactionException) {
                \DB::rollBack();
                \DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                throw $transactionException;
            }

            \Log::info('✅ Data berhasil diperbarui', ['penjahit' => $penjahit]);

            return response()->json(['message' => 'Penjahit berhasil diperbarui!', 'data' => $penjahit]);
        } catch (\Exception $e) {
            \Log::error('❌ Error saat memperbarui penjahit', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Terjadi kesalahan'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $penjahit = Penjahit::findOrFail($id);
            $penjahit->delete();
            \Log::info('✅ Data berhasil dihapus', ['id' => $id]);
            return response()->json(['message' => 'Penjahit berhasil dihapus!'], 200);
        } catch (\Exception $e) {
            \Log::error('❌ Error saat menghapus penjahit', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Gagal menghapus penjahit'], 500);
        }
    }
}
