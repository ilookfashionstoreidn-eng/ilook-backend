<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpkCuttingDistribusiDetail extends Model
{
    use HasFactory;

    protected $table = 'spk_cutting_distribusi_detail';

    protected $fillable = [
        'spk_cutting_distribusi_id',
        'warna',
        'jumlah_produk',
    ];

    public function distribusi()
    {
        return $this->belongsTo(SpkCuttingDistribusi::class, 'spk_cutting_distribusi_id');
    }
}
