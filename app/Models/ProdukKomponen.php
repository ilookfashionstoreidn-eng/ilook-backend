<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProdukKomponen extends Model
{
    use HasFactory;

     protected $table = "produk_komponen";

     protected $fillable = [
        'produk_id',
        'jenis_komponen',
        'sumber_komponen',
        'bahan_id',
        'aksesoris_id',
        'harga_bahan',
        'jumlah_bahan',
        'total_harga_bahan',
    ];

    public function produk()
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }

    public function bahan()
    {
        return $this->belongsTo(Bahan::class, 'bahan_id');
    }

    public function aksesoris()
    {
        return $this-belongsTo(Aksesoris::class, 'aksesoris_id');
    }
}

