<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SpkJasa;
use App\Models\SpkCutting;
use App\Models\SpkCuttingDistribusi;
use App\Models\Produk;
use App\Models\TukangJasa;
use App\Models\HasilCutting;
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

    public function preview($distribusiId)
    {
        $distribusi = SpkCuttingDistribusi::with([
            'spkCutting.produk',
            'spkCutting.tukangCutting'
        ])->findOrFail($distribusiId);

        return response()->json([
            'distribusi_id' => $distribusi->id,
            'kode_seri' => $distribusi->kode_seri,
            'jumlah' => $distribusi->jumlah_produk,
            'produk' => $distribusi->spkCutting->produk->nama_produk ?? null,
            'tukang_cutting' => $distribusi->spkCutting->tukangCutting->nama_tukang_cutting ?? null,
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
