<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddJumlahToSpkJasaTable extends Migration
{
    public function up()
    {
        Schema::table('spk_jasa', function (Blueprint $table) {
            $table->integer('jumlah')->default(0);
        });
    }

    public function down()
    {
        Schema::table('spk_jasa', function (Blueprint $table) {
            $table->dropColumn('jumlah');
        });
    }

}
