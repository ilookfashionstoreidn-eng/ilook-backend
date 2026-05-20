<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpkCuttingDistribusiDetail extends Model
{
    use HasFactory;

    protected $table = 'spk_cutting_distribusi_detail';

    protected $fillable = [
        'spk_cutting_distribusi_id',
        'warna',
        'jumlah_produk',
        'produk_sku_id',
        'product_list_id',
    ];

    public function distribusi()
    {
        return $this->belongsTo(SpkCuttingDistribusi::class, 'spk_cutting_distribusi_id');
    }

    public function produkSku()
    {
        return $this->belongsTo(ProdukSku::class, 'produk_sku_id');
    }

    public function productListSku()
    {
        return $this->belongsTo(ProductList::class, 'product_list_id');
    }
}
