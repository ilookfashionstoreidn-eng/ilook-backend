<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pabrik extends Model
{
    use HasFactory;

    protected $table = "pabrik";

    protected $fillable = [
        'nama_pabrik',
        'lokasi',
        'kontak',
        'ktp',

    ];
}
