<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NoDataGineeLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'tracking_number',
        'scanner_name',
        'order_id',
        'notes',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
