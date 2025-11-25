<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Aksesoris extends Model
{
    use HasFactory;
    
    protected $table ='aksesoris';

    protected $fillable =[
        'nama_aksesoris',
        'jenis_aksesoris',
        'satuan',
        'harga_jual',
        'foto_aksesoris',
    ];

    const SATUAN_AKSESORIS = [
        'pcs' => 'Pcs',
        'pack' => 'Pack',
        'lusin' => 'Lusin',
        'kodi' => 'Kodi',
        'roll' => 'Roll',
        'gross' => 'Gross',
    ];

    
    const JENIS_AKSESORIS  = [
        'handtag' => 'Handtag',
        'renda' => 'Renda',
       
    ];


    
    public static function getSatuanAksesorisOptions()
    {
        return self::SATUAN_AKSESORIS;
    }

    public static function getJenisAksesorisOptions()
    {
        return self::JENIS_AKSESORIS;
    }

    public function pembelianA()
    {
        return $this->hasMany(PembelianA::class, 'aksesoris_id');
    }
    public function stokAksesoris()
    {
        return $this->hasMany(StokAksesoris::class, 'aksesoris_id');
    }
    protected $appends = ['jumlah_stok'];

    public function getJumlahStokAttribute()
    {
        return $this->stokAksesoris()->where('status', 'tersedia')->count();
    }



}
