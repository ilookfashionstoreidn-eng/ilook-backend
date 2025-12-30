<?php

namespace App\Http\Controllers;

use App\Models\HutangCutting;
use App\Models\HistoryHutangCutting;
use App\Models\TukangCutting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HutangCuttingController extends Controller
{

    public function index()
    {
        // Ambil semua tukang cutting dengan left join hutang (jika ada)
        // Gunakan GROUP BY dan SUM untuk menggabungkan multiple hutang per tukang cutting
        $tukangCuttings = TukangCutting::leftJoin('hutang_cutting', function ($join) {
            $join->on('tukang_cutting.id', '=', 'hutang_cutting.tukang_cutting_id')
                ->where('hutang_cutting.status_pembayaran', '=', 'belum lunas');
        })
            ->select(
                'tukang_cutting.id as tukang_cutting_id',
                'tukang_cutting.nama_tukang_cutting',
                DB::raw('MAX(hutang_cutting.id) as hutang_id'),
                DB::raw('COALESCE(SUM(hutang_cutting.jumlah_hutang), 0) as jumlah_hutang'),
                DB::raw('MAX(hutang_cutting.status_pembayaran) as status_pembayaran'),
                DB::raw('MAX(hutang_cutting.tanggal_hutang) as tanggal_hutang'),
                DB::raw('MAX(hutang_cutting.potongan_per_minggu) as potongan_per_minggu'),
                DB::raw('MAX(hutang_cutting.is_potongan_persen) as is_potongan_persen'),
                DB::raw('MAX(hutang_cutting.persentase_potongan) as persentase_potongan')
            )
            ->groupBy('tukang_cutting.id', 'tukang_cutting.nama_tukang_cutting')
            ->orderBy('tukang_cutting.nama_tukang_cutting')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->hutang_id ?? null,
                    'tukang_cutting_id' => $item->tukang_cutting_id,
                    'tukang_cutting' => [
                        'id' => $item->tukang_cutting_id,
                        'nama_tukang_cutting' => $item->nama_tukang_cutting
                    ],
                    'jumlah_hutang' => $item->jumlah_hutang ?? 0,
                    'status_pembayaran' => $item->status_pembayaran ?? 'belum lunas',
                    'tanggal_hutang' => $item->tanggal_hutang ?? null,
                    'potongan_per_minggu' => $item->potongan_per_minggu ?? null,
                    'is_potongan_persen' => $item->is_potongan_persen ?? false,
                    'persentase_potongan' => $item->persentase_potongan ?? null,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Daftar Hutang Cutting',
            'data' => $tukangCuttings
        ], 200);
    }
    public function tambahHutangCutting(Request $request)
    {
        // Konversi is_potongan_persen dari string ke boolean
        $request->merge([
            'is_potongan_persen' => $request->is_potongan_persen === '1' || $request->is_potongan_persen === true || $request->is_potongan_persen === 'true'
        ]);

        $validated = $request->validate([
            'tukang_cutting_id' => 'required|exists:tukang_cutting,id',
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
            $path = $request->file('bukti_transfer')->store('bukti_transfer_cutting', 'public');
            $validated['bukti_transfer'] = $path;
        } else {
            $validated['bukti_transfer'] = null;
        }

        // Cek apakah hutang sudah ada untuk tukang cutting ini
        $existingHutang = HutangCutting::where('tukang_cutting_id', $validated['tukang_cutting_id'])
            ->where('status_pembayaran', 'belum lunas')
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
            HistoryHutangCutting::create([
                'hutang_cutting_id' => $existingHutang->id,
                'jenis_perubahan' => 'penambahan',
                'tanggal_perubahan' => now(),
                'jumlah_hutang' => $existingHutang->jumlah_hutang,
                'perubahan_hutang' => $jumlahBaru,
                'bukti_transfer' => $validated['bukti_transfer'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Hutang cutting berhasil ditambahkan ke data yang sudah ada!',
                'data' => $existingHutang
            ], 200);
        } else {
            // Jika belum ada, buat entri baru
            $hutang = HutangCutting::create([
                'tukang_cutting_id' => $validated['tukang_cutting_id'],
                'jumlah_hutang' => $validated['jumlah_hutang'],
                'status_pembayaran' => 'belum lunas',
                'tanggal_hutang' => now(),
                'potongan_per_minggu' => $validated['is_potongan_persen'] ? null : $validated['potongan_per_minggu'],
                'is_potongan_persen' => $validated['is_potongan_persen'],
                'persentase_potongan' => $validated['is_potongan_persen'] ? $validated['persentase_potongan'] : null,
                'bukti_transfer' => $validated['bukti_transfer'],
            ]);

            HistoryHutangCutting::create([
                'hutang_cutting_id' => $hutang->id,
                'jenis_perubahan' => 'penambahan',
                'tanggal_perubahan' => now(),
                'jumlah_hutang' => $hutang->jumlah_hutang,
                'perubahan_hutang' => $hutang->jumlah_hutang,
                'bukti_transfer' => $validated['bukti_transfer'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Hutang Cutting berhasil ditambahkan!',
                'data' => $hutang
            ], 201);
        }
    }

    public function tambahHutangLama(Request $request, $tukangCuttingId)
    {
        $request->validate([
            'perubahan_hutang' => 'required|numeric|min:1',
            'bukti_transfer' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:20048',
        ]);

        // Cek apakah hutang sudah ada untuk tukang cutting ini
        $hutang = HutangCutting::where('tukang_cutting_id', $tukangCuttingId)
            ->where('status_pembayaran', 'belum lunas')
            ->first();

        if ($request->hasFile('bukti_transfer')) {
            $path = $request->file('bukti_transfer')->store('bukti_transfer_cutting', 'public');
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
            $hutang = HutangCutting::create([
                'tukang_cutting_id' => $tukangCuttingId,
                'jumlah_hutang' => $request->perubahan_hutang,
                'status_pembayaran' => 'belum lunas',
                'tanggal_hutang' => now(),
                'bukti_transfer' => $path,
            ]);
        }

        HistoryHutangCutting::create([
            'hutang_cutting_id' => $hutang->id,
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

        $query = HistoryHutangCutting::where('hutang_cutting_id', $id)->orderBy('tanggal_perubahan', 'desc');

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
