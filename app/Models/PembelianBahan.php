<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PembelianBahan extends Model
{
    use HasFactory;

    protected $table = 'pembelian_bahan';

    protected $fillable = [
        'keterangan',
        'gudang_id',
        'pabrik_id',
        'tanggal_kirim',
        'no_Surat_jalan',
        'foto_surat_jalan',
        'nama_bahan',
        'gramasi',
        'satuan',
        'lebar_kain',
    ];

    public function warna()
    {
        return $this->hasMany(PembelianBahanWarna::class);
    }

    public function gudang()
    {
        return $this->belongsTo(Gudang::class);
    }

    public function pabrik()
    {
        return $this->belongsTo(Pabriak::class);
    }


}
