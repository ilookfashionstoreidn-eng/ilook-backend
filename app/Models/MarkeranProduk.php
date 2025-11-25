<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarkeranProduk extends Model
{
    use HasFactory;
    protected $table = 'markeran_produk' ;

    protected $fillable = [
        'produk_id',
        'nama_komponen',
        'total_panjang',
        'jumlah_hasil',
        'berat_per_pcs',
    ];
     public function produk()
    {
        return $this->belongsTo(Produk::class);
    }

}
