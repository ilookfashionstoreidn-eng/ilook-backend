<?php

namespace App\Http\Controllers;


use App\Models\Hutang;
use App\Models\Penjahit;
use App\Models\Pengiriman;
use App\Models\HistoryHutang;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class HutangController extends Controller
{

    public function index(Request $request)
    {
        // Ambil SEMUA penjahit dari CMT, termasuk yang belum punya hutang
        // Gunakan LEFT JOIN dan GROUP BY untuk menggabungkan multiple hutang per penjahit
        // Tampilkan semua penjahit dengan jumlah_hutang = 0 jika belum punya hutang
        $penjahits = Penjahit::leftJoin('hutang', function ($join) {
            $join->on('penjahit_cmt.id_penjahit', '=', 'hutang.id_penjahit')
                ->where('hutang.status_pembayaran', '=', 'belum lunas');
        })
            ->select(
                'penjahit_cmt.id_penjahit',
                'penjahit_cmt.nama_penjahit',
                DB::raw('MAX(hutang.id_hutang) as hutang_id'),
                DB::raw('COALESCE(CAST(SUM(hutang.jumlah_hutang) AS DECIMAL(15,2)), 0) as jumlah_hutang'),
                DB::raw('MAX(hutang.status_pembayaran) as status_pembayaran'),
                DB::raw('MAX(hutang.tanggal_hutang) as tanggal_hutang'),
                DB::raw('MAX(hutang.jenis_hutang) as jenis_hutang'),
                DB::raw('MAX(hutang.potongan_per_minggu) as potongan_per_minggu'),
                DB::raw('MAX(hutang.is_potongan_persen) as is_potongan_persen'),
                DB::raw('MAX(hutang.persentase_potongan) as persentase_potongan')
            )
            ->groupBy('penjahit_cmt.id_penjahit', 'penjahit_cmt.nama_penjahit')
            ->orderBy('penjahit_cmt.nama_penjahit')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->hutang_id ?? null,
                    'id_penjahit' => $item->id_penjahit,
                    'penjahit' => [
                        'id_penjahit' => $item->id_penjahit,
                        'nama_penjahit' => $item->nama_penjahit
                    ],
                    'jumlah_hutang' => (float)($item->jumlah_hutang ?? 0),
                    'status_pembayaran' => $item->status_pembayaran ?? 'belum lunas',
                    'tanggal_hutang' => $item->tanggal_hutang ?? null,
                    'jenis_hutang' => $item->jenis_hutang ?? null,
                    'potongan_per_minggu' => $item->potongan_per_minggu ? (float)$item->potongan_per_minggu : null,
                    'is_potongan_persen' => (bool)($item->is_potongan_persen ?? false),
                    'persentase_potongan' => $item->persentase_potongan ? (float)$item->persentase_potongan : null,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Daftar Hutang',
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

    public function tambahHutang(Request $request)
    {
        // Konversi is_potongan_persen dari string ke boolean
        $request->merge([
            'is_potongan_persen' => $request->is_potongan_persen === '1' || $request->is_potongan_persen === true || $request->is_potongan_persen === 'true'
        ]);

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

        // Cek apakah hutang sudah ada untuk penjahit ini
        $existingHutang = Hutang::where('id_penjahit', $validated['id_penjahit'])
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
            HistoryHutang::create([
                'id_hutang' => $existingHutang->id_hutang,
                'jenis_perubahan' => 'penambahan',
                'tanggal_perubahan' => now(),
                'jumlah_hutang' => $existingHutang->jumlah_hutang,
                'perubahan_hutang' => $jumlahBaru,
                'bukti_transfer' => $validated['bukti_transfer'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Hutang berhasil ditambahkan ke data yang sudah ada!',
                'data' => $existingHutang
            ], 200);
        } else {
            // Jika belum ada, buat entri baru
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
                'bukti_transfer' => $validated['bukti_transfer'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Hutang berhasil ditambahkan!',
                'data' => $hutang
            ], 201);
        }
    }


    public function tambahHutangLama(Request $request, $id_penjahit)
    {
        $request->validate([
            'perubahan_hutang' => 'required|numeric|min:1',
            'bukti_transfer' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:20048',
        ]);

        // Cek apakah hutang sudah ada untuk penjahit ini
        $hutang = Hutang::where('id_penjahit', $id_penjahit)
            ->where('status_pembayaran', 'belum lunas')
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
            $hutang = Hutang::create([
                'id_penjahit' => $id_penjahit,
                'jumlah_hutang' => $request->perubahan_hutang,
                'status_pembayaran' => 'belum lunas',
                'tanggal_hutang' => now(),
                'jenis_hutang' => 'overtime', // Tambahkan jenis_hutang dengan default value
                'bukti_transfer' => $path,
            ]);
        }

        HistoryHutang::create([
            'id_hutang' => $hutang->id_hutang,
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
