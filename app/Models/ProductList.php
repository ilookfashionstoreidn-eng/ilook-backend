<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductList extends Model
{
    use HasFactory;

    protected $table = 'product_lists';

    protected $fillable = [
        'product_list_image_id',
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
        'pj_dress',
        'pj_celana',
        'pj_baju',
        'price_cmt',
        'price_cutting',
        'notes_spk',
    ];

    protected $casts = [
        'product_list_image_id' => 'integer',
        'materials' => 'array',
        'material_count' => 'integer',
        'estimasi_cutting' => 'float',
        'estimasi_combi' => 'float',
        'pj_dress' => 'float',
        'pj_baju' => 'float',
        'price_cmt' => 'float',
        'price_cutting' => 'float',
    ];

    public function productListImage()
    {
        return $this->belongsTo(ProductListImage::class);
    }
}
