<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Http\Request;
use App\Models\SpkCutting;
use App\Models\HasilCutting;
use App\Models\HasilMarkeran;
use App\Models\MarkeranProduk;
use App\Models\HasilCuttingBahan;
use App\Models\SpkCuttingBahan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;




class HasilCuttingController extends Controller
{
   public function index()
{
    $data = HasilCutting::with([
        'spkCutting.produk',
        'spkCutting.tukangCutting',
        'markeran',
        'bahan.spkCuttingBahan.bagian'
    ])->get()->map(function ($item) {

        // Group bahan by nama_bagian
        $groupedBahan = [];

        foreach ($item->bahan as $b) {
            $namaBagian = $b->spkCuttingBahan->bagian->nama_bagian ?? 'Unknown Bagian';

            if (!isset($groupedBahan[$namaBagian])) {
                $groupedBahan[$namaBagian] = [];
            }

            $groupedBahan[$namaBagian][] = [
                'id' => $b->id,
                'spk_cutting_bahan_id' => $b->spk_cutting_bahan_id,
                'nama_bahan' => $b->spkCuttingBahan->nama_bahan ?? null,
                'qty' => $b->spkCuttingBahan->qty ?? null,
                'berat' => $b->berat,
                'hasil' => $b->hasil,
            ];
        }

        // Ubah array assosiatif jadi array objek agar JSON lebih rapi
        $bahanByBagian = [];
        foreach ($groupedBahan as $namaBagian => $bahanList) {
            $bahanByBagian[] = [
                'nama_bagian' => $namaBagian,
                'bahan' => $bahanList,
            ];
        }

        return [
            // data lain tetap ada
            'id' => $item->id,
            'spk_cutting_id' => $item->spk_cutting_id,
            'id_spk_cutting_id' => $item->spkCutting->id_spk_cutting_id ?? null,
            'foto_komponen' => $item->foto_komponen,
            'jumlah_komponen' => $item->jumlah_komponen,
            'updated_at' => $item->updated_at,
            'created_at' => $item->created_at,
            'nama_produk' => $item->spkCutting->produk->nama_produk ?? null,
            'produk_id' => $item->spkCutting->produk->id ?? null,
            'gambar_produk' => $item->spkCutting->produk->gambar_produk ?? null,
            'nama_tukang_cutting' => $item->spkCutting->tukangCutting->nama_tukang_cutting ?? null,
            'status_perbandingan_agregat' => $item->status_perbandingan_agregat,
            'total_hasil_pendapatan' => $item->total_hasil_pendapatan, 
            'markeran' => $item->markeran->map(function ($m) use ($item) {
                $standar = MarkeranProduk::where('produk_id', $item->spkCutting->produk_id ?? null)
                            ->where('nama_komponen', $m->nama_komponen)
                            ->first();

                return [
                    'nama_komponen' => $m->nama_komponen,
                    'hasil' => [
                        'total_panjang' => $m->total_panjang,
                        'jumlah_hasil' => $m->jumlah_hasil,
                        'berat_per_pcs' => $m->berat_per_pcs,
                    ],
                    'standar' => $standar ? [
                        'total_panjang' => $standar->total_panjang,
                        'jumlah_hasil' => $standar->jumlah_hasil,
                        'berat_per_pcs' => $standar->berat_per_pcs,
                    ] : null,
                    'status_perbandingan' => $m->status_perbandingan,
                ];
            }),
            'bahan_by_bagian' => $bahanByBagian, // ini bagian yang diubah
        ];
    });

    return response()->json($data);
}



    public function store(Request $request)
    {
    try {
          $validated = $request->validate([
            'spk_cutting_id' => 'required|exists:spk_cutting,id',
           'foto_komponen' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:150000',
            'jumlah_komponen' => 'required|integer|min:1|max:255',
            'spk_cutting_bagian_id' => 'required|exists:spk_cutting_bagian,id',
            'hasil_markeran' => 'nullable|array',
            'hasil_markeran.*.nama_komponen' => 'required|string',
            'hasil_markeran.*.total_panjang' => 'required|numeric|min:0',
            'hasil_markeran.*.jumlah_hasil' => 'required|integer|min:1',
            'hasil_bahan' => 'nullable|array',
            'hasil_bahan.*.spk_cutting_bagian_id' => 'required|exists:spk_cutting_bagian,id',
            'hasil_bahan.*.spk_cutting_bahan_id' => 'required|exists:spk_cutting_bahan,id',
            'hasil_bahan.*.berat' => 'nullable|numeric|min:0',
            'hasil_bahan.*.hasil' => 'nullable|integer|min:0',
        ]);
    // Validasi manual nama_komponen berdasarkan markeran produk
    $spkCutting = SpkCutting::with('produk')->findOrFail($validated['spk_cutting_id']);
    $produkId = $spkCutting->produk_id;

    
 


    $komponenValid = MarkeranProduk::where('produk_id', $produkId)
        ->pluck('nama_komponen')
        ->toArray();

    if ($request->has('hasil_markeran')) {
        foreach ($request->hasil_markeran as $index => $markeran) {
            if (!in_array($markeran['nama_komponen'], $komponenValid)) {
                return response()->json([
                    'message' => "Nama komponen tidak valid pada hasil_markeran index ke-{$index}.",
                    'errors' => [
                        "hasil_markeran.{$index}.nama_komponen" => ["Komponen '{$markeran['nama_komponen']}' tidak ditemukan di markeran produk."],
                    ],
                    'komponen_valid' => $komponenValid,
                ], 422);
            }
        }
    }

} catch (\Illuminate\Validation\ValidationException $e) {
    \Log::error('Validasi gagal di store HasilCuttingController', [
        'errors' => $e->errors(),
        'input' => $request->all()
    ]);

    return response()->json([
        'message' => 'Validasi gagal',
        'errors' => $e->errors(),
    ], 422);
}

    DB::beginTransaction();
    try {
        // Upload foto jika ada
        if ($request->hasFile('foto_komponen')) {
            $path = $request->file('foto_komponen')->store('foto_komponen', 'public');
            $validated['foto_komponen'] = $path;
        }

        $bagianIdDipilih = $validated['spk_cutting_bagian_id'];
        $totalHasilPendapatan = 0;

        if ($request->has('hasil_bahan')) {
            foreach ($request->hasil_bahan as $bahan) {
                if ($bahan['spk_cutting_bagian_id'] == $bagianIdDipilih) {
                    $totalHasilPendapatan += $bahan['hasil'] ?? 0;
                }
            }
        }

        $validated['total_hasil_pendapatan'] = $totalHasilPendapatan;

         // Hitung total bayar berdasarkan harga per pcs
        $hargaPerPcs = $spkCutting->harga_per_pcs ?? 0;
        $validated['total_bayar'] = $totalHasilPendapatan * $hargaPerPcs;

        // Simpan hasil cutting
        $hasil = HasilCutting::create($validated);

        $hasil->spkCutting()->update(['status_cutting' => 'selesai']);

        $statusList = [];

        // Simpan hasil markeran (actual) jika ada
        if ($request->has('hasil_markeran')) {
            // Cari produk_id dari spk_cutting
            $spkCutting = SpkCutting::with('produk')->find($validated['spk_cutting_id']);
            $produkId = $spkCutting->produk_id;

          foreach ($request->hasil_markeran as $markeran) {
            $berat_per_pcs = $markeran['total_panjang'] / $markeran['jumlah_hasil'];

            $markeranStandar = MarkeranProduk::where('produk_id', $produkId)
                ->where('nama_komponen', $markeran['nama_komponen'])
                ->first();

            if ($markeranStandar) {
                if ($berat_per_pcs > $markeranStandar->berat_per_pcs) {
                    $status = 'lebih berat';
                } elseif ($berat_per_pcs < $markeranStandar->berat_per_pcs) {
                    $status = 'lebih ringan';
                } else {
                    $status = 'sama';
                }
            } else {
                $status = 'belum ada';
            }

    // ⬅️ Tambahkan baris ini
    $statusList[] = $status;

    HasilMarkeran::create([
        'hasil_cutting_id' => $hasil->id,
        'nama_komponen' => $markeran['nama_komponen'],
        'total_panjang' => $markeran['total_panjang'],
        'jumlah_hasil' => $markeran['jumlah_hasil'],
        'berat_per_pcs' => $berat_per_pcs,
        'status_perbandingan' => $status,
    ]);
}

       
           // Hitung status agregat berdasarkan mayoritas
            $statusCount = array_count_values($statusList);

            // Ambil status terbanyak
            arsort($statusCount); // urutkan berdasarkan jumlah terbanyak
            $mostFrequentStatus = array_key_first($statusCount);

            // Cek jika ada dua status dengan jumlah sama banyak (draw)
            $jumlahTerbanyak = reset($statusCount);
            $jumlahSama = count(array_filter($statusCount, fn($val) => $val === $jumlahTerbanyak));

            if ($jumlahSama > 1) {
                $statusAgregat = 'campuran';
            } else {
                $statusAgregat = $mostFrequentStatus;
            }


            // Simpan status agregat ke hasil_cutting
            $hasil->update([
                'status_perbandingan_agregat' => $statusAgregat
            ]);
        } 
        // Simpan hasil bahan jika ada
        if ($request->has('hasil_bahan')) {
            foreach ($request->hasil_bahan as $index => $bahan) {
                try {
                    HasilCuttingBahan::create([
                        'hasil_cutting_id' => $hasil->id,
                        'spk_cutting_bahan_id' => $bahan['spk_cutting_bahan_id'],
                        'spk_cutting_bagian_id' => $bahan['spk_cutting_bagian_id'],
                        'berat' => $bahan['berat'] ?? null,
                        'hasil' => $bahan['hasil'] ?? null,
                    ]);
                } catch (\Exception $e) {
                    throw new \Exception("Gagal menyimpan hasil_bahan index ke-{$index}: " . $e->getMessage());
                }
            }
        }



        DB::commit();

        return response()->json([
            'message' => 'Hasil cutting dan markeran berhasil disimpan.',
            'data' => $hasil->load('markeran', 'bahan')
        ], \Illuminate\Http\Response::HTTP_CREATED);

        } catch (\Exception $e) {
            DB::rollBack();

           return response()->json([
            'message' => 'Gagal menyimpan data',
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'input_hasil_bahan' => $request->hasil_bahan 
            ], 500);

        }

    }


    
}