<?php

namespace App\Http\Controllers;

use App\Models\StokGudangProduk;
use Illuminate\Http\Request;

class StokGudangProdukController extends Controller
{
    public function index()
    {
        $stok = StokGudangProduk::with('sku')
            ->orderBy('sku_id')
            ->get()
            ->map(function ($item) {
                return [
                    'sku_id'   => $item->sku_id,
                    'sku' => $item->sku->sku?? null,
                    'qty'      => $item->qty,
                ];
            });

        return response()->json([
            'data' => $stok
        ]);
    }
}