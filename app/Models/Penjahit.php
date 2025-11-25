<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Penjahit extends Model
{
    use HasFactory;
    protected $table = 'penjahit_cmt'; // Nama tabel
    protected $primaryKey = 'id_penjahit'; // Primary key

    protected $casts = [
        'mesin' => 'array',
    ];
    
    protected $fillable = [
        'nama_penjahit',
        'kontak',
        'alamat',
        'ktp',
        'kategori_penjahit',
        'jumlah_tim',
        'no_rekening',
        'bank',
        'mesin', 
    ];

    // Relasi ke tabel SPK
    public function spk()
    {
        return $this->hasMany(SpkCmt::class, 'id_penjahit');
    }
    public function user()
    {
        return $this->hasOne(User::class, 'id_penjahit', 'id_penjahit');
    }

    
    public function getPendapatanMingguIni()
    {
        $periodeAwal = now()->startOfWeek();
        $periodeAkhir = now()->endOfWeek();

        return Pengiriman::join('spk_cmt', 'pengiriman.id_spk', '=', 'spk_cmt.id_spk')
            ->where('spk_cmt.id_penjahit', $this->id_penjahit)
            ->whereBetween('pengiriman.tanggal_pengiriman', [$periodeAwal, $periodeAkhir])
            ->selectRaw('
                SUM(pengiriman.total_bayar) as total_pendapatan,
                SUM(pengiriman.claim) as total_claim,
                SUM(pengiriman.refund_claim) as total_refund_claim
            ')
            ->first();
    }


}
