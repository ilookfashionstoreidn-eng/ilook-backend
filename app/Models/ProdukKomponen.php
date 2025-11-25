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
        'nama_bahan',
        'harga_bahan',
        'jumlah_bahan',
        'satuan_bahan',
        'total_harga_bahan',
    ];

     public function produk()
     {
        return $this->belongsTo(Produk::class, 'produk_id');
     }

}

