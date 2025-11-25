<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class PetugasC extends Model
{
    use HasFactory;

    protected $table = 'petugas_c';

    protected $fillable = [
        'user_id',
        'penjahit_id',
        'jumlah_dipesan',
        'status',
        'total_harga',
    ];

    protected $appends = ['jumlah_jenis_aksesoris'];


    
    public function detailPesanan()
    {
        return $this->hasMany(DetailPesananAksesoris::class);
    }

    // Accessor untuk menghitung jumlah jenis aksesoris
    public function getJumlahJenisAksesorisAttribute()
    {
        return $this->detailPesanan->count(); // hitung jumlah item di detail_pesanan
    }


    public function user()
    { 
        return $this->belongsTo(User::class);
    }

   

    public function penjahit()
    {
        return $this->belongsTo(Penjahit::class, 'penjahit_id', 'id_penjahit');
    }
    public function petugasDVerif()
{
    return $this->hasOne(PetugasDVerif::class, 'petugas_c_id');
}


}
