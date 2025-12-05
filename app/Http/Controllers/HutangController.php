<?php

namespace App\Http\Controllers;


use App\Models\Hutang;
use App\Models\Penjahit;
use App\Models\Pengiriman;
use App\Models\HistoryHutang;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HutangController extends Controller
{
    
    public function index(Request $request)
    {
        $penjahitId = $request->query('penjahit');
        $query = Hutang::with('penjahit')->withSum('logPembayaran', 'jumlah_dibayar');

        if (!empty($penjahitId)) {
            $query->where('id_penjahit', $penjahitId);
        }

        $hutangs = $query->orderBy('created_at', 'desc')->paginate(11);

        $hutangs->getCollection()->transform(function ($hutang) {
            $totalDibayar = $hutang->log_pembayaran_sum_jumlah_dibayar ?? 0; 
            return $hutang;
        });

        return response()->json($hutangs);
    }

    

    public function create()
    {
       $penjahits = Penjahit::all();
       return response()->json([
           'success' => true,
           'penjahits' => $penjahits 
       ]);
    }

    public function tambahHutang(Request $request)
    {
        $validated = $request->validate([
            'id_penjahit' => 'required|exists:penjahit_cmt,id_penjahit',
            'jumlah_hutang' => 'required|numeric|min:0',
            'jenis_hutang' => 'required|string',
            'potongan_per_minggu' => 'nullable|numeric|min:0',
            'is_potongan_persen' => 'required|boolean',
            'persentase_potongan' => 'nullable|numeric|min:0|max:100',
            'bukti_transfer' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:20048',
        ]);

        if ($validated['is_potongan_persen'] && is_null($validated['persentase_potongan'])) {
            return response()->json(['message' => 'Persentase potongan harus diisi'], 400);
        }

        if (!$validated['is_potongan_persen'] && is_null($validated['potongan_per_minggu'])) {
            return response()->json(['message' => 'Potongan tetap harus diisi'], 400);
        }

        if ($request->hasFile('bukti_transfer')) {
            $path = $request->file('bukti_transfer')->store('bukti_transfer', 'public');
            $validated['bukti_transfer'] = $path;
        } else {
            $validated['bukti_transfer'] = null;
        }
        
        $hutang = Hutang::create([
            'id_penjahit' => $validated['id_penjahit'],
            'jumlah_hutang' => $validated['jumlah_hutang'],
            'status_pembayaran' => 'belum lunas',
            'tanggal_hutang' => now(),
            'jenis_hutang' => $validated['jenis_hutang'],
            'potongan_per_minggu' => $validated['is_potongan_persen'] ? null : $validated['potongan_per_minggu'],
            'is_potongan_persen' => $validated['is_potongan_persen'],
            'persentase_potongan' => $validated['is_potongan_persen'] ? $validated['persentase_potongan'] : null,
            'bukti_transfer' => $validated['bukti_transfer'],
        ]);

        HistoryHutang::create([
            'id_hutang' => $hutang->id_hutang,
            'jenis_perubahan' => 'penambahan',
            'tanggal_perubahan' => now(),
            'jumlah_hutang' => $hutang->jumlah_hutang,
            'perubahan_hutang' => $hutang->jumlah_hutang,
            'bukti_transfer' => $path ?? null, 
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Hutang berhasil ditambahkan!',
            'data' => $hutang
        ], 201);
    }


    public function tambahHutangLama(Request $request, $id_hutang)
    {
        $request->validate([
            'perubahan_hutang' => 'required|numeric|min:0',
            'bukti_transfer' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:20048',
        ]);

        $hutang = Hutang::findOrFail($id_hutang);

        if ($request->hasFile('bukti_transfer')) {
            $path = $request->file('bukti_transfer')->store('bukti_transfer', 'public');
            $hutang->bukti_transfer = $path;
        }

        $hutang->jumlah_hutang += $request->perubahan_hutang;
        $hutang->save();

        HistoryHutang::create([
            'id_hutang' => $hutang->id_hutang,
            'jenis_perubahan' => 'penambahan', 
            'tanggal_perubahan' => now(),
            'jumlah_hutang' => $hutang->jumlah_hutang, 
            'perubahan_hutang' => $request->perubahan_hutang, 
            'bukti_transfer' => $path ?? null, 
        ]);

        return response()->json(['message' => 'Hutang berhasil ditambahkan']);
    }

    private function kurangiHutangManually($id_hutang, $jumlah_pengurangan)
    {
        $hutang = Hutang::findOrFail($id_hutang);

        if ($hutang->jumlah_hutang < $jumlah_pengurangan) {
            return response()->json(['message' => 'Jumlah pengurangan melebihi hutang yang ada'], 400);
        }

        $hutang->jumlah_hutang -= $jumlah_pengurangan;
        $hutang->save();

        HistoryHutang::create([
            'id_hutang' => $hutang->id_hutang,
            'jenis_perubahan' => 'pengurangan',
            'tanggal_perubahan' => now(),
            'jumlah_hutang' => $hutang->jumlah_hutang,
            'perubahan_hutang' => $jumlah_pengurangan,
        ]);
    }


    public function getHistoryByHutangId(Request $request, $id_hutang)
    {
        $jenisPerubahan = $request->query('jenis_perubahan');
        $query = HistoryHutang::where('id_hutang', $id_hutang)->orderBy('tanggal_perubahan', 'desc');

        if ($jenisPerubahan) {
            $query->where('jenis_perubahan', $jenisPerubahan);
        }

        $history = $query->get();

        if ($history->isEmpty()) {
            return response()->json(['message' => 'History hutang tidak ditemukan'], 404);
        }

        return response()->json($history);
    }


    public function hitungPotongan($id_hutang)
    {
        $hutang = Hutang::findOrFail($id_hutang);
        if ($hutang->is_potongan_persen) {
            $totalBayar = Pengiriman::whereHas('spk', function ($query) use ($hutang) {
                $query->where('id_penjahit', $hutang->id_penjahit);
            })->whereBetween('tanggal_pengiriman', [now()->startOfWeek(), now()->endOfWeek()])
            ->sum('total_bayar');
            
            $potongan = ($hutang->persentase_potongan / 100) * $totalBayar;
        } else {
            $potongan = $hutang->potongan_per_minggu;
        }

        return response()->json([
            'id_hutang' => $hutang->id_hutang,
            'total_bayar' =>  $totalBayar,
            'potongan' => round($potongan, 2),
        ]);
    }




    public function show($id)
    {
        $hutang = Hutang::with('penjahit')->findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $hutang,
        ]);
    }


    public function edit($id)
    {       
        $hutang = Hutang::findOrFail($id);
        $penjahits = Penjahit::all(); 
        return response()->json([
            'success' => true,
            'hutang' => $hutang,
            'penjahits' => $penjahits 
        ]);
    }


    public function update(Request $request, $id)
    {
         $validated = $request->validate([
            'id_penjahit' => 'required|exists:penjahit_cmt,id_penjahit',
            'jumlah_hutang' => 'required|numeric|min:1',
            'status_pembayaran' => 'required|in:belum lunas,lunas,dibayar sebagian',
            'tanggal_jatuh_tempo' => 'required|date',
            'tanggal_hutang' => 'required|date',
        ]);
        $hutang = Hutang::findOrFail($id);
        $hutang->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'hutang berhasil diperbarui!',
            'data' => $hutang
        ]);
    }

    public function destroy($id)
    {
        $hutang = Hutang::findOrFail($id);
        $hutang->delete();

        return response()->json([
            'success' => true,
            'message' => 'hutang berhasil dihapus!'
        ]);
    }
}