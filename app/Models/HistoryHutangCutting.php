<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistoryHutangCutting extends Model
{
   use HasFactory;

    protected $table = 'history_hutang_cutting';

    protected $fillable = [
        'hutang_cutting_id',
        'jumlah_hutang',
        'perubahan_hutang',
        'jenis_perubahan',
        'bukti_transfer',
        'tanggal_perubahan',
    ];

    public $timestamps = false;

    // Relasi ke HutangCutting
    public function hutangCutting()
    {
        return $this->belongsTo(HutangCutting::class, 'hutang_cutting_id');
    }
}
