<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PendapatanPabrik extends Model
{
    use HasFactory;

    protected $table = 'pendapatan_pabrik';

    protected $fillable = [
        'pabrik_id',
        'tanggal_bayar',
        'total_bayar',
        'keterangan',
    ];

    public function pabrik()
    {
        return $this->belongsTo(Pabrik::class);
    }

    public function detail()
    {
        return $this->hasMany(PendapatanPabrikDetail::class);
    }
}
