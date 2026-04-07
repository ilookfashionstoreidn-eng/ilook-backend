<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GudangProdukSlotAlias extends Model
{
    use HasFactory;

    protected $table = 'gudang_produk_slot_aliases';

    protected $fillable = [
        'layout_id',
        'slot_id',
        'alias',
    ];

    public function layout()
    {
        return $this->belongsTo(GudangProdukLayout::class, 'layout_id');
    }
}
