<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Models\ProdukSku;

class SpkCutting extends Model
{
    use HasFactory;
    protected $table = 'spk_cutting';
    protected $appends = ['sisa_hari'];



    protected $fillable = [
        'id_spk_cutting',
        'pic',
        'produk_id',
        'tanggal_batas_kirim',
        'keterangan',
        'harga_jasa',
        'satuan_harga',
        'harga_per_pcs',
        'jumlah_asumsi_produk',
        'jenis_spk',
        'tukang_cutting_id',
        'tukang_pola_id',
        'status_cutting',
        'barcode',
        'sisa_hari_terakhir',
    ];

    public function statusLogs()
    {
        return $this->hasMany(SpkCuttingStatusLog::class);
    }

    public function produk()
    {
        return $this->belongsTo(Produk::class);
    }
    public function bagian()
    {
        return $this->hasMany(SpkCuttingBagian::class);
    }
    public function tukangCutting()
    {
        return $this->belongsTo(TukangCutting::class);
    }
    public function tukangPola()
    {
        return $this->belongsTo(TukangPola::class);
    }
    public function hasilCutting()
    {
        return $this->hasMany(HasilCutting::class);
    }


    public function getSisaHariAttribute()
    {
        if (in_array($this->status_cutting, ['Pending', 'Completed'])) {
            return $this->sisa_hari_terakhir;
        }

        if (!$this->tanggal_batas_kirim) {
            return null;
        }

        $deadline = Carbon::parse($this->tanggal_batas_kirim);
        return $deadline->isPast() ? 0 : $deadline->diffInDays(now());
    }
    public function skus()
    {
        return $this->belongsToMany(
            ProdukSku::class,
            'spk_cutting_skus',
            'spk_cutting_id',
            'produk_sku_id'
        )->withTimestamps();
    }
}
