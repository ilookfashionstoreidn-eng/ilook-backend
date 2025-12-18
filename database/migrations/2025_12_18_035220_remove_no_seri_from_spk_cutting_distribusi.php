<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveNoSeriFromSpkCuttingDistribusi extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   public function up(): void
    {
        Schema::table('spk_cutting_distribusi', function (Blueprint $table) {
            $table->dropColumn('no_seri');
        });
    }

    public function down(): void
    {
        Schema::table('spk_cutting_distribusi', function (Blueprint $table) {
            $table->integer('no_seri')->nullable()->after('spk_cutting_id');
        });
    }
}
