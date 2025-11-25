<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SpkJasa;
use App\Models\SpkCutting;
use App\Models\Produk;
use App\Models\TukangJasa;
use App\Models\HasilCutting;

class SpkJasaController extends Controller
{

  public function index()
{
    $data = SpkJasa::with([
        'produk' => function ($q) {
            $q->select('produk.id', 'produk.nama_produk');
        },
        'tukangJasa:id,nama',
        'spkCutting:id,id_spk_cutting'
    ])->get();

    $data->transform(function ($item) {
        $item->total_hasil_pendapatan = optional(
            HasilCutting::where('spk_cutting_id', $item->spk_cutting_id)->first()
        )->total_hasil_pendapatan ?? 0;

        return $item;
    });

    return response()->json($data);
}



    public function store(Request $request)
    {
        $validated = $request->validate([
            'tukang_jasa_id' => 'required|exists:tukang_jasa,id',
            'spk_cutting_id' => 'required|exists:spk_cutting,id',
            'deadline' => 'required|date|after_or_equal:today',
            'harga' => 'nullable|numeric|min:0',
            'opsi_harga' => 'nullable|in:pcs,lusin',
            'tanggal_ambil' => 'nullable|date',
        ]);

        
        if (!isset($validated['status'])) {
            $validated['status'] = 'proses';
        }
         $jumlah = HasilCutting::where('spk_cutting_id', $validated['spk_cutting_id'])
                ->sum('total_hasil_pendapatan');
         $validated['jumlah'] = $jumlah;

         $validated['harga_per_pcs'] = $validated['opsi_harga'] === 'lusin'
            ? round($validated['harga'] / 12, 2)
            : $validated['harga'];

        $validated['status_jasa'] = 'in progress';

    
        $jasa = SpkJasa::create($validated);

        return response()->json([
            'message' => 'SPK Jasa berhasil ditambahkan.',
            'data' => $jasa
        ], 201);
    }
}
