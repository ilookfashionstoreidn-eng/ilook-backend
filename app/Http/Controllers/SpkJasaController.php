<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
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

    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 8);
        $search = $request->get('search');
        $status = $request->get('status');

        $query = SpkJasa::with([
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
        ]);

        // Filter by Status
        if ($status && $status !== 'all') {
            $query->where('status_pengambilan', $status);
        }

        // Filter by Search (Global Search)
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhereHas('tukangJasa', function ($q) use ($search) {
                        $q->where('nama', 'like', "%{$search}%");
                    })
                    ->orWhereHas('spkCuttingDistribusi', function ($q) use ($search) {
                        $q->where('kode_seri', 'like', "%{$search}%")
                            ->orWhereHas('spkCutting.produk', function ($q) use ($search) {
                                $q->where('nama_produk', 'like', "%{$search}%");
                            });
                    });
            });
        }

        $data = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json($data);
    }

    public function statistics()
    {
        $stats = [
            'total' => SpkJasa::count(),
            'belum_diambil' => SpkJasa::where('status_pengambilan', 'belum_diambil')->count(),
            'sudah_diambil' => SpkJasa::where('status_pengambilan', 'sudah_diambil')->count(),
            'batal_diambil' => SpkJasa::where('status_pengambilan', 'batal_diambil')->count(),
            'selesai' => SpkJasa::where('status_pengambilan', 'selesai')->count(),
        ];

        return response()->json($stats);
    }

    public function getAvailableDistributions(Request $request)
    {
        $search = $request->get('search');

        // Ambil distribusi yang belum memiliki SPK Jasa
        // Atau logika lain sesuai kebutuhan bisnis (misal: yang sudah selesai cutting)
        $query = SpkCuttingDistribusi::with(['spkCutting.produk:id,nama_produk'])
            ->doesntHave('spkJasa'); // Asumsi: relasi spkJasa ada di model SpkCuttingDistribusi

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('kode_seri', 'like', "%{$search}%")
                  ->orWhereHas('spkCutting.produk', function ($q) use ($search) {
                      $q->where('nama_produk', 'like', "%{$search}%");
                  });
            });
        }

        // Limit hasil agar tidak terlalu berat
        $data = $query->limit(20)->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'kode_seri' => $item->kode_seri,
                'jumlah_produk' => $item->jumlah_produk,
                'produk' => $item->spkCutting->produk->nama_produk ?? '-',
                'display' => $item->kode_seri . ' - ' . ($item->spkCutting->produk->nama_produk ?? '-')
            ];
        });

        return response()->json($data);
    }

    public function getForDropdown()
    {
        $spkJasa = SpkJasa::with([
            'spkCuttingDistribusi', // ⬅️ Wajib!
            'spkCuttingDistribusi.spkCutting.produk',
        ])
            ->select('id', 'spk_cutting_distribusi_id') // hanya ambil field penting
            ->get();

        return response()->json($spkJasa);
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
            'foto' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Ambil data distribusi
        $distribusi = SpkCuttingDistribusi::findOrFail(
            $validated['spk_cutting_distribusi_id']
        );

        // Jumlah dari distribusi
        $validated['jumlah'] = $distribusi->jumlah_produk;

        // Hitung harga per pcs
        if (isset($validated['harga'], $validated['opsi_harga'])) {
            $validated['harga_per_pcs'] =
                $validated['opsi_harga'] === 'lusin'
                ? round($validated['harga'] / 12, 2)
                : $validated['harga'];
        }

        // Status default SPK Jasa
        $validated['status_pengambilan'] = 'belum_diambil';

        // Handle upload foto
        if ($request->hasFile('foto')) {
            $foto = $request->file('foto');
            $fotoName = time() . '_' . $foto->getClientOriginalName();
            $foto->storeAs('public/spk_jasa', $fotoName);
            $validated['foto'] = 'spk_jasa/' . $fotoName;
        }

        // Cek apakah sudah ada SPK Jasa untuk distribusi ini
        $existingSpkJasa = SpkJasa::where('spk_cutting_distribusi_id', $validated['spk_cutting_distribusi_id'])->first();
        if ($existingSpkJasa) {
            return response()->json([
                'message' => 'SPK Jasa untuk distribusi seri ini sudah ada. Silakan gunakan distribusi seri yang lain.',
                'error' => 'duplicate'
            ], 422);
        }

        try {
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
        } catch (QueryException $e) {
            // Tangkap error duplikat dari database
            if ($e->getCode() == 23000 || str_contains($e->getMessage(), 'Duplicate entry')) {
                return response()->json([
                    'message' => 'SPK Jasa untuk distribusi seri ini sudah ada. Silakan gunakan distribusi seri yang lain.',
                    'error' => 'duplicate'
                ], 422);
            }
            throw $e;
        }
    }

    public function show($id)
    {
        $spkJasa = SpkJasa::with([
            'tukangJasa:id,nama',
            'spkCuttingDistribusi.detail.produkSku.produk',
            'spkCuttingDistribusi.spkCutting.produk',
        ])->findOrFail($id);

        // Format data untuk preview
        $distribusi = $spkJasa->spkCuttingDistribusi;
        if (!$distribusi) {
            return response()->json(['error' => 'Distribusi tidak ditemukan'], 404);
        }

        $produk = $distribusi->spkCutting->produk ?? null;
        $warna = $distribusi->detail->map(function ($d) {
            return [
                'nama_warna' => $d->warna,
                'qty' => $d->jumlah_produk,
            ];
        });

        // Ambil SKU unik dari detail distribusi
        $skus = $distribusi->detail
            ->whereNotNull('produk_sku_id')
            ->map(function ($d) {
                $sku = $d->produkSku;
                if ($sku) {
                    $namaProduk = ($sku->produk->nama_produk ?? '');
                    $warna = ($sku->warna ?? '');
                    $ukuran = ($sku->ukuran ?? '');
                    $displayText = trim(strtoupper($namaProduk . ' - ' . $warna . ' ' . $ukuran));
                    return [
                        'id' => $sku->id,
                        'sku' => $sku->sku,
                        'nama_produk' => $namaProduk,
                        'warna' => $warna,
                        'ukuran' => $ukuran,
                        'display' => $displayText,
                    ];
                }
                return null;
            })
            ->filter()
            ->unique('id')
            ->values();

        return response()->json([
            'id' => $spkJasa->id,
            'spk_cutting_distribusi_id' => $spkJasa->spk_cutting_distribusi_id,
            'kode_seri' => $distribusi->kode_seri,
            'nomor_seri' => $distribusi->kode_seri, // kode_seri bisa digunakan sebagai nomor_seri
            'nama_produk' => $produk?->nama_produk,
            'kategori_produk' => $produk?->kategori_produk,
            'gambar_produk' => $produk?->gambar_produk,
            'jumlah_produk' => $distribusi->jumlah_produk,
            'warna' => $warna,
            'tukang_jasa' => $spkJasa->tukangJasa,
            'skus' => $skus,
        ]);
    }

    public function update(Request $request, $id)
    {
        $spkJasa = SpkJasa::findOrFail($id);

        // =========================
        // VALIDATION
        // =========================
        $validated = $request->validate([
            'tukang_jasa_id' => 'sometimes|required|exists:tukang_jasa,id',
            'spk_cutting_distribusi_id' => 'sometimes|required|exists:spk_cutting_distribusi,id',
            'deadline' => 'sometimes|required|date',
            'harga' => 'sometimes|nullable|numeric|min:0',
            'opsi_harga' => 'sometimes|nullable|in:pcs,lusin',
            'tanggal_ambil' => 'sometimes|nullable|date',
            'foto' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // =========================
        // CEK DUPLIKAT DISTRIBUSI
        // =========================
        if (array_key_exists('spk_cutting_distribusi_id', $validated)) {
            $exists = SpkJasa::where('spk_cutting_distribusi_id', $validated['spk_cutting_distribusi_id'])
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'SPK Jasa untuk distribusi seri ini sudah ada.',
                    'error' => 'duplicate'
                ], 422);
            }

            // Update jumlah berdasarkan distribusi
            $distribusi = SpkCuttingDistribusi::findOrFail(
                $validated['spk_cutting_distribusi_id']
            );

            $validated['jumlah'] = $distribusi->jumlah_produk;
        }

        // =========================
        // HITUNG HARGA PER PCS (ANTI BUG FORM-DATA)
        // =========================
        $harga = array_key_exists('harga', $validated)
            ? (float) $validated['harga']
            : $spkJasa->harga;

        $opsiHarga = array_key_exists('opsi_harga', $validated)
            ? $validated['opsi_harga']
            : $spkJasa->opsi_harga;

        if ($harga !== null && $opsiHarga !== null) {
            $validated['harga_per_pcs'] =
                $opsiHarga === 'lusin'
                ? round($harga / 12, 2)
                : $harga;
        }

        // =========================
        // HANDLE UPLOAD FOTO
        // =========================
        if ($request->hasFile('foto')) {
            // Hapus foto lama
            if ($spkJasa->foto) {
                $oldPath = storage_path('app/public/' . $spkJasa->foto);
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }

            $foto = $request->file('foto');
            $namaFoto = time() . '_' . $foto->getClientOriginalName();
            $foto->storeAs('public/spk_jasa', $namaFoto);
            $validated['foto'] = 'spk_jasa/' . $namaFoto;
        }

        // =========================
        // UPDATE DATA
        // =========================
        $spkJasa->update($validated);

        return response()->json([
            'message' => 'SPK Jasa berhasil diperbarui',
            'data' => $spkJasa->fresh()->load([
                'tukangJasa:id,nama',
                'spkCuttingDistribusi',
                'spkCuttingDistribusi.spkCutting.produk:id,nama_produk',
                'statusLogs'
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
            'gambar_produk' => $distribusi->spkCutting->produk->gambar_produk ?? null,
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
