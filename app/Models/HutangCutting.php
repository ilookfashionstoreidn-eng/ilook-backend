<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HutangCutting extends Model
{
    use HasFactory;

    protected $table = 'hutang_cutting';

    protected $fillable = [
        'tukang_cutting_id',
        'jumlah_hutang',
        'status_pembayaran',
        'tanggal_hutang',
        'potongan_per_minggu',
        'is_potongan_persen',
        'persentase_potongan',
        'bukti_transfer',
    ];

    public function tukangCutting()
    {
        return $this->belongsTo(TukangCutting::class);
    }
}
