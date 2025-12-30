<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CashboanJasa;
use App\Models\HistoryCashboanJasa;
use App\Models\TukangJasa;
use Illuminate\Support\Facades\DB;

class CashboanJasaController extends Controller
{
    public function index()
    {
        // Ambil semua tukang jasa dengan left join cashbon (jika ada)
        // Gunakan GROUP BY dan SUM untuk menggabungkan multiple cashbon per tukang jasa
        $tukangJasas = TukangJasa::leftJoin('cashboan_jasa', function ($join) {
            $join->on('tukang_jasa.id', '=', 'cashboan_jasa.tukang_jasa_id')
                ->where('cashboan_jasa.status_pembayaran', '=', 'belum lunas');
        })
            ->select(
                'tukang_jasa.id as tukang_jasa_id',
                'tukang_jasa.nama',
                DB::raw('MAX(cashboan_jasa.id) as cashboan_id'),
                DB::raw('COALESCE(SUM(cashboan_jasa.jumlah_cashboan), 0) as jumlah_cashboan'),
                DB::raw('MAX(cashboan_jasa.status_pembayaran) as status_pembayaran'),
                DB::raw('MAX(cashboan_jasa.tanggal_cashboan) as tanggal_cashboan')
            )
            ->groupBy('tukang_jasa.id', 'tukang_jasa.nama')
            ->orderBy('tukang_jasa.nama')
            ->get()
            ->map(function ($item) {
                // Pastikan jumlah_cashboan adalah integer
                $jumlahCashboan = (int) ($item->jumlah_cashboan ?? 0);

                return [
                    'id' => $item->cashboan_id ?? null,
                    'tukang_jasa_id' => $item->tukang_jasa_id,
                    'tukang_jasa' => [
                        'id' => $item->tukang_jasa_id,
                        'nama' => $item->nama
                    ],
                    'jumlah_cashboan' => $jumlahCashboan,
                    'status_pembayaran' => $item->status_pembayaran ?? 'belum lunas',
                    'tanggal_cashboan' => $item->tanggal_cashboan ?? null,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Daftar Cashboan Jasa',
            'data' => $tukangJasas
        ], 200);
    }


    public function tambahCashboanJasa(Request $request)
    {
        $validated = $request->validate([
            'tukang_jasa_id' => 'required|exists:tukang_jasa,id',
            'jumlah_cashboan' => 'required|numeric|min:0',
            'bukti_transfer' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:20048',
        ]);


        if ($request->hasFile('bukti_transfer')) {
            $path = $request->file('bukti_transfer')->store('bukti_transfer_cashboan_jasa', 'public');
        } else {
            $path = null;
        }

        // Cek apakah penjahit sudah ada di cashbon
        $existingCashboan = \App\Models\CashboanJasa::where('tukang_jasa_id', $validated['tukang_jasa_id'])
            ->where('status_pembayaran', 'belum lunas')
            ->first();

        if ($existingCashboan) {
            // Jika sudah ada, tambahkan jumlah cashbon ke yang sudah ada
            $jumlahLama = $existingCashboan->jumlah_cashboan;
            $jumlahBaru = $validated['jumlah_cashboan'];
            $existingCashboan->jumlah_cashboan += $jumlahBaru;
            $existingCashboan->save();

            // Update history
            HistoryCashboanJasa::create([
                'cashboan_jasa_id' => $existingCashboan->id,
                'jenis_perubahan' => 'penambahan',
                'tanggal_perubahan' => now(),
                'jumlah_cashboan' => $existingCashboan->jumlah_cashboan,
                'perubahan_cashboan' => $jumlahBaru,
                'bukti_transfer' => $path,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cashboan jasa berhasil ditambahkan ke data yang sudah ada!',
                'data' => $existingCashboan
            ], 200);
        } else {
            // Jika belum ada, buat entri baru
            $cashboan = \App\Models\CashboanJasa::create([
                'tukang_jasa_id' => $validated['tukang_jasa_id'],
                'jumlah_cashboan' => $validated['jumlah_cashboan'],
                'status_pembayaran' => 'belum lunas',
                'tanggal_cashboan' => now(),
                'bukti_transfer' => $path,
            ]);

            HistoryCashboanJasa::create([
                'cashboan_jasa_id' => $cashboan->id,
                'jenis_perubahan' => 'penambahan',
                'tanggal_perubahan' => now(),
                'jumlah_cashboan' => $cashboan->jumlah_cashboan,
                'perubahan_cashboan' => $cashboan->jumlah_cashboan,
                'bukti_transfer' => $path,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cashboan jasa berhasil ditambahkan!',
                'data' => $cashboan
            ], 201);
        }
    }

    public function tambahCashboanLama(Request $request, $tukangJasaId)
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
            'tukang_jasa_id' => $tukangJasaId
        ]);

        // Validasi ulang setelah cleaning
        if ($perubahanCashboan <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Jumlah cashbon harus lebih dari 0'
            ], 400);
        }

        // Cek apakah cashbon sudah ada untuk tukang jasa ini
        $cashboan = CashboanJasa::where('tukang_jasa_id', $tukangJasaId)
            ->where('status_pembayaran', 'belum lunas')
            ->first();

        if ($request->hasFile('bukti_transfer')) {
            $path = $request->file('bukti_transfer')->store('bukti_transfer_cashboan', 'public');
        } else {
            $path = null;
        }

        if ($cashboan) {
            // Jika sudah ada, tambahkan jumlah
            $jumlahLama = (int) $cashboan->jumlah_cashboan;
            $jumlahBaru = $jumlahLama + $perubahanCashboan;

            \Log::info('Updating existing cashbon', [
                'old_amount' => $jumlahLama,
                'adding' => $perubahanCashboan,
                'new_amount' => $jumlahBaru
            ]);

            $cashboan->jumlah_cashboan = $jumlahBaru;
            $cashboan->save();
        } else {
            // Jika belum ada, buat baru
            \Log::info('Creating new cashbon', [
                'amount' => $perubahanCashboan
            ]);

            $cashboan = CashboanJasa::create([
                'tukang_jasa_id' => $tukangJasaId,
                'jumlah_cashboan' => $perubahanCashboan,
                'status_pembayaran' => 'belum lunas',
                'tanggal_cashboan' => now(),
                'bukti_transfer' => $path,
            ]);
        }

        HistoryCashboanJasa::create([
            'cashboan_jasa_id' => $cashboan->id,
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

    public function getHistoryByCashboanId(Request $request, $id)
    {

        $jenisPerubahan = $request->query('jenis_perubahan');


        $query = HistoryCashboanJasa::where('cashboan_jasa_id', $id)->orderBy('tanggal_perubahan', 'desc');


        if ($jenisPerubahan) {
            $query->where('jenis_perubahan', $jenisPerubahan);
        }

        $history = $query->get();

        if ($history->isEmpty()) {
            return response()->json(['message' => 'History cashboan tidak ditemukan'], 404);
        }

        return response()->json($history);
    }
}
