<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStokBahanKeluarTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('stok_bahan_keluar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spk_cutting_id')->constrained('spk_cutting')->onDelete('cascade');
            $table->foreignId('spk_cutting_bahan_id')->constrained('spk_cutting_bahan')->onDelete('cascade');
            $table->foreignId('stok_bahan_id')->constrained('stok_bahan')->onDelete('cascade');
            $table->string('barcode');
            $table->decimal('berat', 10, 2)->nullable();
            $table->timestamp('scanned_at');
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
        Schema::dropIfExists('stok_bahan_keluar');
    }
}
