<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sku extends Model
{
    use HasFactory;

     protected $fillable = [
        'sku',
        'is_active',
    ];

    public function spkCmts()
    {
        return $this->hasMany(SpkCmt::class, 'sku_id');
    }

    public function productList()
    {
        return $this->hasOne(ProductList::class, 'sku_name', 'sku');
    }
}
