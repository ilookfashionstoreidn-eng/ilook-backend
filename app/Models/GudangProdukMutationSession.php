<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GudangProdukMutationSession extends Model
{
    protected $table = 'gudang_produk_mutation_sessions';

    protected $fillable = [
        'layout_id',
        'from_slot_id',
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
