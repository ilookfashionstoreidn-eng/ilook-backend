<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSeriTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('seri', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_seri');
            $table->timestamps();
        });
    }

   
    public function down()
    {
        Schema::dropIfExists('seri');
    }
}
