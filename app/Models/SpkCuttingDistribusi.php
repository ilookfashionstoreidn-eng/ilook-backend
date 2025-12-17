<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpkCuttingDistribusi extends Model
{
   use HasFactory;

    protected $table = 'spk_cutting_distribusi';

    protected $fillable = [
        'spk_cutting_id',
        'kode_seri',
        'no_seri',
        'jumlah_produk',
        'status',
    ];

    public function spkCutting()
    {
        return $this->belongsTo(SpkCutting::class);
    }

    public function spkCmt()
    {
        return $this->hasOne(SpkCmt::class);
    }
}
