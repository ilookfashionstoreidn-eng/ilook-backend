<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PembelianBahanWarna extends Model
{
    use HasFactory;

    protected $table = 'pembelian_bahan_warna';

    protected $fillable = [
        'pembelian_bahan_id',
        'warna',
        'jumlah_rol',
    ];

    public function pembelianBahan()
    {
        return $this->belongsTo(PembelianBahan::class);
    }
    public function rol()
    {
        return $this->hasMany(PembelianBahanRol::class);
    }
}
