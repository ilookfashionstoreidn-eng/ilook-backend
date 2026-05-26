<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpkBahan extends Model
{
    use HasFactory;

    protected $table = 'spk_bahan';

    protected $fillable = [
        'pabrik_id',
        'bahan_id',
        'jumlah',
        'jenis_pembayaran',
        'tanggal_pembayaran',
        'tanggal_pemesanan',
        'tanggal_jatuh_tempo',
        'estimasi_pengiriman',
        'status',
        'lama_pemesanan',
    ];

    // =====================
    // Relasi (opsional)
    // =====================

    public function pabrik()
    {
        return $this->belongsTo(Pabrik::class);
    }

    public function bahan()
    {
        return $this->belongsTo(Bahan::class);
    }
    public function warna()
    {
        return $this->hasMany(SpkBahanWarna::class);
    }
    public function pembelianBahan()
    {
        return $this->hasMany(PembelianBahan::class);
    }


}
