<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CashboanCutting;
use App\Models\HistoryCashboanCutting;
use App\Models\TukangCutting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CashboanCuttingController extends Controller
{
    public function index()
    {
        // Ambil semua tukang cutting dengan left join cashbon (jika ada)
        // Gunakan GROUP BY dan SUM untuk menggabungkan multiple cashbon per tukang cutting
        $tukangCuttings = TukangCutting::leftJoin('cashboan_cutting', function ($join) {
            $join->on('tukang_cutting.id', '=', 'cashboan_cutting.tukang_cutting_id')
                ->where('cashboan_cutting.status_pembayaran', '=', 'belum lunas');
        })
            ->select(
                'tukang_cutting.id as tukang_cutting_id',
                'tukang_cutting.nama_tukang_cutting',
                DB::raw('MAX(cashboan_cutting.id) as cashboan_id'),
                DB::raw('COALESCE(SUM(cashboan_cutting.jumlah_cashboan), 0) as jumlah_cashboan'),
                DB::raw('MAX(cashboan_cutting.status_pembayaran) as status_pembayaran'),
                DB::raw('MAX(cashboan_cutting.tanggal_cashboan) as tanggal_cashboan')
            )
            ->groupBy('tukang_cutting.id', 'tukang_cutting.nama_tukang_cutting')
            ->orderBy('tukang_cutting.nama_tukang_cutting')
            ->get()
            ->map(function ($item) {
                // Pastikan jumlah_cashboan adalah integer
                $jumlahCashboan = (int) ($item->jumlah_cashboan ?? 0);

                return [
                    'id' => $item->cashboan_id ?? null,
                    'tukang_cutting_id' => $item->tukang_cutting_id,
                    'tukang_cutting' => [
                        'id' => $item->tukang_cutting_id,
                        'nama_tukang_cutting' => $item->nama_tukang_cutting
                    ],
                    'jumlah_cashboan' => $jumlahCashboan,
                    'status_pembayaran' => $item->status_pembayaran ?? 'belum lunas',
                    'tanggal_cashboan' => $item->tanggal_cashboan ?? null,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Daftar Cashboan Cutting',
            'data' => $tukangCuttings
        ], 200);
    }


    public function tambahCashboanCutting(Request $request)
    {
        $validated = $request->validate([
            'tukang_cutting_id' => 'required|exists:tukang_cutting,id',
            'jumlah_cashboan' => 'required|numeric|min:0',
            'bukti_transfer' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:20048',
        ]);

        if ($request->hasFile('bukti_transfer')) {
            $path = $request->file('bukti_transfer')->store('bukti_transfer_cashboan_cutting', 'public');
        } else {
            $path = null;
        }

        // Cek apakah tukang cutting sudah ada di cashbon
        $existingCashboan = CashboanCutting::where('tukang_cutting_id', $validated['tukang_cutting_id'])
            ->where('status_pembayaran', 'belum lunas')
            ->first();

        if ($existingCashboan) {
            // Jika sudah ada, tambahkan jumlah cashbon ke yang sudah ada
            $jumlahLama = $existingCashboan->jumlah_cashboan;
            $jumlahBaru = $validated['jumlah_cashboan'];
            $existingCashboan->jumlah_cashboan += $jumlahBaru;
            $existingCashboan->save();

            // Update history
            HistoryCashboanCutting::create([
                'cashboan_cutting_id' => $existingCashboan->id,
                'jenis_perubahan' => 'penambahan',
                'tanggal_perubahan' => now(),
                'jumlah_cashboan' => $existingCashboan->jumlah_cashboan,
                'perubahan_cashboan' => $jumlahBaru,
                'bukti_transfer' => $path,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cashboan cutting berhasil ditambahkan ke data yang sudah ada!',
                'data' => $existingCashboan
            ], 200);
        } else {
            // Jika belum ada, buat entri baru
            $cashboan = CashboanCutting::create([
                'tukang_cutting_id' => $validated['tukang_cutting_id'],
                'jumlah_cashboan' => $validated['jumlah_cashboan'],
                'status_pembayaran' => 'belum lunas',
                'tanggal_cashboan' => now(),
                'bukti_transfer' => $path,
            ]);

            HistoryCashboanCutting::create([
                'cashboan_cutting_id' => $cashboan->id,
                'jenis_perubahan' => 'penambahan',
                'tanggal_perubahan' => now(),
                'jumlah_cashboan' => $cashboan->jumlah_cashboan,
                'perubahan_cashboan' => $cashboan->jumlah_cashboan,
                'bukti_transfer' => $path,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cashboan cutting berhasil ditambahkan!',
                'data' => $cashboan
            ], 201);
        }
    }

    public function tambahCashboanLama(Request $request, $tukangCuttingId)
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
        Log::info('Cashbon Cutting Input', [
            'raw_request' => $rawValue,
            'type' => gettype($rawValue),
            'cleaned' => $cleanedValue,
            'converted_int' => $perubahanCashboan,
            'tukang_cutting_id' => $tukangCuttingId
        ]);

        // Validasi ulang setelah cleaning
        if ($perubahanCashboan <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Jumlah cashbon harus lebih dari 0'
            ], 400);
        }

        // Cek apakah cashbon sudah ada untuk tukang cutting ini
        $cashboan = CashboanCutting::where('tukang_cutting_id', $tukangCuttingId)
            ->where('status_pembayaran', 'belum lunas')
            ->first();

        if ($request->hasFile('bukti_transfer')) {
            $path = $request->file('bukti_transfer')->store('bukti_transfer_cashboan_cutting', 'public');
        } else {
            $path = null;
        }

        if ($cashboan) {
            // Jika sudah ada, tambahkan jumlah
            $jumlahLama = (int) $cashboan->jumlah_cashboan;
            $jumlahBaru = $jumlahLama + $perubahanCashboan;

            Log::info('Updating existing cashbon cutting', [
                'old_amount' => $jumlahLama,
                'adding' => $perubahanCashboan,
                'new_amount' => $jumlahBaru
            ]);

            $cashboan->jumlah_cashboan = $jumlahBaru;
            $cashboan->save();
        } else {
            // Jika belum ada, buat baru
            Log::info('Creating new cashbon cutting', [
                'amount' => $perubahanCashboan
            ]);

            $cashboan = CashboanCutting::create([
                'tukang_cutting_id' => $tukangCuttingId,
                'jumlah_cashboan' => $perubahanCashboan,
                'status_pembayaran' => 'belum lunas',
                'tanggal_cashboan' => now(),
                'bukti_transfer' => $path,
            ]);
        }

        HistoryCashboanCutting::create([
            'cashboan_cutting_id' => $cashboan->id,
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

        $query = HistoryCashboanCutting::where('cashboan_cutting_id', $id)->orderBy('tanggal_perubahan', 'desc');

        if ($jenisPerubahan) {
            $query->where('jenis_perubahan', $jenisPerubahan);
        }

        $history = $query->get();

        if ($history->isEmpty()) {
            return response()->json(['message' => 'History cashboantidak ditemukan'], 404);
        }

        return response()->json($history);
    }
}
