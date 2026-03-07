<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QcScanReject extends Model
{
    use HasFactory;

    protected $table = 'qc_scan_reject';

    protected $fillable = [
        'nomor_seri',
        'sku',
        'jumlah',
    ];
}
