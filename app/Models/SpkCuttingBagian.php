<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpkCuttingBagian extends Model
{
    use HasFactory;

    protected $table = 'spk_cutting_bagian';

    protected $fillable = [
        'spk_cutting_id',
        'nama_bagian',
       
    ];

    public function spkCutting()
    {
        return $this->belongsTo(SpkCutting::class);
    }

    public function bahan()
    {
        return $this->hasMany(SpkCuttingBahan::class, 'spk_cutting_bagian_id');
    }
}