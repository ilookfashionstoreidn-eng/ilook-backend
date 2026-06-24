<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GudangProdukPlacementSession extends Model
{
    use HasFactory;

    protected $table = 'gudang_produk_placement_sessions';

    protected $fillable = [
        'seri_id',
        'sku_id',
        'barcodes',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'barcodes' => 'array',
    ];
}
