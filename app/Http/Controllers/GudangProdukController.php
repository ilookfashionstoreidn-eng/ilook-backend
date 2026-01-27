<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\GudangProduk;
use App\Models\GudangProdukDetail;
use App\Models\GudangProdukDetailVerifikasi;
use Illuminate\Support\Facades\DB;

class GudangProdukController extends Controller
{
    public function index()
    {
        $gudangProduk = GudangProduk::with(['details.sku', 'details.verifikasi', 'creator', 'verifier'])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'data' => $gudangProduk,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.sku_id' => 'required|integer',
            'items.*.qty' => 'required|integer|min:1',
        ]);

        $gudangProduk = DB::transaction(function () use ($request) {
            // 1. buat header
            $gudangProduk = GudangProduk::create([
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);

            // 2. simpan detail (multi SKU)
            foreach ($request->items as $item) {
                GudangProdukDetail::create([
                    'gudang_produk_id' => $gudangProduk->id,
                    'sku_id' => $item['sku_id'],
                    'qty_acuan' => $item['qty'],
                ]);
            }

            return $gudangProduk;
        });

        // Load relasi untuk response
        $gudangProduk->load(['details.sku', 'creator']);

        return response()->json([
            'message' => 'Data gudang produk berhasil disimpan (draft)',
            'data' => $gudangProduk,
        ], 201);
    }

        public function verify(Request $request, $id)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.detail_id' => 'required|integer|exists:gudang_produk_detail,id',
            'items.*.qty' => 'required|integer|min:0',
        ]);

        $gudangProduk = GudangProduk::with('details')->findOrFail($id);

        // tidak boleh verifikasi data yang sudah sah
        if ($gudangProduk->status === 'terverifikasi') {
            return response()->json([
                'message' => 'Data sudah terverifikasi'
            ], 400);
        }

        DB::transaction(function () use ($request, $gudangProduk) {

            $semuaSesuai = true;

            foreach ($request->items as $item) {
                $detail = $gudangProduk->details
                    ->where('id', $item['detail_id'])
                    ->first();

                if (!$detail) {
                    $semuaSesuai = false;
                    break;
                }

                // simpan data verifikasi
                GudangProdukDetailVerifikasi::updateOrCreate(
                    ['gudang_produk_detail_id' => $detail->id],
                    [
                        'qty_verifikasi' => $item['qty'],
                        'created_by' => auth()->id(),
                    ]
                );

                // bandingkan
                if ($detail->qty_acuan != $item['qty']) {
                    $semuaSesuai = false;
                }
            }

            // kalau semua cocok → sahkan
            if ($semuaSesuai) {
                $gudangProduk->update([
                    'status' => 'terverifikasi',
                    'verified_by' => auth()->id(),
                    'verified_at' => now(),
                ]);
            }
        });

        // Load relasi untuk response
        $gudangProduk->load(['details.sku', 'details.verifikasi', 'creator', 'verifier']);

        return response()->json([
            'message' => 'Proses verifikasi selesai',
            'status' => $gudangProduk->fresh()->status,
            'data' => $gudangProduk->fresh(['details.sku', 'details.verifikasi', 'creator', 'verifier']),
        ]);
    }
}


