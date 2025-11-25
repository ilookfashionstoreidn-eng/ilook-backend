<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePendapatanTable extends Migration
{
   
    public function up()
    {
        Schema::create('pendapatan', function (Blueprint $table) {
            $table->id('id_pendapatan');
            $table->unsignedBigInteger('id_penjahit');
            $table->date('periode_awal');
            $table->date('periode_akhir');
            $table->decimal('total_pendapatan', 15, 2);
            $table->decimal('total_claim', 15, 2)->default(0);
            $table->decimal('total_refund_claim', 15, 2)->default(0);
            $table->decimal('total_cashbon', 15, 2)->default(0);
            $table->decimal('total_hutang', 15, 2)->default(0);
            $table->decimal('handtag', 15, 2)->default(0);
            $table->decimal('transportasi', 15, 2)->default(0);
            $table->decimal('total_transfer', 15, 2);
            $table->timestamps();

            $table->foreign('id_penjahit')->references('id_penjahit')->on('penjahit_cmt')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('pendapatan');
    }
}
