<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NoDataGineeLogScan extends Model
{
    use HasFactory;

    protected $fillable = [
        'no_data_ginee_log_id',
        'scan_index',
        'actual_sku',
        'actual_sku_id',
        'actual_product_name',
        'actual_image',
        'serial_number',
        'resolved_status',
        'resolved_order_item_id',
        'resolved_original_sku',
        'resolved_original_product_name',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function log()
    {
        return $this->belongsTo(NoDataGineeLog::class, 'no_data_ginee_log_id');
    }

    public function resolvedOrderItem()
    {
        return $this->belongsTo(OrderItem::class, 'resolved_order_item_id');
    }

    public function actualSku()
    {
        return $this->belongsTo(Sku::class, 'actual_sku_id');
    }
}
