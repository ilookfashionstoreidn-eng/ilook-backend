<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCashboanJasaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cashboan_jasa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tukang_jasa_id')->constrained('tukang_jasa')->onDelete('cascade');
            $table->decimal('jumlah_cashboan', 12, 2);
            $table->enum('status_pembayaran', ['belum lunas', 'lunas'])->default('belum lunas');
            $table->date('tanggal_cashboan');
            $table->string('bukti_transfer')->nullable();
            $table->timestamps();
        });
    }
    public function down()
    {
        Schema::dropIfExists('cashboan_jasa');
    }
}
