<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QcScanLolos extends Model
{
    use HasFactory;

    protected $table = 'qc_scan_lolos';

    protected $fillable = [
        'nomor_seri',
        'sku',
        'jumlah',
    ];
}
