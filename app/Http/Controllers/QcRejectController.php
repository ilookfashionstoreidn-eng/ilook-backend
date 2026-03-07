<?php

namespace App\Http\Controllers;

use App\Models\QcScanReject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QcRejectController extends Controller
{
    /**
     * Ambil data reject yang di-group by nomor_seri + sku.
     */
    public function index()
    {
        $grouped = DB::table('qc_scan_reject')
            ->select(
                'nomor_seri',
                'sku',
                DB::raw('SUM(jumlah) as total_jumlah'),
                DB::raw('COUNT(*) as total_input'),
                DB::raw('MAX(created_at) as last_input')
            )
            ->groupBy('nomor_seri', 'sku')
            ->orderBy('last_input', 'desc')
            ->get();

        return response()->json([
            'data' => $grouped,
            'total_item' => (int) $grouped->sum('total_jumlah'),
            'total_input' => (int) DB::table('qc_scan_reject')->count(),
        ]);
    }

    /**
     * Simpan satu input data reject.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nomor_seri' => 'required|string|max:255',
            'sku' => 'required|string|max:255',
            'jumlah' => 'required|integer|min:1',
        ]);

        $nomorSeri = trim($validated['nomor_seri']);
        $sku = strtoupper(trim($validated['sku']));
        $jumlah = (int) $validated['jumlah'];

        if ($nomorSeri === '' || $sku === '') {
            return response()->json([
                'message' => 'Nomor seri dan SKU wajib diisi.',
            ], 422);
        }

        $row = QcScanReject::create([
            'nomor_seri' => $nomorSeri,
            'sku' => $sku,
            'jumlah' => $jumlah,
        ]);

        $summary = DB::table('qc_scan_reject')
            ->where('nomor_seri', $nomorSeri)
            ->where('sku', $sku)
            ->select(
                DB::raw('SUM(jumlah) as total_jumlah'),
                DB::raw('COUNT(*) as total_input')
            )
            ->first();

        return response()->json([
            'message' => 'Data reject berhasil ditambahkan.',
            'id' => $row->id,
            'nomor_seri' => $nomorSeri,
            'sku' => $sku,
            'jumlah_terakhir' => $jumlah,
            'total_jumlah' => (int) $summary->total_jumlah,
            'total_input' => (int) $summary->total_input,
        ], 201);
    }
}
