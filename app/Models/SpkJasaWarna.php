<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpkJasaWarna extends Model
{
    protected $table = 'spk_jasa_warna';

    protected $fillable = [
        'spk_jasa_id',
        'warna',
        'qty',
    ];

    public function spkJasa()
    {
        return $this->belongsTo(SpkJasa::class);
    }
}
