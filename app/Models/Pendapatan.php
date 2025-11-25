<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pendapatan extends Model
{
    use HasFactory;
    protected $table = 'pendapatan';
    protected $primaryKey = 'id_pendapatan';

    protected $fillable = [
        'id_penjahit',
        'periode_awal',
        'periode_akhir',
        'total_pendapatan',
        'total_claim',
        'total_refund_claim',
        'total_cashbon',
        'total_hutang',
        'potongan_aksesoris',
        'handtag',
        'transportasi',
        'total_transfer',
        'status_pembayaran',
        'bukti_transfer'
    ];

    // Relasi ke Penjahit
    public function penjahit()
    {
        return $this->belongsTo(Penjahit
         ::class, 'id_penjahit');
    }
    

    public function pengiriman()
    {
        return $this->belongsToMany(Pengiriman::class, 'pengiriman_pendapatan', 'id_pendapatan', 'id_pengiriman');
    }
    
}
