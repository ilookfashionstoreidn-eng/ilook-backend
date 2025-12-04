<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StokBahanKeluar extends Model
{
    use HasFactory;

    protected $table = 'stok_bahan_keluar';

    protected $fillable = [
        'spk_cutting_id',
        'spk_cutting_bahan_id',
        'stok_bahan_id',
        'barcode',
        'berat',
        'scanned_at',
    ];

    public function spkCutting()
    {
        return $this->belongsTo(SpkCutting::class);
    }

    public function spkCuttingBahan()
    {
        return $this->belongsTo(SpkCuttingBahan::class);
    }

    public function stokBahan()
    {
        return $this->belongsTo(StokBahan::class);
    }
}
