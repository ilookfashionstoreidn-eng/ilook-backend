<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TukangPola extends Model
{
    use HasFactory;

    protected $table = 'tukang_pola';

    protected $fillable = [
        'nama',
    ];

    public function spkCutting()
    {
        return $this->hasMany(SpkCutting::class);
    }
}
