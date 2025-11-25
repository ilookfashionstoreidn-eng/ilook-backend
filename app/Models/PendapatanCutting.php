<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PendapatanCutting extends Model
{
    use HasFactory;
    protected $table = 'pendapatan_cutting';

    protected $fillable = [
        'tukang_cutting_id',
        'total_pendapatan',
        'total_claim',
        'total_refund_claim',
        'total_cashbon',
        'total_hutang',
        'total_transfer',
        'status_pembayaran',
        'bukti_transfer',
    ];
      public function tukangCutting()
    {
        return $this->belongsTo(TukangCutting::class);
    }
    public function hasil_cutting()
{
    return $this->belongsToMany(HasilCutting::class, 'hasil_pendapatan_cutting');
}

}
