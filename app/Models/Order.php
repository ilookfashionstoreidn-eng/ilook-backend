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
        'source',
        'ginee_order_id',
        'logistic_provider_name',
        'customer_address',
        'customer_city',
        'customer_province',
        'customer_zip_code',
        'shipping_fee',
        'discount_amount',
        'voucher_code',
        'tax_amount',
        'pay_time',
        'cancel_time',
        'buyer_message',
        'seller_memo',
        'cancel_reason',
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
