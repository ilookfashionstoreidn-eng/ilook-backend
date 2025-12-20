<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUniqueToSpkJasaSpkCuttingDistribusiId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   public function up(): void
    {
        Schema::table('spk_jasa', function (Blueprint $table) {
            $table->unique(
                'spk_cutting_distribusi_id',
                'spk_jasa_distribusi_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('spk_jasa', function (Blueprint $table) {
            $table->dropUnique('spk_jasa_distribusi_unique');
        });
    }
}
