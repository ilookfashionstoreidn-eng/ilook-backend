<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PembelianB extends Model
{
    use HasFactory;

    protected $table ='pembelian_aksesoris_b';

    protected $fillable =[
        'pembelian_a_id',
        'user_id',
        'jumlah_terverifikasi',
        'status_verifikasi',
        'barcode_downloaded',
        
    ];
    

    public function pembelianA(){
        return $this->belongsTo(PembelianA::class, 'pembelian_a_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function stokAksesoris()
{
    return $this->hasMany(StokAksesoris::class, 'pembelian_aksesoris_b_id');
}


}
