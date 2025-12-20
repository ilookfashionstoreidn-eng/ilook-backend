<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSpkJasaStatusLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   public function up(): void
    {
        Schema::create('spk_jasa_status_log', function (Blueprint $table) {
            $table->id();

            $table->foreignId('spk_jasa_id')
                ->constrained('spk_jasa');

            $table->enum('status', [
                'belum_diambil',
                'sudah_diambil',
                'batal_diambil',
                'selesai'
            ]);

            $table->text('keterangan')->nullable();

            $table->timestamps(); // created_at = waktu perubahan status
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spk_jasa_status_log');
    }
}
