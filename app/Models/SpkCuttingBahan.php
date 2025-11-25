<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpkCuttingBahan extends Model
{
   use HasFactory;

    protected $table = 'spk_cutting_bahan';

    protected $fillable = [
        'spk_cutting_bagian_id',
        'nama_bahan',
        'qty',
        'berat',
    ];

    public function bagian()
    {
        return $this->belongsTo(SpkCuttingBagian::class, 'spk_cutting_bagian_id');
    }
}