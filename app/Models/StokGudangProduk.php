<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StokGudangProduk extends Model
{
    use HasFactory;
    
    protected $table = 'stok_gudang_produk';

    protected $fillable = [
        'sku_id',
        'qty',
    ];

    /* ================= RELATION ================= */

    // stok ini milik satu SKU
    public function sku()
    {
        return $this->belongsTo(Sku::class, 'sku_id');
    }
}
