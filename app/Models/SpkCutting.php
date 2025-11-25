<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class SpkCutting extends Model
{
    use HasFactory;
    protected $table = 'spk_cutting';
    protected $appends = ['sisa_hari'];


    
    protected $fillable = [
        'id_spk_cutting',
        'produk_id',
        'tanggal_batas_kirim',
        'keterangan',
        'harga_jasa',
        'satuan_harga',
        'harga_per_pcs',
        'tukang_cutting_id',
        'status_cutting'


    ];
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

}
