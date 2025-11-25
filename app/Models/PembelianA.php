<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PembelianA extends Model
{
    use HasFactory;
    
    protected $table = 'pembelian_aksesoris_a';

    protected $fillable =[
        'user_id',
        'aksesoris_id',
        'jumlah',
        'harga_satuan',
        'total_harga',
        'tanggal_pembelian',
        'bukti_pembelian',
    ];

    public function aksesoris()
    { 
        return $this->belongsTo(Aksesoris::class, 'aksesoris_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    public function pembelianB()
    {
        return $this->hasOne(PembelianB::class, 'pembelian_a_id');
    }
    protected $appends = ['status_verifikasi'];

    public function getStatusVerifikasiAttribute()
    {
        if ($this->pembelianB) {
            return $this->pembelianB->status_verifikasi;
        }
        return 'belum diverifikasi';
    }
}
