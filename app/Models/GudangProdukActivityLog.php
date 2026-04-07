<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GudangProdukActivityLog extends Model
{
    use HasFactory;

    protected $table = 'gudang_produk_activity_logs';

    protected $fillable = [
        'type',
        'sku_id',
        'from_slot_id',
        'to_slot_id',
        'qty',
        'notes',
        'created_by',
    ];

    public function sku()
    {
        return $this->belongsTo(Sku::class, 'sku_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
