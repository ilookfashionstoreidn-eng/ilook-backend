<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWaktuPengerjaanTerakhirAndSisaHariTerakhirToSpkCmtTable extends Migration
{
    public function up()
{
    Schema::table('spk_cmt', function (Blueprint $table) {
        $table->integer('waktu_pengerjaan_terakhir')->nullable()->after('handtag'); // Nilai terakhir waktu pengerjaan
        $table->integer('sisa_hari_terakhir')->nullable()->after('waktu_pengerjaan_terakhir'); // Nilai terakhir sisa hari
    });
}

public function down()
{
    Schema::table('spk_cmt', function (Blueprint $table) {
        $table->dropColumn(['waktu_pengerjaan_terakhir', 'sisa_hari_terakhir']);
    });
}

}
