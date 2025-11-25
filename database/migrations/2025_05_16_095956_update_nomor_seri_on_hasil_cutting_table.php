<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateNomorSeriOnHasilCuttingTable extends Migration
{
     public function up()
    {
        Schema::table('hasil_cutting', function (Blueprint $table) {
            // Hapus constraint unique lebih dulu
            $table->dropUnique(['nomor_seri']); // hapus index unik
            $table->dropColumn('nomor_seri');   // lalu hapus kolom
        });

        Schema::table('hasil_cutting', function (Blueprint $table) {
            $table->string('nomor_seri'); // tambahkan ulang tanpa unique
        });
    }

    public function down()
    {
        Schema::table('hasil_cutting', function (Blueprint $table) {
            $table->dropColumn('nomor_seri');
        });

        Schema::table('hasil_cutting', function (Blueprint $table) {
            $table->string('nomor_seri')->unique();
        });
    }
}
