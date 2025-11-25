<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLogStatusTable extends Migration
{
    public function up()
    {
        Schema::create('log_status', function (Blueprint $table) {
            $table->id('id_status');
            $table->foreignId('id_spk')->references('id_spk')->on('spk_cmt')->onDelete('cascade');
            $table->enum('status_lama', ['Pending', 'In Progress', 'Completed']); // Status sebelum perubahan
            $table->enum('status_baru', ['Pending', 'In Progress', 'Completed']); // Status setelah perubahan
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('log_status');
    }
}
