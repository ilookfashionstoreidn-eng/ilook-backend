<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TukangPotong extends Model
{
    use HasFactory;

    protected $table ='tukang_potong';
    
    protected $fillable = [
        'nama',
        'kontak',
        'alamat',
        'bank',
        'no_rekening',
        'ktp',
        'jenis_jasa',
    ];

}
