<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePengirimanPendapatanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
{
    Schema::create('pengiriman_pendapatan', function (Blueprint $table) {
        $table->id();
        $table->foreignId('id_pendapatan')->constrained('pendapatan', 'id_pendapatan')->onDelete('cascade');
        $table->foreignId('id_pengiriman')->constrained('pengiriman','id_pengiriman')->onDelete('cascade');
        $table->timestamps();
    });
    
}

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pengiriman_pendapatan');
    }
}
