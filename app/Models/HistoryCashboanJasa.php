<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistoryCashboanJasa extends Model
{
    use HasFactory;
    
    protected $table = 'history_cashboan_jasa';

    protected $fillable = [
        'cashboan_jasa_id',
        'jumlah_cashboan',
        'perubahan_cashboan',
        'jenis_perubahan',
        'bukti_transfer',
        'tanggal_perubahan',
    ];

    
    public function cashboanJasa()
    {
        return $this->belongsTo(CashboanJasa::class, 'cashboan_jasa_id');
    }
}
