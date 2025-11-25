<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HasilMarkeran extends Model
{
    use HasFactory;
    protected $table = 'hasil_markeran';

    protected $fillable = [
        'hasil_cutting_id',
        'nama_komponen',
        'total_panjang',
        'jumlah_hasil',
        'berat_per_pcs',
        'status_perbandingan',
    ];

   public function hasilCutting()
    {
        return $this->belongsTo(HasilCutting::class);
    }

}
