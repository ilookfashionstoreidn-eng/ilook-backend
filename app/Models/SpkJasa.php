<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class SpkJasa extends Model
{
    use HasFactory;

    protected $table = 'spk_jasa';
    protected $appends = ['sisa_hari', 'total_hasil_pendapatan'];

  protected $fillable = [
    'tukang_jasa_id',
    'spk_cutting_distribusi_id',
    'deadline',
    'jumlah',
    'harga',
    'opsi_harga',
    'harga_per_pcs',
    'tanggal_ambil',
    'foto',
    'status_pengambilan'
];

    public function tukangJasa()
    {
        return $this->belongsTo(TukangJasa::class);
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

    // 🔹 ambil status terakhir (helper)
    public function latestStatusLog()
    {
        return $this->hasOne(SpkJasaStatusLog::class)->latestOfMany();
    }

    // Relasi ke HasilJasa untuk menghitung total hasil pendapatan
    public function hasilJasa()
    {
        return $this->hasMany(HasilJasa::class);
    }

    // Accessor untuk total hasil pendapatan (jumlah dari hasil_jasa.total_pendapatan)
    public function getTotalHasilPendapatanAttribute()
    {
        return $this->hasilJasa()->sum('total_pendapatan') ?? 0;
    }

    // Relasi ke SpkCutting melalui SpkCuttingDistribusi
    public function spkCutting()
    {
        return $this->hasOneThrough(
            SpkCutting::class,
            SpkCuttingDistribusi::class,
            'id', // Foreign key di SpkCuttingDistribusi (ke SpkCuttingDistribusi.id)
            'id', // Foreign key di SpkCutting (ke SpkCutting.id)
            'spk_cutting_distribusi_id', // Foreign key di SpkJasa (ke SpkCuttingDistribusi)
            'spk_cutting_id' // Foreign key di SpkCuttingDistribusi (ke SpkCutting)
        );
    }

    public function spkCmts()
    {
        return $this->morphMany(SpkCmt::class, 'source');
    }
    
    public function spkCuttingDistribusi()
    {
        return $this->belongsTo(SpkCuttingDistribusi::class);
    }

    public function statusLogs()
    {
        return $this->hasMany(SpkJasaStatusLog::class);
    }


}
