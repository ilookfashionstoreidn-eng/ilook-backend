<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderFollowupLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'status',
        'notes',
        'performed_by',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
