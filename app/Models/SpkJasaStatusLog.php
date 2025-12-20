<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpkJasaStatusLog extends Model
{
   use HasFactory;

    protected $table = 'spk_jasa_status_log';

    protected $fillable = [
        'spk_jasa_id',
        'status',
    ];

    public function spkJasa()
    {
        return $this->belongsTo(SpkJasa::class);
    }
}
