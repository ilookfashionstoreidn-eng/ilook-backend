<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GudangProdukLayoutRack extends Model
{
    use HasFactory;

    protected $table = 'gudang_produk_layout_racks';

    protected $fillable = [
        'uid',
        'block_id',
        'number',
        'rows',
        'label',
        'position_x',
        'position_y',
        'width_cells',
        'height_cells',
        'sort_order',
    ];

    public function block()
    {
        return $this->belongsTo(GudangProdukLayoutBlock::class, 'block_id');
    }
}
