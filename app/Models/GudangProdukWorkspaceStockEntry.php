<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GudangProdukWorkspaceStockEntry extends Model
{
    use HasFactory;

    protected $table = 'gudang_produk_stock_entries';

    protected $fillable = [
        'layout_id',
        'sku_id',
        'slot_id',
        'qty',
        'updated_by',
    ];

    public function layout()
    {
        return $this->belongsTo(GudangProdukLayout::class, 'layout_id');
    }

    public function sku()
    {
        return $this->belongsTo(Sku::class, 'sku_id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
