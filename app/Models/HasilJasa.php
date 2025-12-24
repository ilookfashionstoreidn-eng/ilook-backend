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
        'jumlah_rusak', 
        'total_pendapatan',
        'bukti_transfer',
        'status_bayar',
        'pendapatan_jasa_id',
    ];

    public function spkJasa()
    {
        return $this->belongsTo(SpkJasa::class);
    }
    public function pendapatanJasa()
    {
        return $this->belongsTo(PendapatanJasa::class);
    }


} 
