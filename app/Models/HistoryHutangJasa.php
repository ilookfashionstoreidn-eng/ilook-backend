<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistoryHutangJasa extends Model
{
    use HasFactory;

    protected $table = 'history_hutang_jasa';

    protected $fillable = [
        'hutang_jasa_id',
        'jumlah_hutang',
        'perubahan_hutang',
        'jenis_perubahan',
        'bukti_transfer',
        'tanggal_perubahan',
    ];

    public $timestamps = false;

   
    public function hutangJasa()
    {
        return $this->belongsTo(HutangJasa::class, 'hutang_jasa_id');
    }
}

