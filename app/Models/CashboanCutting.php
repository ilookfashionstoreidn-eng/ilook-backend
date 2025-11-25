<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashboanCutting extends Model
{
    use HasFactory;
    protected $table = 'cashboan_cutting';

    protected $fillable = [
        'tukang_cutting_id',
        'jumlah_cashboan',
        'status_pembayaran',
        'tanggal_cashboan',
        'bukti_transfer',

    ];

     public function tukangCutting()
    {
        return $this->belongsTo(TukangCutting::class, 'tukang_cutting_id');
    }
}
