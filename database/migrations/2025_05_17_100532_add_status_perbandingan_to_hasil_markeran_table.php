<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusPerbandinganToHasilMarkeranTable extends Migration
{
    
    public function up()
    {
        Schema::table('hasil_markeran', function (Blueprint $table) {
             $table->string('status_perbandingan')->nullable();
        });
    }

   
    public function down()
    {
        Schema::table('hasil_markeran', function (Blueprint $table) {
             $table->dropColumn('status_perbandingan');
        });
    }
}
