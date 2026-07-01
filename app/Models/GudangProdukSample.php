<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GudangProdukSample extends Model
{
    use HasFactory;

    protected $table = 'gudang_produk_samples';

    protected $fillable = [
        'sku_id',
        'qty',
        'peminjam',
        'tujuan',
        'status',
        'tanggal_pinjam',
        'tanggal_kembali',
        'created_by',
    ];

    protected $casts = [
        'tanggal_pinjam' => 'datetime',
        'tanggal_kembali' => 'datetime',
    ];

    public function sku()
    {
        return $this->belongsTo(Sku::class, 'sku_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
