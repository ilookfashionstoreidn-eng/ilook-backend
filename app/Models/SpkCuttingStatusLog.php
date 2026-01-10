<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpkCuttingStatusLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'spk_cutting_id',
        'status',
        'keterangan',
        'created_at'
    ];

    public function spkCutting()
    {
        return $this->belongsTo(SpkCutting::class);
    }
}
 