<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TukangSample extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'nama_tukang_sample',
        'nomor_hp',
    ];
}
