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
        'bukti_transfer',
        'kurangi_hutang',
        'kurangi_cashbon',
        'detail_aksesoris_ids',
        'claim_ids'
    ];

    protected $casts = [
        'kurangi_hutang' => 'boolean',
        'kurangi_cashbon' => 'boolean',
        'detail_aksesoris_ids' => 'array',
        'claim_ids' => 'array',
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
                                                                                                                                                                                                                                                          