<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpkCuttingBahan extends Model
{
    use HasFactory;

    protected $table = 'spk_cutting_bahan';

    protected $fillable = [
        'spk_cutting_bagian_id',
        'sumber_komponen',
        'bahan_id',
        'aksesoris_id',
        'warna',
        'qty',
        'berat',
    ];

    public function bagian()
    {
        return $this->belongsTo(SpkCuttingBagian::class, 'spk_cutting_bagian_id');
    }

    public function bahan()
    {
        return $this->belongsTo(Bahan::class);
    }

    public function aksesoris()
    {
        return $this->belongsTo(Aksesoris::class, 'aksesoris_id');
    }

    public function skus()
    {
        return $this->belongsToMany(
            ProductList::class,
            'spk_cutting_bahan_skus',
            'spk_cutting_bahan_id',
            'sku_id'
        )->withPivot('qty')->withTimestamps();
    }
}
