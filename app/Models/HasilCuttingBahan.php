<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HasilCuttingBahan extends Model
{
    use HasFactory;
    protected $table = 'hasil_cutting_bahan'; 

    protected $fillable = [
        'hasil_cutting_id',
        'spk_cutting_bahan_id',
        'spk_cutting_bagian_id',
        'berat',
        'hasil',
    ];

    public function hasilCutting()
    {
        return $this->belongsTo(HasilCutting::class);
    }

    public function spkCuttingBahan()
    {
        return $this->belongsTo(SpkCuttingBahan::class);
    }
   public function pendapatan_cutting()
    {
        return $this->belongsToMany(PendapatanCutting::class, 'hasil_pendapatan_cutting');
    }
  

}
