<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GudangProdukDetailVerifikasi extends Model
{
    use HasFactory;
    protected $table = 'gudang_produk_detail_verifikasi';

    protected $fillable = [
        'gudang_produk_detail_id',
        'qty_verifikasi',
        'created_by',
    ];

    /* ================= RELATION ================= */

    // verifikasi milik satu detail
    public function detail()
    {
        return $this->belongsTo(GudangProdukDetail::class, 'gudang_produk_detail_id');
    }

    // optional: user verifikator
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

}
