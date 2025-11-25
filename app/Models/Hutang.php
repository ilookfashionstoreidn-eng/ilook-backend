<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hutang extends Model
{
    use HasFactory;
    protected $table = 'hutang';
    protected $primaryKey = 'id_hutang';

    protected $fillable = [
        'id_penjahit',
        'jumlah_hutang',
        'status_pembayaran',
        'tanggal_hutang',
        'jenis_hutang',
        'potongan_per_minggu',
        'is_potongan_persen', 
        'persentase_potongan',
        'bukti_transfer',
    ];

    const JENIS_HUTANG = [
        'overtime' => 'Overtime',
        'lainnya' => 'Lainnya',
    ];

    public static function getJenisHutangOptions()
    {
        return self::JENIS_HUTANG;
    }

    // Relasi ke tabel SPK
    public function penjahit()
    {
        return $this->belongsTo(Penjahit::class, 'id_penjahit', 'id_penjahit');
    }

    public function logPembayaran()
    {
        return $this->hasMany(LogPembayaranHutang::class, 'id_hutang');
    }

    public function history()
    {
        return $this->hasMany(HistoryHutang::class, 'id_hutang', 'id_hutang');
    }


}
