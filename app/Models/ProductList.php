<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductList extends Model
{
    use HasFactory;

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
        'id_s',
        'id_m',
        'id_l',
        'id_xl',
        'ukuran',
        'pj_dress',
        'pj_celana',
        'pj_baju',
        'price_cmt',
        'price_cutting',
        'notes_spk',
        'product_list_image_id',
    ];

    protected $casts = [
        'materials' => 'array',
        'material_count' => 'integer',
        'estimasi_cutting' => 'integer',
        'estimasi_combi' => 'integer',
        'pj_dress' => 'decimal:2',
        'pj_baju' => 'decimal:2',
        'price_cmt' => 'decimal:2',
        'price_cutting' => 'decimal:2',
    ];

    public function productListImage()
    {
        return $this->belongsTo(ProductListImage::class, 'product_list_image_id');
    }
}
