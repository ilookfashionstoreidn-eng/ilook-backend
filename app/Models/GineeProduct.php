<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GineeProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'product_name',
        'color',
        'size',
        'image_url',
        'last_synced_at',
        'category',
        'status',
        'description',
        'created_at_ginee',
        'updated_at_ginee',
    ];
}
