<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderReturnLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'tracking_number',
        'performed_by',
        'notes',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
