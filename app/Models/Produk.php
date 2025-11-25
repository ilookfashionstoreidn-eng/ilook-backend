<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Produk extends Model
{
    use HasFactory;
    protected $table = 'produk';

    
    protected $fillable = [
        'nama_produk',
        'kategori_produk',
        'gambar_produk',
        'jenis_produk',
        'harga_jasa_cutting',
        'harga_jasa_cmt',
        'harga_jasa_aksesoris',
        'harga_overhead',
        'hpp',
        'status_produk',

    ];

    public static function getKategoriOptions()
    {
        return ['Urgent', 'Normal']; 
    }
    public static function getJenisOptions()
    {
        return ['Gamis', 'Kaos', 'Celana']; 
    }
    public function markeranProduk()
    {
        return $this->hasMany(MarkeranProduk::class);
    }
    public function komponen()
{
    return $this->hasMany(ProdukKomponen::class, 'produk_id');
}


    
}
