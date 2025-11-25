<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetailPesananAksesoris extends Model
{
    use HasFactory;

    protected $fillable = [
        'petugas_c_id',
        'aksesoris_id',
        'jumlah_dipesan',
        'total_harga'
    ];

    public function petugasC()
    {
        return $this->belongsTo(PetugasC::class);
    }
    public function petugasDVerif()
    {
        return $this->belongsTo(PetugasDVerif::class, 'petugas_d_verif_id');
    }
    

    public function aksesoris()
    {
        return $this->belongsTo(Aksesoris::class);
    }

    public function hargaTerbaru()
{
    return PembelianA::where('aksesoris_id', $this->aksesoris_id)
        ->orderBy('created_at', 'desc')
        ->first();
}
public function setTotalHargaAttribute()
{
    $hargaSatuan = $this->aksesoris ? $this->aksesoris->harga_jual : 0;
    $this->attributes['total_harga'] = $hargaSatuan * $this->jumlah_dipesan;
}



}