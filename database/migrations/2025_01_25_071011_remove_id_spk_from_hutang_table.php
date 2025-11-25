<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveIdSpkFromHutangTable extends Migration
{
    public function up()
    {
        Schema::table('hutang', function (Blueprint $table) {
            // Hapus constraint kunci asing terlebih dahulu
            $table->dropForeign(['id_spk']);
            // Kemudian hapus kolom id_spk
            $table->dropColumn('id_spk');
        });
    }

    public function down()
    {
        Schema::table('hutang', function (Blueprint $table) {
            // Tambahkan kembali kolom id_spk
            $table->unsignedBigInteger('id_spk')->nullable();
            // Tambahkan kembali constraint kunci asing
            $table->foreign('id_spk')->references('id')->on('spk');
        });
    }
}
