<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cashboan extends Model
{
    use HasFactory;

    protected $table = 'cashboan'; 
    protected $primaryKey = 'id_cashboan';

    protected $fillable = [
       'id_penjahit',
        'jumlah_cashboan',
        'status_pembayaran',
        'tanggal_jatuh_tempo',
        'tanggal_cashboan',
        'potongan_per_minggu',
        'bukti_transfer'
    ];

    // Relasi ke tabel SPK
    public function penjahit()
    {
        return $this->belongsTo(Penjahit::class, 'id_penjahit', 'id_penjahit');
    }

    public function logPembayaran()
    {
        return $this->hasMany(LogPembayaranCashboan::class, 'id_cashboan');
    }
}
