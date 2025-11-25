<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistoryCashboan extends Model
{
    use HasFactory;

    protected $table = 'history_cashboan';
    protected $primaryKey = 'id_history';
    public $timestamps = false; 

    protected $fillable = [
        'id_cashboan',
        'jumlah_cashboan',
        'perubahan_cashboan',
        'jenis_perubahan',
        'tanggal_perubahan',
        'potongan_per_minggu',
        'bukti_transfer',
    ];

    // Relasi ke Hutang
    public function cashboan()
    {
        return $this->belongsTo(Cashboan::class, 'id_cashboan', 'id_cashboan');
    }
}