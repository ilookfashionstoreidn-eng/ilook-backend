<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpkCuttingDistribusi extends Model
{
    use HasFactory;

    protected $table = 'spk_cutting_distribusi';

    protected $fillable = [
        'spk_cutting_id',
        'hasil_cutting_id',
        'kode_seri',
        'jumlah_produk',
        'status',
    ];

    public function spkCutting()
    {
        return $this->belongsTo(SpkCutting::class);
    }

    public function hasilCutting()
    {
        return $this->belongsTo(HasilCutting::class, 'hasil_cutting_id');
    }

    public function spkCmt()
    {
        return $this->hasOne(SpkCmt::class);
    }
    public function spkCmts()
{
    return $this->morphMany(SpkCmt::class, 'source');
}

}
