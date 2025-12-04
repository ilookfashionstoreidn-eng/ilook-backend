<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPembayaranColumnsToPetugasDVerifTable extends Migration
{
    
    public function up()
    {
        Schema::table('petugas_d_verif', function (Blueprint $table) {
            $table->enum('status_pembayaran', ['belum', 'sudah'])->default('belum');
            $table->string('bukti_pembayaran')->nullable();
        });
    }

   
    public function down()
    {
        Schema::table('petugas_d_verif', function (Blueprint $table) {
            $table->dropColumn(['status_pembayaran', 'bukti_pembayaran']);
        });
    }
}
