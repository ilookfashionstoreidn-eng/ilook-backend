<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bahan extends Model
{
    use HasFactory;

    protected $table = 'bahan';

    protected $fillable = [
        'group_bahan',
        'pabrik_bahan',
        'nama_bahan',
        'deskripsi',
        'harga',
        'satuan',
        'warna_bahan',
        'stok_bahan',
    ];
}
