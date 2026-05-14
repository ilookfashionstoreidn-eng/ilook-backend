<?php

namespace App\Http\Controllers;

class PackingPendinganController extends PackingRandomController
{
    protected function getMismatchStatus(): string
    {
        return 'pendingan';
    }

    protected function getSuccessMessage(): string
    {
        return 'Order berhasil divalidasi melalui packing pendingan';
    }

    protected function getLogAction(): string
    {
        return 'scan_validasi_pendingan';
    }
}
