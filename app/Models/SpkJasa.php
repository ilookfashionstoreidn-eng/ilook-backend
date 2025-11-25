<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class SpkJasa extends Model
{
    use HasFactory;

    protected $table = 'spk_jasa';
    protected $appends = ['sisa_hari'];

    protected $fillable = [
        'tukang_jasa_id',
        'spk_cutting_id',
        'deadline',
        'jumlah',
        'harga',
        'opsi_harga',
        'harga_per_pcs',
        'status_jasa',
        'tanggal_ambil',

    ];

    public function tukangJasa()
    {
        return $this->belongsTo(TukangJasa::class);
    }

    public function spkCutting()
    {
        return $this->belongsTo(SpkCutting::class);
    }

    public function getSisaHariAttribute()
    {
        if (!$this->deadline) return null;

        $today = \Carbon\Carbon::today();
        $deadline = \Carbon\Carbon::parse($this->deadline);
        return $today->diffInDays($deadline, false);
    }

    // Di SpkJasa.php
    public function produk()
    {
        return $this->hasOneThrough(
            Produk::class,
            SpkCutting::class,
            'id',           // Foreign key di SpkCutting (ke SpkCutting.id)
            'id',           // Foreign key di Produk (ke Produk.id)
            'spk_cutting_id', // Foreign key di SpkJasa (ke SpkCutting)
            'produk_id'       // Foreign key di SpkCutting (ke Produk)
        );
    }


}