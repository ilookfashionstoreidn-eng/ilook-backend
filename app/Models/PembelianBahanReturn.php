<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PembelianBahanReturn extends Model
{
    use HasFactory;

    protected $table = 'pembelian_bahan_return';

    protected $fillable = [
        'pembelian_bahan_id',
        'pembelian_bahan_rol_id',
        'tipe_return',
        'jumlah_rol',
        'total_refund',
        'keterangan',
        'tanggal_return',
        'status',
        'foto_bukti',
    ];

    protected $casts = [
        'tanggal_return' => 'date',
        'total_refund' => 'decimal:2',
    ];

    // Relasi
    public function pembelianBahan()
    {
        return $this->belongsTo(PembelianBahan::class);
    }

    public function rol()
    {
        return $this->belongsTo(PembelianBahanRol::class, 'pembelian_bahan_rol_id');
    }
}
