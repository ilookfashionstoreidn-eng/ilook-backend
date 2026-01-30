<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpkCuttingSku extends Model
{
    use HasFactory;
    protected $table = 'spk_cutting_skus';

    protected $fillable = [
        'spk_cutting_id',
        'produk_sku_id',
    ];
}
