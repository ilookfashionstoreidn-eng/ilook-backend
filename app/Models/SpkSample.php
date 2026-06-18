<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpkSample extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama_sample',
        'kategori_sample',
        'detail',
        'keterangan_sample',
        'foto',
        'tukang_sample_id',
        'bahan_utama',
        'bahan_kombinasi',
        'aksesoris',
        'warna_yang_akan_dikeluarkan',
        'harga_potong',
        'harga_cmt',
    ];

    protected $casts = [
        'bahan_utama' => 'array',
        'bahan_kombinasi' => 'array',
        'aksesoris' => 'array',
        'warna_yang_akan_dikeluarkan' => 'array',
    ];

    public function tukangSample()
    {
        return $this->belongsTo(TukangSample::class, 'tukang_sample_id');
    }
}
