<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;
    protected $table = 'order';

    protected $fillable = [
        'order_number',
        'tracking_number',
        'platform',
        'customer_name',
        'customer_phone',
        'total_amount',
        'status',
        'order_date',
        'total_qty',
        'sku',
        'is_packed',
       
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function logs()
    {
        return $this->hasMany(OrderLog::class);
    }
}
