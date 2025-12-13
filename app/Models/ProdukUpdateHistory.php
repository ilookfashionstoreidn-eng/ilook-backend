<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProdukUpdateHistory extends Model
{
   protected $fillable = [
        'produk_id',
        'user_id',
        'action',
        'old_data',
        'new_data',
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
    ];

    public function produk()
    {
        return $this->belongsTo(Produk::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}