<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSpkJasaTable extends Migration
{
    public function up()
    {
        Schema::create('spk_jasa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tukang_jasa_id')->constrained('tukang_jasa')->onDelete('cascade');
            $table->foreignId('spk_cutting_id')->constrained('spk_cutting')->onDelete('cascade');
            $table->date('deadline');
            $table->string('status')->default('proses'); 
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('spk_jasa');
    }
}
