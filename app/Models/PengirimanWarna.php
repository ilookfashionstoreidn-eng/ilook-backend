<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PengirimanWarna extends Model
{
    use HasFactory;

    protected $table = 'pengiriman_warna';
    protected $primaryKey = 'id_pengiriman_warna';
    protected $fillable = ['id_pengiriman', 'warna', 'jumlah_dikirim', 'sisa_barang_per_warna'];

    public function pengiriman()
    {
        return $this->belongsTo(Pengiriman::class, 'id_pengiriman');
    }
}
