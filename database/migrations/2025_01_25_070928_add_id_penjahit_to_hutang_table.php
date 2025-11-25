<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdPenjahitToHutangTable extends Migration
{
    public function up()
{
    Schema::table('hutang', function (Blueprint $table) {
        $table->unsignedBigInteger('id_penjahit')->nullable();
    });
}

public function down()
{
    Schema::table('hutang', function (Blueprint $table) {
        $table->dropColumn('id_penjahit');
    });
}

}
