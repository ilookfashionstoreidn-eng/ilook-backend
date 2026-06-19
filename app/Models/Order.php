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
        'order_type',
        'is_packed',
        'label_print_status',
        'label_print_time',
        'picked_at',
        'shipping_deadline',
       
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function logs()
    {
        return $this->hasMany(OrderLog::class);
    }

    public function packingResults()
    {
        return $this->hasMany(OrderPackingResult::class);
    }

    public function returnLogs()
    {
        return $this->hasMany(OrderReturnLog::class);
    }
}
