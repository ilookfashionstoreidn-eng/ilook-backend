<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PembelianBahanRol extends Model
{
    use HasFactory;

    protected $table = 'pembelian_bahan_rol';

    protected $fillable = [
        'pembelian_bahan_warna_id',
        'berat',
        'barcode',
        'status',
    ];

    public function warna()
    {
        return $this->belongsTo(PembelianBahanWarna::class);
    }
}
