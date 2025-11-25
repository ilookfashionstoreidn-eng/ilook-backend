<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashboanJasa extends Model
{
    use HasFactory;
    protected $table = 'cashboan_jasa'; 

    protected $fillable = [
        'tukang_jasa_id',
        'jumlah_cashboan',
        'status_pembayaran',
        'tanggal_cashboan',
        'bukti_transfer',
    ];

    
    public function tukangJasa()
    {
        return $this->belongsTo(TukangJasa::class, 'tukang_jasa_id');
    }
}
