<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSpkCmtWarnaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('spk_cmt_warna', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('spk_cmt_id');
            $table->string('nama_warna');
            $table->integer('qty');
            $table->timestamps();

            $table->foreign('spk_cmt_id')
                ->references('id_spk')
                ->on('spk_cmt')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('spk_cmt_warna');
    }
}
