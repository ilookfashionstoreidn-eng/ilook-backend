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
        'scan_mode',
        'reconciliation_status',
        'order_id',
        'notes',
        'reconciled_at',
    ];

    protected $casts = [
        'reconciled_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function scans()
    {
        return $this->hasMany(NoDataGineeLogScan::class)->orderBy('scan_index');
    }
}
