<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\SpkCmt;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SpkCmtController;
use App\Http\Controllers\PenjahitController;
use App\Http\Controllers\LaporanCmtController;
use App\Http\Controllers\WarnaController;
use App\Http\Controllers\PengirimanController;
use App\Http\Controllers\CashboanController;
use App\Http\Controllers\LogPembayaranCashbonController;
use App\Http\Controllers\HutangController;
use App\Http\Controllers\LogPembayaranHutangController;
use App\Http\Controllers\PendapatanController;
use App\Http\Controllers\PembelianBahanController;
use App\Models\PembelianBahan;
use App\Models\PembelianBahanRol;
use App\Models\SpkSample;
use Barryvdh\DomPDF\Facade\Pdf;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/nota', function () {
    return view('pdf.nota'); // Sesuai dengan path resources/views/pdf/nota.blade.php
});
Route::get('/contoh', function () {
    return view('pdf.contoh'); // Sesuai dengan path resources/views/pdf/nota.blade.php
});
Route::get('/nota2', function () {
    return view('pdf.nota2'); // Sesuai dengan path resources/views/pdf/nota.blade.php
});
// Test route untuk debug barcode
Route::get('/test-barcode/{id}', function ($id) {
    $controller = new PembelianBahanController();
    return $controller->downloadBarcodes($id);
});

// Test route dengan blade simple untuk debug
Route::get('/test-barcode-simple/{id}', function ($id) {
    $pembelianBahan = PembelianBahan::with(['pabrik', 'bahan', 'gudang'])->findOrFail($id);
    $barcodes = PembelianBahanRol::whereHas('warna', function ($q) use ($id) {
        $q->where('pembelian_bahan_id', $id);
    })->with(['warna'])->get();

    $pdf = Pdf::loadView('pdf.barcode_pembelian_bahan_TEST', [
        'barcodes' => $barcodes,
        'pembelianBahan' => $pembelianBahan,
    ]);

    return $pdf->download("barcode-TEST-{$id}.pdf");
});

Route::get('/spk-sample', function () {
    $samples = SpkSample::latest()->get();
    return view('spk-sample.index', compact('samples'));
});

Route::get('/blank', function () {
    return view('blank');
});
