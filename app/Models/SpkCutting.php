<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Models\ProdukSku;
use App\Models\ProductList;

class SpkCutting extends Model
{
    use HasFactory;
    protected $table = 'spk_cutting';
    protected $appends = ['sisa_hari', 'tahap_terakhir'];



    protected $fillable = [
        'id_spk_cutting',
        'pic',
        'produk_id',
        'product_list_id',
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
    public function productList()
    {
        return $this->belongsTo(ProductList::class);
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
    public function productListSkus()
    {
        return $this->belongsToMany(
            ProductList::class,
            'spk_cutting_skus',
            'spk_cutting_id',
            'product_list_id'
        )->withTimestamps();
    }

    public function getTahapTerakhirAttribute()
    {
        $spkId = $this->id;

        $hasJasa = \App\Models\SpkJasa::whereIn('spk_cutting_distribusi_id', function($q) use ($spkId) {
            $q->select('id')->from('spk_cutting_distribusi')->where('spk_cutting_id', $spkId);
        })->exists();
        if ($hasJasa) {
            return "SPK Jasa";
        }

        $hasCmt = \App\Models\SpkCmt::where('source_type', 'cutting')
            ->whereIn('source_id', function($q) use ($spkId) {
                $q->select('id')->from('spk_cutting_distribusi')->where('spk_cutting_id', $spkId);
            })->exists();
        if ($hasCmt) {
            return "SPK CMT";
        }

        $hasDistribusi = \App\Models\SpkCuttingDistribusi::where('spk_cutting_id', $spkId)->exists();
        if ($hasDistribusi) {
            return "Terdistribusi";
        }

        $hasHasil = \App\Models\HasilCutting::where('spk_cutting_id', $spkId)->exists();
        if ($hasHasil) {
            return "Hasil Cutting";
        }

        return "SPK Cutting";
    }

    public function stokBahanKeluar()
    {
        return $this->hasMany(\App\Models\StokBahanKeluar::class, 'spk_cutting_id');
    }
}
