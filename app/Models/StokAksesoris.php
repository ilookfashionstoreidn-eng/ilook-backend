<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StokAksesoris extends Model
{
    use HasFactory;

    protected $table  = 'stok_aksesoris';

    protected $fillable = [
        'pembelian_aksesoris_b_id',
        'aksesoris_id',
        'barcode',
        'status',
    ];

    public function pembelianB()
    {
        return $this->belongsTo(PembelianB::class, 'pembelian_aksesoris_b_id');
    }


    public function aksesoris()
    {
        return $this->belongsTo(Aksesoris::class, 'aksesoris_id');
    }
}
