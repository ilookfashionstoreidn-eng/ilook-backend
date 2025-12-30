<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLogStatusSpkCmtTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('log_status_spk_cmt', function (Blueprint $table) {
             $table->id();
            $table->unsignedBigInteger('spk_cmt_id');
            $table->string('status');
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
        Schema::dropIfExists('log_status_spk_cmt');
    }
}
