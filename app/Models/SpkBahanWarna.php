<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpkBahanWarna extends Model
{
    use HasFactory;

    protected $table = 'spk_bahan_warna';

    protected $fillable = [
        'spk_bahan_id',
        'warna',
        'jumlah_rol',
    ];

    /**
     * Relasi ke header SPK Bahan
     */
    public function spkBahan()
    {
        return $this->belongsTo(SpkBahan::class);
    }
    
}
