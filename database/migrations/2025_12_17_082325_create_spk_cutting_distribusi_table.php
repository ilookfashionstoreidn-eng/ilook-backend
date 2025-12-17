<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSpkCuttingDistribusiTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
     public function up(): void
    {
        Schema::create('spk_cutting_distribusi', function (Blueprint $table) {
            $table->id();

            $table->foreignId('spk_cutting_id')
                ->constrained('spk_cutting')
                ->cascadeOnDelete();

            $table->string('kode_seri', 5);       
            $table->string('no_seri', 30);          

            $table->integer('jumlah_produk');

            $table->enum('status', [
                'draft',
                'assigned',
            ])->default('draft');

            $table->timestamps();

            $table->unique(['spk_cutting_id', 'kode_seri']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spk_cutting_distribusi');
    }
}
