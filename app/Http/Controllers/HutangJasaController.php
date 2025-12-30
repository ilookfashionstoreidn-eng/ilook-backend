<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\HutangJasa;
use App\Models\HistoryHutangJasa;
use App\Models\TukangJasa;
use Illuminate\Support\Facades\DB;

class HutangJasaController extends Controller
{

    public function index()
    {
        // Ambil semua tukang jasa dengan left join hutang (jika ada)
        // Gunakan GROUP BY dan SUM untuk menggabungkan multiple hutang per tukang jasa
        $tukangJasas = TukangJasa::leftJoin('hutang_jasa', function ($join) {
            $join->on('tukang_jasa.id', '=', 'hutang_jasa.tukang_jasa_id')
                ->where('hutang_jasa.status_pembayaran', '=', 'belum');
        })
            ->select(
                'tukang_jasa.id as tukang_jasa_id',
                'tukang_jasa.nama',
                DB::raw('MAX(hutang_jasa.id) as hutang_id'),
                DB::raw('COALESCE(SUM(hutang_jasa.jumlah_hutang), 0) as jumlah_hutang'),
                DB::raw('MAX(hutang_jasa.status_pembayaran) as status_pembayaran'),
                DB::raw('MAX(hutang_jasa.tanggal_hutang) as tanggal_hutang'),
                DB::raw('MAX(hutang_jasa.potongan_per_minggu) as potongan_per_minggu'),
                DB::raw('MAX(hutang_jasa.is_potongan_persen) as is_potongan_persen'),
                DB::raw('MAX(hutang_jasa.persentase_potongan) as persentase_potongan')
            )
            ->groupBy('tukang_jasa.id', 'tukang_jasa.nama')
            ->orderBy('tukang_jasa.nama')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->hutang_id ?? null,
                    'tukang_jasa_id' => $item->tukang_jasa_id,
                    'tukang_jasa' => [
                        'id' => $item->tukang_jasa_id,
                        'nama' => $item->nama
                    ],
                    'jumlah_hutang' => $item->jumlah_hutang ?? 0,
                    'status_pembayaran' => $item->status_pembayaran ?? 'belum',
                    'tanggal_hutang' => $item->tanggal_hutang ?? null,
                    'potongan_per_minggu' => $item->potongan_per_minggu ?? null,
                    'is_potongan_persen' => $item->is_potongan_persen ?? false,
                    'persentase_potongan' => $item->persentase_potongan ?? null,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Daftar Hutang Jasa',
            'data' => $tukangJasas
        ], 200);
    }
    public function tambahHutangJasa(Request $request)
    {
        // Konversi is_potongan_persen dari string ke boolean
        $request->merge([
            'is_potongan_persen' => $request->is_potongan_persen === '1' || $request->is_potongan_persen === true || $request->is_potongan_persen === 'true'
        ]);

        $validated = $request->validate([
            'tukang_jasa_id' => 'required|exists:tukang_jasa,id',
            'jumlah_hutang' => 'required|numeric|min:0',
            'potongan_per_minggu' => 'nullable|numeric|min:0',
            'is_potongan_persen' => 'required|boolean',
            'persentase_potongan' => 'nullable|numeric|min:0|max:100',
            'bukti_transfer' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:20048',
        ]);

        //
        if ($validated['is_potongan_persen'] && is_null($validated['persentase_potongan'])) {
            return response()->json(['message' => 'Persentase potongan harus diisi'], 400);
        }

        if (!$validated['is_potongan_persen'] && is_null($validated['potongan_per_minggu'])) {
            return response()->json(['message' => 'Potongan tetap harus diisi'], 400);
        }


        if ($request->hasFile('bukti_transfer')) {
            $path = $request->file('bukti_transfer')->store('bukti_transfer_jasa', 'public');
            $validated['bukti_transfer'] = $path;
        } else {
            $validated['bukti_transfer'] = null;
        }

        // Cek apakah hutang sudah ada untuk tukang jasa ini
        $existingHutang = HutangJasa::where('tukang_jasa_id', $validated['tukang_jasa_id'])
            ->where('status_pembayaran', 'belum')
            ->first();

        if ($existingHutang) {
            // Jika sudah ada, tambahkan jumlah hutang ke yang sudah ada
            $jumlahLama = $existingHutang->jumlah_hutang;
            $jumlahBaru = $validated['jumlah_hutang'];
            $existingHutang->jumlah_hutang += $jumlahBaru;

            // Update potongan jika diisi
            if (!$validated['is_potongan_persen'] && isset($validated['potongan_per_minggu'])) {
                $existingHutang->potongan_per_minggu = $validated['potongan_per_minggu'];
                $existingHutang->is_potongan_persen = false;
                $existingHutang->persentase_potongan = null;
            } elseif ($validated['is_potongan_persen'] && isset($validated['persentase_potongan'])) {
                $existingHutang->persentase_potongan = $validated['persentase_potongan'];
                $existingHutang->is_potongan_persen = true;
                $existingHutang->potongan_per_minggu = null;
            }

            $existingHutang->save();

            // Update history
            HistoryHutangJasa::create([
                'hutang_jasa_id' => $existingHutang->id,
                'jenis_perubahan' => 'penambahan',
                'tanggal_perubahan' => now(),
                'jumlah_hutang' => $existingHutang->jumlah_hutang,
                'perubahan_hutang' => $jumlahBaru,
                'bukti_transfer' => $validated['bukti_transfer'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Hutang jasa berhasil ditambahkan ke data yang sudah ada!',
                'data' => $existingHutang
            ], 200);
        } else {
            // Jika belum ada, buat entri baru
            $hutang = HutangJasa::create([
                'tukang_jasa_id' => $validated['tukang_jasa_id'],
                'jumlah_hutang' => $validated['jumlah_hutang'],
                'status_pembayaran' => 'belum',
                'tanggal_hutang' => now(),
                'potongan_per_minggu' => $validated['is_potongan_persen'] ? null : $validated['potongan_per_minggu'],
                'is_potongan_persen' => $validated['is_potongan_persen'],
                'persentase_potongan' => $validated['is_potongan_persen'] ? $validated['persentase_potongan'] : null,
                'bukti_transfer' => $validated['bukti_transfer'],
            ]);

            HistoryHutangJasa::create([
                'hutang_jasa_id' => $hutang->id,
                'jenis_perubahan' => 'penambahan',
                'tanggal_perubahan' => now(),
                'jumlah_hutang' => $hutang->jumlah_hutang,
                'perubahan_hutang' => $hutang->jumlah_hutang,
                'bukti_transfer' => $validated['bukti_transfer'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Hutang Jasa berhasil ditambahkan!',
                'data' => $hutang
            ], 201);
        }
    }

    public function tambahHutangLama(Request $request, $tukangJasaId)
    {
        $request->validate([
            'perubahan_hutang' => 'required|numeric|min:1',
            'bukti_transfer' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:20048',
        ]);

        // Cek apakah hutang sudah ada untuk tukang jasa ini
        $hutang = HutangJasa::where('tukang_jasa_id', $tukangJasaId)
            ->where('status_pembayaran', 'belum')
            ->first();

        if ($request->hasFile('bukti_transfer')) {
            $path = $request->file('bukti_transfer')->store('bukti_transfer', 'public');
        } else {
            $path = null;
        }

        if ($hutang) {
            // Jika sudah ada, tambahkan jumlah
            $hutang->jumlah_hutang += $request->perubahan_hutang;
            if ($path) {
                $hutang->bukti_transfer = $path;
            }
            $hutang->save();
        } else {
            // Jika belum ada, buat baru
            $hutang = HutangJasa::create([
                'tukang_jasa_id' => $tukangJasaId,
                'jumlah_hutang' => $request->perubahan_hutang,
                'status_pembayaran' => 'belum',
                'tanggal_hutang' => now(),
                'bukti_transfer' => $path,
            ]);
        }

        HistoryHutangJasa::create([
            'hutang_jasa_id' => $hutang->id,
            'jenis_perubahan' => 'penambahan',
            'tanggal_perubahan' => now(),
            'jumlah_hutang' => $hutang->jumlah_hutang,
            'perubahan_hutang' => $request->perubahan_hutang,
            'bukti_transfer' => $path ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Hutang berhasil ditambahkan',
            'data' => $hutang
        ]);
    }

    public function getHistoryByHutangId(Request $request, $id)
    {

        $jenisPerubahan = $request->query('jenis_perubahan');

        $query = HistoryHutangJasa::where('hutang_jasa_id', $id)->orderBy('tanggal_perubahan', 'desc');

        if ($jenisPerubahan) {
            $query->where('jenis_perubahan', $jenisPerubahan);
        }

        $history = $query->get();

        if ($history->isEmpty()) {
            return response()->json(['message' => 'History hutang tidak ditemukan'], 404);
        }
        return response()->json($history);
    }
}
