<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Seri extends Model
{
    protected $table = 'seri';

    protected $fillable = [
        'nomor_seri',
        'sku',
        'sku_id',
        'jumlah',
        'source',
    ];

    public function skuModel()
    {
        return $this->belongsTo(Sku::class, 'sku_id');
    }
}

