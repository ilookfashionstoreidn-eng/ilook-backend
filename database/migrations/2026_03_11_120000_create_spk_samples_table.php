<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('spk_samples', function (Blueprint $table) {
            $table->id();
            $table->string('nama_sample');
            $table->string('kategori_sample');
            $table->text('detail')->nullable();
            $table->string('status_spk')->default('Draft');
            $table->text('keterangan_sample')->nullable();
            $table->string('foto')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spk_samples');
    }
};
