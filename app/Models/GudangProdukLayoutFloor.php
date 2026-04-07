<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GudangProdukLayoutFloor extends Model
{
    use HasFactory;

    protected $table = 'gudang_produk_layout_floors';

    protected $fillable = [
        'uid',
        'layout_id',
        'number',
        'label',
        'sort_order',
    ];

    public function layout()
    {
        return $this->belongsTo(GudangProdukLayout::class, 'layout_id');
    }

    public function blocks()
    {
        return $this->hasMany(GudangProdukLayoutBlock::class, 'floor_id');
    }
}
