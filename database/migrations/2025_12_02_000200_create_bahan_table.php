<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (!Schema::hasTable('bahan')) {
            Schema::create('bahan', function (Blueprint $table) {
                $table->id();
                $table->string('nama_bahan')->unique();
                $table->text('deskripsi')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('bahan');
    }
};
