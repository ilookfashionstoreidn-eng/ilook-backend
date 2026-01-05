<?php

namespace App\Http\Controllers;

use App\Models\Cashboan;
use App\Models\Penjahit;
use Illuminate\Http\Request;
use App\Models\HistoryCashboan;
use Illuminate\Support\Facades\DB;

class CashboanController extends Controller
{
    public function index(Request $request)
    {
        // Ambil semua penjahit dengan left join cashbon (jika ada)
        // Gunakan GROUP BY dan SUM untuk menggabungkan multiple cashbon per penjahit
        $penjahits = Penjahit::leftJoin('cashboan', function ($join) {
            $join->on('penjahit_cmt.id_penjahit', '=', 'cashboan.id_penjahit')
                ->where('cashboan.status_pembayaran', '=', 'belum lunas');
        })
            ->select(
                'penjahit_cmt.id_penjahit',
                'penjahit_cmt.nama_penjahit',
                DB::raw('MAX(cashboan.id_cashboan) as cashboan_id'),
                DB::raw('COALESCE(SUM(cashboan.jumlah_cashboan), 0) as jumlah_cashboan'),
                DB::raw('MAX(cashboan.status_pembayaran) as status_pembayaran'),
                DB::raw('MAX(cashboan.tanggal_cashboan) as tanggal_cashboan')
            )
            ->groupBy('penjahit_cmt.id_penjahit', 'penjahit_cmt.nama_penjahit')
            ->orderBy('penjahit_cmt.nama_penjahit')
            ->get()
            ->map(function ($item) {
                // Pastikan jumlah_cashboan adalah integer
                $jumlahCashboan = (int) ($item->jumlah_cashboan ?? 0);

                return [
                    'id' => $item->cashboan_id ?? null,
                    'id_penjahit' => $item->id_penjahit,
                    'penjahit' => [
                        'id_penjahit' => $item->id_penjahit,
                        'nama_penjahit' => $item->nama_penjahit
                    ],
                    'jumlah_cashboan' => $jumlahCashboan,
                    'status_pembayaran' => $item->status_pembayaran ?? 'belum lunas',
                    'tanggal_cashboan' => $item->tanggal_cashboan ?? null,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Daftar Cashboan',
            'data' => $penjahits
        ], 200);
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
        $validated = $request->validate([
            'id_penjahit' => 'required|exists:penjahit_cmt,id_penjahit',
            'jumlah_cashboan' => 'required|numeric|min:0',
            'bukti_transfer' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:20048',
        ]);


        if ($request->hasFile('bukti_transfer')) {
            $path = $request->file('bukti_transfer')->store('bukti_transfer_cashboan', 'public');
        } else {
            $path = null;
        }

        // Cek apakah penjahit sudah ada di cashbon
        $existingCashboan = Cashboan::where('id_penjahit', $validated['id_penjahit'])
            ->where('status_pembayaran', 'belum lunas')
            ->first();

        if ($existingCashboan) {
            // Jika sudah ada, tambahkan jumlah cashbon ke yang sudah ada
            $jumlahLama = $existingCashboan->jumlah_cashboan;
            $jumlahBaru = $validated['jumlah_cashboan'];
            $existingCashboan->jumlah_cashboan += $jumlahBaru;
            $existingCashboan->potongan_per_minggu = $existingCashboan->jumlah_cashboan;
            $existingCashboan->save();

            // Update history
            HistoryCashboan::create([
                'id_cashboan' => $existingCashboan->id_cashboan,
                'jenis_perubahan' => 'penambahan',
                'tanggal_perubahan' => now(),
                'jumlah_cashboan' => $existingCashboan->jumlah_cashboan,
                'perubahan_cashboan' => $jumlahBaru,
                'bukti_transfer' => $path,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cashboan berhasil ditambahkan ke data yang sudah ada!',
                'data' => $existingCashboan
            ], 200);
        } else {
            // Jika belum ada, buat entri baru
            $cashboan = Cashboan::create([
                'id_penjahit' => $validated['id_penjahit'],
                'jumlah_cashboan' => $validated['jumlah_cashboan'],
                'status_pembayaran' => 'belum lunas',
                'tanggal_cashboan' => now(),
                'potongan_per_minggu' => $validated['jumlah_cashboan'],
                'bukti_transfer' => $path,
            ]);

            HistoryCashboan::create([
                'id_cashboan' => $cashboan->id_cashboan,
                'jenis_perubahan' => 'penambahan',
                'tanggal_perubahan' => now(),
                'jumlah_cashboan' => $cashboan->jumlah_cashboan,
                'perubahan_cashboan' => $cashboan->jumlah_cashboan,
                'bukti_transfer' => $path,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cashboan berhasil ditambahkan!',
                'data' => $cashboan
            ], 201);
        }
    }
    

    public function tambahCashboanLama(Request $request, $id_cashboan)
    {
        $request->validate([
            'perubahan_cashboan' => 'required|numeric|min:1',
            'bukti_transfer' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:20048',
        ]);

        // Pastikan perubahan_cashboan adalah integer, bukan float
        // Hapus semua karakter non-digit jika ada
        $rawValue = $request->perubahan_cashboan;
        $cleanedValue = preg_replace('/[^0-9]/', '', (string) $rawValue);
        $perubahanCashboan = (int) $cleanedValue;

        // Log untuk debugging
        \Log::info('Cashbon Input', [
            'raw_request' => $rawValue,
            'type' => gettype($rawValue),
            'cleaned' => $cleanedValue,
            'converted_int' => $perubahanCashboan,
            'id_cashboan' => $id_cashboan
        ]);

        // Validasi ulang setelah cleaning
        if ($perubahanCashboan <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Jumlah cashbon harus lebih dari 0'
            ], 400);
        }

        // Cek apakah cashbon sudah ada
        $cashboan = Cashboan::findOrFail($id_cashboan);

        if ($request->hasFile('bukti_transfer')) {
            $path = $request->file('bukti_transfer')->store('bukti_transfer_cashboan', 'public');
        } else {
            $path = null;
        }

        // Update jumlah cashboan dengan nilai yang ditambahkan
        $jumlahLama = (int) $cashboan->jumlah_cashboan;
        $jumlahBaru = $jumlahLama + $perubahanCashboan;

        \Log::info('Updating existing cashbon', [
            'old_amount' => $jumlahLama,
            'adding' => $perubahanCashboan,
            'new_amount' => $jumlahBaru
        ]);

        $cashboan->jumlah_cashboan = $jumlahBaru;
        $cashboan->potongan_per_minggu = $jumlahBaru;
        $cashboan->save();

        // Simpan perubahan ke history cashboan
        HistoryCashboan::create([
            'id_cashboan' => $cashboan->id_cashboan,
            'jenis_perubahan' => 'penambahan',
            'tanggal_perubahan' => now(),
            'jumlah_cashboan' => $cashboan->jumlah_cashboan,
            'perubahan_cashboan' => $perubahanCashboan,
            'bukti_transfer' => $path ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cashboan berhasil ditambahkan',
            'data' => $cashboan
        ]);
    }

    public function getHistoryByCashboanId(Request $request, $id_cashboan)
    {

        $jenisPerubahan = $request->query('jenis_perubahan');


        $query = HistoryCashboan::where('id_cashboan', $id_cashboan)->orderBy('tanggal_perubahan', 'desc');


        if ($jenisPerubahan) {
            $query->where('jenis_perubahan', $jenisPerubahan);
        }

        $history = $query->get();

        if ($history->isEmpty()) {
            return response()->json(['message' => 'History cashboan tidak ditemukan'], 404);
        }

        return response()->json($history);
    }


    public function show($id)
    {
        $cashboan = Cashboan::with('penjahit')->findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $cashboan,
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
