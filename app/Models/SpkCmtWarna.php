<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpkCmtWarna extends Model
{
   protected $table = 'spk_cmt_warna';

    protected $fillable = [
        'spk_cmt_id',
        'nama_warna',
        'qty',
    ];

    public function spkCmt()
    {
        return $this->belongsTo(SpkCmt::class, 'spk_cmt_id', 'id_spk');
    }
}
