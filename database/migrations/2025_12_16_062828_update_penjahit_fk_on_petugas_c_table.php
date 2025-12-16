<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdatePenjahitFkOnPetugasCTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('petugas_c', function (Blueprint $table) {
            // 1. DROP foreign key lama
            $table->dropForeign(['penjahit_id']);

            // 2. Jadikan penjahit_id nullable
            $table->unsignedBigInteger('penjahit_id')->nullable()->change();

            // 3. Tambah foreign key BARU (AMAN)
            $table->foreign('penjahit_id')
                  ->references('id_penjahit')
                  ->on('penjahit_cmt')
                  ->nullOnDelete(); // ⬅️ KUNCI
        });
    }

    public function down(): void
    {
        Schema::table('petugas_c', function (Blueprint $table) {
            $table->dropForeign(['penjahit_id']);

            $table->unsignedBigInteger('penjahit_id')->nullable(false)->change();

            $table->foreign('penjahit_id')
                  ->references('id_penjahit')
                  ->on('penjahit_cmt')
                  ->onDelete('cascade'); // balik ke kondisi lama
        });
    }
}
