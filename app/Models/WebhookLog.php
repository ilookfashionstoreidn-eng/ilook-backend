<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    protected $fillable = [
        'ginee_order_id',
        'entity',
        'action',
        'status',
        'error_message',
        'raw_payload',
        'order_id',
    ];

    protected $casts = [
        'raw_payload' => 'array',
    ];

    /**
     * Relasi ke order yang diproses
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
