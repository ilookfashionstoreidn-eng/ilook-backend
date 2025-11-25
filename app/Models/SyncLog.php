<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    protected $table = 'sync_logs';

    protected $fillable = [
        'type',
        'last_sync_at',
    ];

    public $timestamps = true;

    protected $dates = ['last_sync_at'];
}