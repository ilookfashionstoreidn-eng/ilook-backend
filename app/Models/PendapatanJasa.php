<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PendapatanJasa extends Model
{
    use HasFactory;
    protected $table = 'pendapatan_jasa';

    protected $fillable = [
        'tukang_jasa_id',
        'total_pendapatan',
        'total_claim',
        'total_refund_claim',
        'total_cashbon',
        'total_hutang',
        'total_transfer',
        'status_pembayaran',
        'bukti_transfer',
    ];
      public function tukangJasa()
    {
        return $this->belongsTo(TukangJasa::class);
    }
   
}
