<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogStatusSpkCmt extends Model
{
   protected $table = 'log_status_spk_cmt';

    protected $fillable = [
        'spk_cmt_id',
        'status',
        'keterangan',
    ];

    public function spkCmt()
    {
        return $this->belongsTo(
            SpkCmt::class,
            'spk_cmt_id',
            'id_spk'
        );
    }
}
