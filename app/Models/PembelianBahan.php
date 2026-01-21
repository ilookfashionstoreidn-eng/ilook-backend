<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PembelianBahan extends Model
{
    use HasFactory;
    
    protected $table = 'pembelian_bahan';

    protected $fillable = [
        'spk_bahan_id',
        'keterangan',
        'gudang_id',
        'pabrik_id',
        'tanggal_kirim',
        'no_surat_jalan',
        'foto_surat_jalan',
        'sku',
        'harga',
        'bahan_id',
        'gramasi',
        'lebar_kain',
    ];
  public function warna()
    {
        return $this->hasMany(PembelianBahanWarna::class);
    }

    public function spkBahan()
    {
        return $this->belongsTo(SpkBahan::class);
    }

    public function bahan()
    {
        return $this->belongsTo(Bahan::class);
    }

    public function pabrik()
    {
        return $this->belongsTo(Pabrik::class);
    }

    public function gudang()
    {
        return $this->belongsTo(Gudang::class);
    }
}
