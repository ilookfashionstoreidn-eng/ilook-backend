<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;



    protected $fillable = [
        'order_id',
        'sku',
        'product_name',
        'quantity',
        'price',
        'image',
        'nomor_seri',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
    public function serials()
    {
        return $this->hasMany(OrderItemSerial::class);
    }

}
