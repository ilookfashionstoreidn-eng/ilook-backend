<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PendapatanPabrikDetail extends Model
{
    use HasFactory;

    protected $table = 'pendapatan_pabrik_detail';

    protected $fillable = [
        'pendapatan_pabrik_id',
        'pembelian_bahan_id',
        'nominal',
    ];

    public function pendapatanPabrik()
    {
        return $this->belongsTo(PendapatanPabrik::class);
    }

    public function pembelianBahan()
    {
        return $this->belongsTo(PembelianBahan::class);
    }
}
