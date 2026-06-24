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
        Schema::table('pengiriman', function (Blueprint $table) {
            $table->unsignedBigInteger('id_penjahit')->nullable()->after('id_spk');
            $table->foreign('id_penjahit')->references('id_penjahit')->on('penjahit_cmt')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pengiriman', function (Blueprint $table) {
            $table->dropForeign(['id_penjahit']);
            $table->dropColumn('id_penjahit');
        });
    }
};
