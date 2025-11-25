<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistoryCashboanCutting extends Model
{
    use HasFactory;
    protected $table = 'history_cashboan_cutting';

    protected $fillable = [
        'cashboan_cutting_id',
        'jumlah_cashboan',
        'perubahan_cashboan',
        'jenis_perubahan',
        'tanggal_perubahan',
        'bukti_transfer',
    ];

    // Relasi ke CashboanCutting
    public function cashboanCutting()
    {
        return $this->belongsTo(CashboanCutting::class, 'cashboan_cutting_id');
    }
}
