<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GudangProdukDetail extends Model
{
    use HasFactory;

    protected $table = 'gudang_produk_detail';

    protected $fillable = [
        'gudang_produk_id',
        'sku_id',
        'qty_acuan',
        'sku_rak',
        'foto',
    ];

    /* ================= RELATION ================= */

    // detail milik satu gudang_produk
    public function gudangProduk()
    {
        return $this->belongsTo(GudangProduk::class);
    }

    // satu detail punya satu data verifikasi
    public function verifikasi()
    {
        return $this->hasOne(GudangProdukDetailVerifikasi::class);
    }

    // optional: relasi ke SKU
    public function sku()
    {
        return $this->belongsTo(Sku::class, 'sku_id');
    }


}
