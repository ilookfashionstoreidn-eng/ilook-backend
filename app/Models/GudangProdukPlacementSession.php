<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GudangProdukPlacementSession extends Model
{
    protected $table = 'gudang_produk_placement_sessions';

    protected $fillable = [
        'seri_id',
        'sku_id',
        'barcodes',
        'notes',
        'status',
        'created_by',
    ];

    protected $casts = [
        'barcodes' => 'array',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sku()
    {
        return $this->belongsTo(Sku::class, 'sku_id');
    }

    public function seri()
    {
        return $this->belongsTo(Seri::class, 'seri_id');
    }
}
