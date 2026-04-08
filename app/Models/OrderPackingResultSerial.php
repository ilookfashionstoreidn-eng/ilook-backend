<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderPackingResultSerial extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_packing_result_id',
        'serial_number',
    ];

    public function packingResult()
    {
        return $this->belongsTo(OrderPackingResult::class, 'order_packing_result_id');
    }
}
