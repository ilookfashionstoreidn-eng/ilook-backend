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






