<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePendapatanPabrikDetailTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pendapatan_pabrik_detail', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pendapatan_pabrik_id')
                ->constrained('pendapatan_pabrik')
                ->cascadeOnDelete();

            $table->foreignId('pembelian_bahan_id')
                ->constrained('pembelian_bahan')
                ->restrictOnDelete();

            $table->decimal('nominal', 15, 2);

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
        Schema::dropIfExists('pendapatan_pabrik_detail');
    }
}
