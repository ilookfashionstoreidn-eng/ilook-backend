<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogStatus extends Model
{
    use HasFactory;
    protected $table = 'log_status';
    protected $primaryKey = 'id_log';
    public $timestamps = true; 

    protected $fillable = [
        'id_spk',
        'status_lama',
        'status_baru',
        'keterangan',
        'tanggal_aktivitas',
    ];
    
    // relasi ke tabel spk
    public function spk()
    {
        return $this->belongsTo(SpkCmt::class, 'id_spk');
    }
}
