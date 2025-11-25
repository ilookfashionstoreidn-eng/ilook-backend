<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistoryHutang extends Model
{
    use HasFactory;

    protected $table = 'history_hutang';
    protected $primaryKey = 'id_history';
    public $timestamps = false; // Karena kita sudah pakai timestamp manual di `tanggal_perubahan`

    protected $fillable = [
        'id_hutang',
        'jumlah_hutang',
        'perubahan_hutang',
        'jenis_perubahan',
        'tanggal_perubahan',
        'bukti_transfer',
    ];

    // Relasi ke Hutang
    public function hutang()
    {
        return $this->belongsTo(Hutang::class, 'id_hutang', 'id_hutang');
    }
}
