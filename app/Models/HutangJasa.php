<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HutangJasa extends Model
{
    use HasFactory;
     protected $table = 'hutang_jasa';

    protected $fillable = [
        'tukang_jasa_id',
        'jumlah_hutang',
        'status_pembayaran',
        'tanggal_hutang',
        'potongan_per_minggu',
        'is_potongan_persen',
        'persentase_potongan',
        'bukti_transfer',
    ];

    public function tukangJasa()
    {
        return $this->belongsTo(TukangJasa::class);
    }
}
