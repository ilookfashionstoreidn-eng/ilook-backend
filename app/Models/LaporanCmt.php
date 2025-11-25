<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LaporanCmt extends Model
{
    use HasFactory;

// Nama tabel
protected $table = 'laporan_cmt';
protected $primaryKey = 'id_laporan'; 

// Kolom yang dapat diisi
protected $fillable = [
    'id_spk',
    'tgl_pengiriman',
    'jumlah_dikirim',
    'barang_rusak',
    'barang_hilang',
    'upah_per_barang',
    'total_upah',
    'potongan',
    'cashbon',
    'status_pembayaran',
    'keterangan',
];

// Relasi ke tabel SPK
public function spk()
{
    return $this->belongsTo(SpkCmt::class, 'id_spk');
}
}