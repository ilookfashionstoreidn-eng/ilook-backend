<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Models\Produk;

class ProdukSku extends Model
{
    use HasFactory;

    protected $table = 'produk_sku';

    protected $fillable = [
        'produk_id',
        'warna',
        'ukuran',
        'sku',
    ];

    /**
     * Relasi ke produk
     */
    public function produk()
    {
        return $this->belongsTo(Produk::class);
    }

    /**
     * Auto-generate SKU saat create
     */
    protected static function booted()
    {
        static::creating(function ($sku) {
            if (empty($sku->sku)) {
                $produk = $sku->produk;

                if ($produk) {
                    $sku->sku = strtoupper(
                        $produk->nama_produk
                        . ' - '
                        . $sku->warna
                        . ' '
                        . $sku->ukuran
                    );
                }
            }
        });
    }

    public function spkCuttings()
    {
        return $this->belongsToMany(
            SpkCutting::class,
            'spk_cutting_skus',
            'produk_sku_id',
            'spk_cutting_id'
        )->withTimestamps();
    }
}