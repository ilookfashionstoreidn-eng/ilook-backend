<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHargaJasaAwalToSpkCmt extends Migration
{
   
    public function up()
    {
        Schema::table('spk_cmt', function (Blueprint $table) {
            $table->decimal('harga_jasa_awal', 10, 2)->nullable();
        });
    }

   
    public function down()
    {
        Schema::table('spk_cmt', function (Blueprint $table) {
            $table->dropColumn(['harga_jasa_awal']);
        });
    }
}
