<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TukangCutting extends Model
{
    use HasFactory;
    
    protected $table = 'tukang_cutting';

    protected $fillable = [
        'nama_tukang_cutting',
        'kontak',
        'bank',
        'no_rekening',
        'alamat',
    ];


public function getPendapatanMingguIni()
{
    $awal = now()->startOfWeek();
    $akhir = now()->endOfWeek();

    $total = HasilCutting::join('spk_cutting', 'hasil_cutting.spk_cutting_id', '=', 'spk_cutting.id')
        ->where('spk_cutting.tukang_cutting_id', $this->id)
        ->whereBetween('hasil_cutting.created_at', [$awal, $akhir])
        ->sum('total_bayar');

    return (object)[
        'total_pendapatan' => $total,
    ];
}

  
}
