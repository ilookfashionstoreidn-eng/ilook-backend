<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TukangJasa extends Model
{
    use HasFactory;

    protected $table = 'tukang_jasa';

    protected $fillable = [
        'nama',
        'kontak',
        'alamat',
        'bank',
        'no_rekening',
        'jenis_jasa',
    ];

   
public function getPendapatanMingguIni()
{
    $awalMinggu = now()->startOfWeek();
    $akhirMinggu = now()->endOfWeek();

    return HasilJasa::join('spk_jasa', 'hasil_jasa.spk_jasa_id', '=', 'spk_jasa.id')
        ->where('spk_jasa.tukang_jasa_id', $this->id)
        ->whereBetween('hasil_jasa.created_at', [$awalMinggu, $akhirMinggu])
        ->selectRaw('SUM(total_pendapatan) as total_pendapatan')
        ->first();
}

}
