<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddJenisHutangToHutangTable extends Migration
{
   
    public function up()
    {
        Schema::table('hutang', function (Blueprint $table) {
            $table->string('jenis_hutang')->after('tanggal_hutang');
        });
    }

  
    public function down()
    {
        Schema::table('hutang', function (Blueprint $table) {
            $table->dropColumn('jenis_hutang');
        });
    }
}
