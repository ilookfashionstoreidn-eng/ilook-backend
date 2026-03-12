<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpkSample extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama_sample',
        'kategori_sample',
        'detail',
        'status_spk',
        'status_proses',
        'tahap_proses',
        'keterangan_sample',
        'foto',
        'tukang_sample_id',
    ];

    public function tukangSample()
    {
        return $this->belongsTo(TukangSample::class, 'tukang_sample_id');
    }
}
