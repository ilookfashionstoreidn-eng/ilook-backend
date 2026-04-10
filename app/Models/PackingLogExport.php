<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackingLogExport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status',
        'filters',
        'file_name',
        'file_path',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
