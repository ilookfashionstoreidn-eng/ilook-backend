<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QualityControl extends Model
{
    use HasFactory;

    protected $fillable = [
        'kode_seri',
        'jumlah_barang_nota',
        'jumlah_diterima',
    ];

    public function items()
    {
        return $this->hasMany(QualityControlItem::class);
    }
}
