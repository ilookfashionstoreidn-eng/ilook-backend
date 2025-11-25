<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddJenisJasaToTukangJasaTable extends Migration
{
    
    public function up()
    {
        Schema::table('tukang_jasa', function (Blueprint $table) {
              $table->string('jenis_jasa')->nullable();
        });
    }

  
    public function down()
    {
        Schema::table('tukang_jasa', function (Blueprint $table) {
            $table->dropColumn('jenis_jasa');
        });
    }
}
