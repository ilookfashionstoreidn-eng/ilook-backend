<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
 
class PetugasDVerif extends Model
{
    use HasFactory;
    
    protected $table = 'petugas_d_verif';

    protected $fillable =[
        'user_id',
        'petugas_c_id',
        'barcode',
        'bukti_nota',
    ];

    protected $casts = [
        'barcode' => 'array',
    ];
    

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function petugasC()
    {
        return $this->belongsTo(PetugasC::class);
    }
    public function penjahit()
    {
        return $this->belongsTo(Penjahit::class, 'penjahit_id', 'id_penjahit');
    }
    
}


