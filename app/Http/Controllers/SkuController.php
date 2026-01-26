<?php

namespace App\Http\Controllers;

use App\Models\Sku;
use Illuminate\Http\Request;

class SkuController extends Controller
{

 public function index(Request $request)
    {
        $query = Sku::query();
        return response()->json([
            'data' => $query->orderBy('sku')->get()
        ]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:255|unique:skus,sku',
        ]);

        $sku = Sku::create([
            'sku'       => $validated['sku'],
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'SKU berhasil ditambahkan',
            'data'    => $sku,
        ], 201);
    }
    public function update(Request $request, $id)
    {
        $sku = Sku::findOrFail($id);

        $validated = $request->validate([
            'sku'       => 'sometimes|string|max:255|unique:skus,sku,' . $sku->id,
            'is_active' => 'sometimes|boolean',
        ]);

        $sku->update($validated);

        return response()->json([
            'message' => 'SKU berhasil diperbarui',
            'data'    => $sku,
        ]);
    }
}
