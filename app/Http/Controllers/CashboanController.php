<?php

namespace App\Http\Controllers;

use App\Models\Cashboan;
use App\Models\Penjahit;
use Illuminate\Http\Request;
use App\Models\HistoryCashboan;

class CashboanController extends Controller
{
    public function index(Request $request)
{
    $penjahitId = $request->query('penjahit');
    $query = Cashboan::with('penjahit')->withSum('logPembayaran', 'jumlah_dibayar');

    if (!empty($penjahitId)) {
        $query->where('id_penjahit', $penjahitId);
    }

    $cashboans = $query->orderBy('created_at', 'desc')->paginate(11);

   
    $cashboans->getCollection()->transform(function ($cashboan) {
        $totalDibayar = $cashboan->log_pembayaran_sum_jumlah_dibayar ?? 0; // Jika null, default ke 0
        $cashboan->sisa_cashbon = $cashboan->jumlah_cashboan - $totalDibayar;
        return $cashboan;
    });

    return response()->json($cashboans);
}

    

    
    public function create()
    {
        
        $penjahits = Penjahit::all();
       return response()->json([
           'success' => true,
           'penjahits' => $penjahits 
       ]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_penjahit' => 'required|exists:penjahit_cmt,id_penjahit',
            'jumlah_cashboan' => 'required|numeric|min:1',
            'status_pembayaran' => 'required|in:belum lunas,lunas,dibayar sebagian',
            'tanggal_jatuh_tempo' => 'required|date',
            'tanggal_cashboan' => 'required|date',
        ]);

        
        $cashboan = Cashboan::create($validated); 

         return response()->json([
            'success' => true,
            'message' => ' cashboan berhasil disimpan!',
            'data' => $cashboan
        ], 201);

    }

    public function tambahCashboan(Request $request)
    {
        $request->validate([
            'id_penjahit' => 'required|exists:penjahit_cmt,id_penjahit',
            'jumlah_cashboan' => 'required|numeric|min:0',
            'bukti_transfer' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:20048', 
            
        ]);
    
        $jumlahCashboan = $request->jumlah_cashboan;


        // Simpan file
        if ($request->hasFile('bukti_transfer')) {
            $path = $request->file('bukti_transfer')->store('bukti_transfer_cashboan', 'public'); // Ganti dengan folder 'bukti_transfer_cashboan'
            $validated['bukti_transfer'] = $path;
        } else {
            $validated['bukti_transfer'] = null;
        }

        $cashboan = Cashboan::create([
            'id_penjahit' => $request->id_penjahit,
            'jumlah_cashboan' => $jumlahCashboan,
            'status_pembayaran' => 'belum lunas',
            'tanggal_cashboan' => now(),
            'potongan_per_minggu' => $jumlahCashboan, // samakan dengan jumlah_cashboan
            'bukti_transfer' => $path,
        ]);
    
        HistoryCashboan::create([
            'id_cashboan' => $cashboan->id_cashboan,
            'jenis_perubahan' => 'penambahan',
            'tanggal_perubahan' => now(),
            'jumlah_cashboan' => $cashboan->jumlah_cashboan,
            'perubahan_cashboan' => $jumlahCashboan,
            'bukti_transfer' => $path,
        ]);
    
        return response()->json([
            'success' => true,
            'message' => 'Cashboan berhasil ditambahkan!',
            'data' => $cashboan
        ], 201);
    }
    

    public function tambahCashboanLama(Request $request, $id_cashboan)
{
    $request->validate([
        'perubahan_cashboan' => 'required|numeric|min:0', 
        'bukti_transfer' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:20048',
    ]);

    // Ambil cashboan yang sudah ada
    $cashboan = Cashboan::findOrFail($id_cashboan);

    // Update jumlah cashboan dengan nilai yang ditambahkan
    $cashboan->jumlah_cashboan += $request->perubahan_cashboan;

    // Simpan file
    if ($request->hasFile('bukti_transfer')) {
        $path = $request->file('bukti_transfer')->store('bukti_transfer_cashboan', 'public'); // Ganti dengan folder 'bukti_transfer_cashboan'
        $validated['bukti_transfer'] = $path;
    } else {
        $validated['bukti_transfer'] = null;
    }

    $cashboan->save();

    // Simpan perubahan ke history cashboan
    HistoryCashboan::create([
        'id_cashboan' => $cashboan->id_cashboan,
        'jenis_perubahan' => 'penambahan', 
        'tanggal_perubahan' => now(),
        'jumlah_cashboan' => $cashboan->jumlah_cashboan, 
        'perubahan_cashboan' => $request->perubahan_cashboan, 
        'bukti_transfer' => $path ?? null, 
    ]);

    return response()->json(['message' => 'Cashboan berhasil ditambahkan']);
}

public function getHistoryByCashboanId(Request $request, $id_cashboan)
{
    // Ambil parameter jenis_perubahan dari request
    $jenisPerubahan = $request->query('jenis_perubahan');

    // Query dasar
    $query = HistoryCashboan::where('id_cashboan', $id_cashboan)->orderBy('tanggal_perubahan', 'desc');

    // Jika ada filter jenis_perubahan, tambahkan ke query
    if ($jenisPerubahan) {
        $query->where('jenis_perubahan', $jenisPerubahan);
    }

    $history = $query->get();

    if ($history->isEmpty()) {
        return response()->json(['message' => 'History cashboantidak ditemukan'], 404);
    }

    return response()->json($history);
}


    public function show($id)
    {
        $cashboan = Cashboan::with('penjahit')->findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $cahboan,
        ]);
    }


    // Menampilkan form untuk mengedit Cashboan
    public function edit($id)
    {
        $cashboan = Cashboan::findOrFail($id); // Mengambil data cashboan berdasarkan id
        $penjahits = Penjahit::all(); // Mengambil semua Penjahit
        return response()->json([
            'success' => true,
            'cashboan' => $cashboan,
            'penjahits' => $penjahits // Menyertakan data Penjahit
        ]);
    }

    // Memperbarui data Cashboan
    public function update(Request $request, $id)
    {
        // Validasi inputan
        $validated = $request->validate([
          'id_penjahit' => 'required|exists:penjahit_cmt,id_penjahit',
            'jumlah_cashboan' => 'required|numeric|min:1',
            'status_pembayaran' => 'required|in:belum lunas,lunas,dibayar sebagian',
            'tanggal_jatuh_tempo' => 'required|date',
            'tanggal_cashboan' => 'required|date',
        ]);

        // Mencari dan memperbarui cashboan
        $cashboan = Cashboan::findOrFail($id);
        $cashboan->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'cashboan berhasil diperbarui!',
            'data' => $cashboan
        ]);
    }

    // Menghapus data Cashboan
    public function destroy($id)
    {
        $cashboan = Cashboan::findOrFail($id);
        $cashboan->delete();

        return response()->json([
            'success' => true,
            'message' => 'cashboan berhasil dihapus!'
        ]);
    }
}
