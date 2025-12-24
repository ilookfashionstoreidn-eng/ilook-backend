<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\HasilJasa;
use App\Models\SpkJasa;

class HasilJasaController extends Controller
{
    public function index()
    {
        $data = HasilJasa::with([
            'spkJasa:id,tukang_jasa_id,spk_cutting_distribusi_id,status_pengambilan',
            'spkJasa.tukangJasa:id,nama',
            'spkJasa.spkCuttingDistribusi' => function ($q) {
                $q->select('id', 'spk_cutting_id', 'kode_seri');
            },
            'spkJasa.spkCuttingDistribusi.spkCutting' => function ($q) {
                $q->select('id', 'produk_id');
            },
            'spkJasa.spkCuttingDistribusi.spkCutting.produk:id,nama_produk'
        ])
            ->select(
                'id',
                'spk_jasa_id',
                'tanggal',
                'jumlah_hasil',
                'jumlah_rusak',
                'total_pendapatan'
            )
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($data);
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'spk_jasa_id'     => 'required|exists:spk_jasa,id',
            'tanggal'         => 'required|date',
            'jumlah_hasil'    => 'required|integer|min:0',
            'jumlah_rusak'    => 'nullable|integer|min:0',
            'bukti_transfer'  => 'nullable|file|mimes:jpg,jpeg,png,pdf',
        ]);

        $validated['jumlah_rusak'] = $validated['jumlah_rusak'] ?? 0;

        $spkJasa = SpkJasa::findOrFail($validated['spk_jasa_id']);

        // 🔹 Hitung total sebelumnya
        $totalSebelumnya = HasilJasa::where('spk_jasa_id', $spkJasa->id)
            ->selectRaw('COALESCE(SUM(jumlah_hasil + jumlah_rusak), 0) as total')
            ->value('total');

        $totalBaru = $totalSebelumnya
            + $validated['jumlah_hasil']
            + $validated['jumlah_rusak'];

        if ($totalBaru > $spkJasa->jumlah) {
            return response()->json([
                'message' => 'Total hasil melebihi jumlah SPK Jasa'
            ], 422);
        }

        // 🔹 Hitung pendapatan (hanya OK)
        $validated['total_pendapatan'] =
            $validated['jumlah_hasil'] * ($spkJasa->harga_per_pcs ?? 0);

        // 🔹 Upload bukti
        if ($request->hasFile('bukti_transfer')) {
            $validated['bukti_transfer'] =
                $request->file('bukti_transfer')->store('bukti_transfer_jasa', 'public');
        }

        $validated['status_bayar'] = 'belum_dibayar';
        $validated['pendapatan_jasa_id'] = null;


        $hasil = HasilJasa::create($validated);

        $totalOk = HasilJasa::where('spk_jasa_id', $spkJasa->id)->sum('jumlah_hasil');
        $totalRusak = HasilJasa::where('spk_jasa_id', $spkJasa->id)->sum('jumlah_rusak');

        if (($totalOk + $totalRusak) >= $spkJasa->jumlah) {
            $spkJasa->update([
                'status_pengambilan' => 'selesai'
            ]);
        }

        return response()->json([
            'message' => 'Hasil Jasa berhasil ditambahkan',
            'data' => $hasil
        ], 201);
    }
}
