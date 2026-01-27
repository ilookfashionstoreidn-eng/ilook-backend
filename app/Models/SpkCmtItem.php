<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpkCmtItem extends Model
{
    use HasFactory;

    protected $table = 'spk_cmt_items';

    protected $fillable = [
        'spk_cmt_id',
        'sku_id',
    ];

    // ===============================
    // RELATIONS
    // ===============================

    public function spkCmt()
    {
        return $this->belongsTo(SpkCmt::class, 'spk_cmt_id', 'id_spk');
    }

    public function sku()
    {
        return $this->belongsTo(Sku::class, 'sku_id');
    }
}
