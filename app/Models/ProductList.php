<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductList extends Model
{
    use HasFactory;

    protected $table = 'product_lists';

    protected $fillable = [
        'product',
        'sku_name',
        'product_group',
        'product_size',
        'product_source',
        'product_colour',
        'materials',
        'material_count',
        'estimasi_cutting',
        'estimasi_combi',
        'ukuran',
        'pj_dress',
        'pj_celana',
        'pj_baju',
        'notes_spk',
    ];

    protected $casts = [
        'materials' => 'array',
        'material_count' => 'integer',
        'estimasi_cutting' => 'float',
        'estimasi_combi' => 'float',
        'pj_dress' => 'float',
        'pj_celana' => 'float',
        'pj_baju' => 'float',
    ];
}
