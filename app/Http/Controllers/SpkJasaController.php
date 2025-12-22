<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SpkJasa;
use App\Models\SpkCutting;
use App\Models\SpkCuttingDistribusi;
use App\Models\Produk;
use App\Models\TukangJasa;
use App\Models\HasilCutting;
use App\Models\HasilCuttingBahan;
use App\Models\SpkJasaStatusLog;


class SpkJasaController extends Controller
{

    public function index()
    {
        $data = SpkJasa::with([
            'tukangJasa:id,nama',
            'spkCuttingDistribusi' => function ($q) {
                $q->select(
                    'id',
                    'spk_cutting_id',
                    'jumlah_produk',
                    'kode_seri'
                );
            },
            'spkCuttingDistribusi.spkCutting' => function ($q) {
                $q->select('id', 'produk_id');
            },
            'spkCuttingDistribusi.spkCutting.produk:id,nama_produk'

        ])->get();

        return response()->json($data);
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'tukang_jasa_id' => 'required|exists:tukang_jasa,id',
            'spk_cutting_distribusi_id' => 'required|exists:spk_cutting_distribusi,id',
            'deadline' => 'required|date|after_or_equal:today',
            'harga' => 'nullable|numeric|min:0',
            'opsi_harga' => 'nullable|in:pcs,lusin',
            'tanggal_ambil' => 'nullable|date',
        ]);

        // Ambil data distribusi
        $distribusi = SpkCuttingDistribusi::findOrFail(
            $validated['spk_cutting_distribusi_id']
        );

        // Jumlah dari distribusi
        $validated['jumlah'] = $distribusi->jumlah_produk;

        // Hitung harga per pcs
        if (!empty($validated['harga']) && !empty($validated['opsi_harga'])) {
            $validated['harga_per_pcs'] = $validated['opsi_harga'] === 'lusin'
                ? round($validated['harga'] / 12, 2)
                : $validated['harga'];
        }

        // Status default SPK Jasa

        $validated['status_pengambilan'] = 'belum_diambil';

        // Simpan SPK Jasa
        $jasa = SpkJasa::create($validated);

        // 🔹 INSERT LOG STATUS PERTAMA
        SpkJasaStatusLog::create([
            'spk_jasa_id' => $jasa->id,
            'status' => 'belum_diambil',

        ]);

        return response()->json([
            'message' => 'SPK Jasa berhasil ditambahkan',
            'data' => $jasa->load([
                'spkCuttingDistribusi',
                'statusLogs'
            ])
        ], 201);
    }

    public function show($id)
    {
        $spkJasa = SpkJasa::with([
            'tukangJasa:id,nama',
            'spkCuttingDistribusi' => function ($q) {
                $q->select(
                    'id',
                    'spk_cutting_id',
                    'jumlah_produk',
                    'kode_seri'
                );
            },
            'spkCuttingDistribusi.spkCutting' => function ($q) {
                $q->select('id', 'produk_id');
            },
            'spkCuttingDistribusi.spkCutting.produk:id,nama_produk'
        ])->findOrFail($id);

        return response()->json($spkJasa);
    }

    public function update(Request $request, $id)
    {
        $spkJasa = SpkJasa::findOrFail($id);

        $validated = $request->validate([
            'tukang_jasa_id' => 'required|exists:tukang_jasa,id',
            'spk_cutting_distribusi_id' => 'required|exists:spk_cutting_distribusi,id',
            'deadline' => 'required|date',
            'harga' => 'nullable|numeric|min:0',
            'opsi_harga' => 'nullable|in:pcs,lusin',
            'tanggal_ambil' => 'nullable|date',
        ]);

        // Ambil data distribusi
        $distribusi = SpkCuttingDistribusi::findOrFail(
            $validated['spk_cutting_distribusi_id']
        );

        // Jumlah dari distribusi
        $validated['jumlah'] = $distribusi->jumlah_produk;

        // Hitung harga per pcs
        if (!empty($validated['harga']) && !empty($validated['opsi_harga'])) {
            $validated['harga_per_pcs'] = $validated['opsi_harga'] === 'lusin'
                ? round($validated['harga'] / 12, 2)
                : $validated['harga'];
        } else {
            // Jika harga atau opsi_harga kosong, set harga_per_pcs ke null
            $validated['harga_per_pcs'] = null;
        }

        // Update SPK Jasa
        $spkJasa->update($validated);

        return response()->json([
            'message' => 'SPK Jasa berhasil diperbarui',
            'data' => $spkJasa->load([
                'tukangJasa:id,nama',
                'spkCuttingDistribusi',
                'spkCuttingDistribusi.spkCutting.produk:id,nama_produk'
            ])
        ]);
    }

    public function preview($distribusiId)
    {
        $distribusi = SpkCuttingDistribusi::with([
            'spkCutting.produk',
            'spkCutting.tukangCutting',
            'hasilCutting.bahan.spkCuttingBahan'
        ])->findOrFail($distribusiId);

        // Hitung jumlah produk per warna dari total_produk di hasil_cutting_bahan
        $jumlahPerWarna = [];

        // Cek apakah hasilCutting ada dan hasil_cutting_id terisi
        if ($distribusi->hasil_cutting_id && $distribusi->hasilCutting) {
            // Reload bahan jika belum ter-load dengan benar
            $bahanList = HasilCutting::with(['bahan.spkCuttingBahan'])
                ->find($distribusi->hasil_cutting_id);

            if ($bahanList && $bahanList->bahan && $bahanList->bahan->count() > 0) {
                foreach ($bahanList->bahan as $bahan) {
                    // Ambil warna dari spkCuttingBahan
                    $warna = null;
                    if ($bahan->spkCuttingBahan) {
                        $warna = $bahan->spkCuttingBahan->warna;
                    }

                    // Gunakan total_produk sebagai prioritas utama (sesuai dengan yang ditampilkan di tabel Data Hasil)
                    // Jika total_produk null, coba hasil, jika masih null gunakan 0
                    $jumlah = $bahan->total_produk ?? $bahan->hasil ?? 0;

                    if ($warna && $jumlah > 0) {
                        if (!isset($jumlahPerWarna[$warna])) {
                            $jumlahPerWarna[$warna] = 0;
                        }
                        $jumlahPerWarna[$warna] += (int) $jumlah;
                    }
                }
            }
        }

        // Format menjadi array untuk response
        $jumlahPerWarnaFormatted = [];
        foreach ($jumlahPerWarna as $warna => $jumlah) {
            $jumlahPerWarnaFormatted[] = [
                'warna' => $warna,
                'jumlah' => $jumlah
            ];
        }

        // Debug info untuk troubleshooting
        $debugInfo = [
            'hasil_cutting_id' => $distribusi->hasil_cutting_id,
            'has_hasil_cutting' => $distribusi->hasilCutting ? true : false,
        ];

        if ($distribusi->hasilCutting) {
            // Coba reload bahan langsung dari model
            $bahanCount = HasilCuttingBahan::where('hasil_cutting_id', $distribusi->hasil_cutting_id)->count();
            $debugInfo['bahan_count_direct'] = $bahanCount;
            $debugInfo['bahan_count_relation'] = $distribusi->hasilCutting->bahan ? $distribusi->hasilCutting->bahan->count() : 0;
        } else {
            $debugInfo['bahan_count_direct'] = 0;
            $debugInfo['bahan_count_relation'] = 0;
        }

        return response()->json([
            'distribusi_id' => $distribusi->id,
            'kode_seri' => $distribusi->kode_seri,
            'jumlah' => $distribusi->jumlah_produk,
            'produk' => $distribusi->spkCutting->produk->nama_produk ?? null,
            'tukang_cutting' => $distribusi->spkCutting->tukangCutting->nama_tukang_cutting ?? null,
            'jumlah_per_warna' => $jumlahPerWarnaFormatted,
            'debug' => $debugInfo
        ]);
    }

    public function updateStatusPengambilan(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:belum_diambil,sudah_diambil,batal_diambil,selesai',

        ]);

        $spkJasa = SpkJasa::findOrFail($id);

        // ❌ Kalau status sama, tidak perlu update
        if ($spkJasa->status_pengambilan === $validated['status']) {
            return response()->json([
                'message' => 'Status sudah sama, tidak ada perubahan'
            ], 422);
        }

        /**
         * OPTIONAL: RULE TRANSISI STATUS
         * (boleh kamu hapus kalau belum mau strict)
         */
        $allowedTransitions = [
            'belum_diambil' => ['sudah_diambil', 'batal_diambil'],
            'sudah_diambil' => ['selesai'],
            'batal_diambil' => [],
            'selesai' => [],
        ];

        if (!in_array(
            $validated['status'],
            $allowedTransitions[$spkJasa->status_pengambilan] ?? []
        )) {
            return response()->json([
                'message' => 'Perubahan status tidak valid'
            ], 422);
        }

        // 🔹 Update status terkini
        $spkJasa->update([
            'status_pengambilan' => $validated['status']
        ]);

        // 🔹 Simpan log status
        SpkJasaStatusLog::create([
            'spk_jasa_id' => $spkJasa->id,
            'status' => $validated['status'],
        ]);

        return response()->json([
            'message' => 'Status pengambilan berhasil diperbarui',
            'data' => $spkJasa->load('statusLogs')
        ]);
    }
}
