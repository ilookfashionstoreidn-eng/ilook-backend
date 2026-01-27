<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GudangProduk extends Model
{
    use HasFactory;

    protected $table = 'gudang_produk';

    protected $fillable = [
        'status',
        'created_by',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    /* ================= RELATION ================= */

    // 1 gudang_produk punya banyak detail SKU
    public function details()
    {
        return $this->hasMany(GudangProdukDetail::class);
    }

    // optional: user pembuat
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // optional: user verifikator
    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
