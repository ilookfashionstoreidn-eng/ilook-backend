<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveKtpFromTukangJasaTable extends Migration
{
    public function up()
{
    Schema::table('tukang_jasa', function (Blueprint $table) {
        $table->dropColumn('ktp');
    });
}

public function down()
{
    Schema::table('tukang_jasa', function (Blueprint $table) {
        $table->string('ktp')->nullable();
    });
}

}
