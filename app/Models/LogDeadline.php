<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogDeadline extends Model
{
    use HasFactory;
    protected $table = 'log_deadline';
    protected $primaryKey = 'id_log';
    public $timestamps = true; 

    protected $fillable = [
        'deadline_lama',
        'deadline_baru',
        'keterangan',
        'tanggal_aktivitas',
        'id_spk',
    ];

    // relasi ke tabel spk
    public function spk()
    {
        return $this->belongsTo(SpkCmt::class, 'id_spk');
    }
}
