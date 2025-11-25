<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warna extends Model
{
    use HasFactory;
    protected $table = 'warna'; // Nama tabel
    protected $primaryKey = 'id_warna'; // Primary key

    protected $fillable = [
        'nama_warna',
        'qty',
        'id_spk',
    ];

    public function spkCmt()
    {
        return $this->belongsTo(SpkCmt::class, 'id_spk', 'id_spk');
    }
}
