<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveTglSpkAndTanggalAmbilFromSpkCmtTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
     public function up()
    {
        Schema::table('spk_cmt', function (Blueprint $table) {
            $table->dropColumn([
                'tgl_spk',
                'tanggal_ambil',
            ]);
        });
    }

    public function down()
    {
        Schema::table('spk_cmt', function (Blueprint $table) {
            $table->date('tgl_spk')->nullable();
            $table->date('tanggal_ambil')->nullable();
        });
    }
}
