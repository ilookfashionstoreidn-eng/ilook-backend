<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HasilJasa extends Model
{
    use HasFactory;

    protected $table = 'hasil_jasa';

    protected $fillable = [
        'spk_jasa_id',
        'tanggal',
        'jumlah_hasil',
        'total_pendapatan',
        'bukti_transfer',
    ];

    public function spkJasa()
    {
        return $this->belongsTo(SpkJasa::class);
    }
} 
