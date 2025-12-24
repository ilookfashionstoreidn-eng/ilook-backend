<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusBayarToHasilJasaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   public function up()
    {
        Schema::table('hasil_jasa', function (Blueprint $table) {
            $table->enum('status_bayar', ['belum_dibayar', 'sudah_dibayar'])
                ->default('belum_dibayar')
                ->after('total_pendapatan');

            $table->foreignId('pendapatan_jasa_id')
                ->nullable()
                ->after('status_bayar')
                ->constrained('pendapatan_jasa')
                ->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('hasil_jasa', function (Blueprint $table) {
            $table->dropForeign(['pendapatan_jasa_id']);
            $table->dropColumn(['status_bayar', 'pendapatan_jasa_id']);
        });
    }
}
