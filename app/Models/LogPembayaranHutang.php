<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogPembayaranHutang extends Model
{
    use HasFactory;

    protected $table = 'log_pembayaran_hutang';
    protected $primaryKey = 'id_log_hutang';

    protected $fillable =[
        'id_hutang',
        'tanggal_bayar',
        'jumlah_dibayar',
        'catatan',
    ];

    public function hutang()
    {
        return $this->belongsTo(Hutang::class, 'id_hutang');
    }
}
