<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSpkJasaWarnaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('spk_jasa_warna', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spk_jasa_id')
                  ->constrained('spk_jasa')
                  ->cascadeOnDelete();

            $table->string('warna', 50);
            $table->integer('qty');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spk_jasa_warna');
    }
}
