<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItemSerial extends Model
{
     use HasFactory;

    protected $fillable = [
        'order_item_id',
        'serial_number'
    ];

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }
}