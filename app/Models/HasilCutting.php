<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HasilCutting extends Model
{
    use HasFactory;
    protected $table = 'hasil_cutting';

    protected $fillable = [
        'spk_cutting_id',
        'spk_cutting_distribusi_id',
        'jenis_hasil',
        'foto_komponen',
        'jumlah_komponen',
        'status_perbandingan_agregat',
        'total_bayar',
        'spk_cutting_bagian_id',
        'total_hasil_pendapatan',
        'data_acuan',
        'nama_bagian',
        'nama_bahan',
        'warna',
        'qty',
        'total_produk',
        'tanggal_potong',
    ];

    protected $casts = [
        'data_acuan' => 'array',
        'status_perbandingan_agregat' => 'array',
    ];

    public function spkCutting()
    {
        return $this->belongsTo(SpkCutting::class);
    }

    public function distribusi()
    {
        return $this->hasMany(SpkCuttingDistribusi::class, 'hasil_cutting_id');
    }

    public function spkCuttingDistribusi()
    {
        return $this->belongsTo(SpkCuttingDistribusi::class, 'spk_cutting_distribusi_id');
    }

    public function markeran()
    {
        return $this->hasMany(HasilMarkeran::class);
    }

    public function bahan()
    {
        return $this->hasMany(HasilCuttingBahan::class, 'hasil_cutting_id');
    }
    public function tukangCutting()
{
    return $this->belongsTo(TukangCutting::class, 'tukang_cutting_id');
}

}
