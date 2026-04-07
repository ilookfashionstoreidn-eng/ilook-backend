<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GudangProdukLayout extends Model
{
    use HasFactory;

    protected $table = 'gudang_produk_layouts';

    protected $fillable = [
        'uid',
        'name',
        'address',
        'pic',
        'description',
        'created_by',
        'updated_by',
    ];

    public function floors()
    {
        return $this->hasMany(GudangProdukLayoutFloor::class, 'layout_id');
    }

    public function slotAliases()
    {
        return $this->hasMany(GudangProdukSlotAlias::class, 'layout_id');
    }

    public function stockEntries()
    {
        return $this->hasMany(GudangProdukWorkspaceStockEntry::class, 'layout_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
