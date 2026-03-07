<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QualityControlItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'quality_control_id',
        'status',
        'sku',
        'jumlah',
    ];

    public function qualityControl()
    {
        return $this->belongsTo(QualityControl::class);
    }
}
