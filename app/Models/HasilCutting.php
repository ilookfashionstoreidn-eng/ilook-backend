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
        'foto_komponen',
        'jumlah_komponen',
        'status_perbandingan_agregat',
        'total_bayar',
        'spk_cutting_bagian_id',
        'total_hasil_pendapatan',
    ];

    public function spkCutting()
    {
        return $this->belongsTo(SpkCutting::class);
    }
    
    public function markeran()
    {
        return $this->hasMany(HasilMarkeran::class);
    }
  
    public function bahan()
    {
        return $this->hasMany(HasilCuttingBahan::class, 'hasil_cutting_id');
    }


}
