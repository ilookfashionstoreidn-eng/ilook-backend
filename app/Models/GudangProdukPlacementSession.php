<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GudangProdukPlacementSession extends Model
{
    protected $table = 'gudang_produk_placement_sessions';

    protected $fillable = [
        'seri_id',
        'sku_id',
        'barcodes',
        'notes',
        'status',
        'created_by',
    ];

    protected $casts = [
        'barcodes' => 'array',
    ];
}
