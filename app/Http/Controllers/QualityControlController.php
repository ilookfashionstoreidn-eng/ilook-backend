<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\QualityControl;
use App\Models\QualityControlItem;

class QualityControlController extends Controller
{
    public function index()
    {
        $data = QualityControl::with('items')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $data
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'kode_seri' => 'required|string',
            'jumlah_barang_nota' => 'required|integer',
            'jumlah_diterima' => 'required|integer',
            'items' => 'required|array',
            'items.*.status' => 'required|in:lolos,reject',
            'items.*.sku' => 'required|string',
            'items.*.jumlah' => 'required|integer',
        ]);

        try {
            DB::beginTransaction();

            $qc = QualityControl::create([
                'kode_seri' => $request->kode_seri,
                'jumlah_barang_nota' => $request->jumlah_barang_nota,
                'jumlah_diterima' => $request->jumlah_diterima,
            ]);

            foreach ($request->items as $item) {
                QualityControlItem::create([
                    'quality_control_id' => $qc->id,
                    'status' => $item['status'],
                    'sku' => $item['sku'],
                    'jumlah' => $item['jumlah'],
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Data Quality Control berhasil disimpan.',
                'data' => $qc->load('items')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan saat menyimpan data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
