<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderPackingResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'order_item_id',
        'actual_sku_id',
        'line_type',
        'status',
        'original_sku',
        'original_product_name',
        'original_image',
        'actual_sku',
        'actual_product_name',
        'actual_image',
        'ordered_qty',
        'scanned_qty',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function actualSku()
    {
        return $this->belongsTo(Sku::class, 'actual_sku_id');
    }

    public function serials()
    {
        return $this->hasMany(OrderPackingResultSerial::class);
    }
}
