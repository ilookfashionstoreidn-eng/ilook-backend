<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StokBahan extends Model
{
    use HasFactory;

    protected $table = 'stok_bahan';

    protected $fillable = [
        'pembelian_bahan_id',
        'pembelian_bahan_warna_id',
        'pembelian_bahan_rol_id',
        'gudang_id',
        'pabrik_id',
        'barcode',
        'berat',
        'scanned_at',
        'status',
    ];

    public function pembelianBahan()
    {
        return $this->belongsTo(PembelianBahan::class);
    }

    public function warna()
    {
        return $this->belongsTo(PembelianBahanWarna::class, 'pembelian_bahan_warna_id');
    }

    public function rol()
    {
        return $this->belongsTo(PembelianBahanRol::class, 'pembelian_bahan_rol_id');
    }

    public function gudang()
    {
        return $this->belongsTo(Gudang::class);
    }

    public function pabrik()
    {
        return $this->belongsTo(Pabrik::class);
    }
}
