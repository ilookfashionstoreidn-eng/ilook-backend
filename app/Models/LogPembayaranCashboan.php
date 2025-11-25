<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogPembayaranCashboan extends Model
{
    use HasFactory;

    protected $table = 'log_pembayaran_cashboan';
    // Pastikan primary key sudah diatur jika diperlukan
    protected $primaryKey = 'id_log_pembayaran'; // Jika id log berbeda dari 'id'
    protected $fillable =[
        'id_cashboan',
        'tanggal_bayar',
        'jumlah_dibayar',
        'catatan',
    ];
    public function cashboan()
    {
        return $this->belongsTo(Cashboan::class, 'id_cashboan');
    }
}
