<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePendapatanJasaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pendapatan_jasa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tukang_jasa_id')->constrained('tukang_jasa')->onDelete('cascade');
            $table->decimal('total_pendapatan', 15, 2)->default(0);
            $table->decimal('total_claim', 15, 2)->default(0);
            $table->decimal('total_refund_claim', 15, 2)->default(0);
            $table->decimal('total_cashbon', 15, 2)->default(0);
            $table->decimal('total_hutang', 15, 2)->default(0);
            $table->decimal('total_transfer', 15, 2)->default(0);
            $table->enum('status_pembayaran', ['sudah_dibayar', 'belum_dibayar'])->default('belum_dibayar');
            $table->string('bukti_transfer')->nullable();
            $table->timestamps();
        });
    }

    
    public function down()
    {
        Schema::dropIfExists('pendapatan_jasa');
    }
}
