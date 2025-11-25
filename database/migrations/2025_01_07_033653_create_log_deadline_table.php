<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLogDeadlineTable extends Migration
{
  
    public function up()
    {
        Schema::create('log_deadline', function (Blueprint $table) {
            $table->id('id_log');
            $table->foreignId('id_spk')->references('id_spk')->on('spk_cmt')->onDelete('cascade');

            $table->date('deadline_lama');
            $table->date('deadline_baru');
            $table->text('keterangan')->nullable();
            $table->timestamp('tanggal_aktivitas')->useCurrent();
        });
    }

    public function down()
    {
        Schema::dropIfExists('log_deadline');
    }
}
