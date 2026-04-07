<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GudangProdukLayoutBlock extends Model
{
    use HasFactory;

    protected $table = 'gudang_produk_layout_blocks';

    protected $fillable = [
        'uid',
        'floor_id',
        'code',
        'label',
        'layout_columns',
        'layout_canvas_columns',
        'layout_canvas_rows',
        'sort_order',
    ];

    public function floor()
    {
        return $this->belongsTo(GudangProdukLayoutFloor::class, 'floor_id');
    }

    public function racks()
    {
        return $this->hasMany(GudangProdukLayoutRack::class, 'block_id');
    }
}
