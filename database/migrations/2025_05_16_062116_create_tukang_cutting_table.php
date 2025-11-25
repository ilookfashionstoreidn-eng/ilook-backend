<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTukangCuttingTable extends Migration
{
    
    public function up()
    {
        Schema::create('tukang_cutting', function (Blueprint $table) {
            $table->id();
            $table->string('nama_tukang_cutting');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('tukang_cutting');
    }
}
