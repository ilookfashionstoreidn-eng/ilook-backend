<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hasil_cutting', function (Blueprint $table) {
            $table->unique(
                ['spk_cutting_distribusi_id', 'jenis_hasil'],
                'hasil_cutting_distribusi_jenis_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('hasil_cutting', function (Blueprint $table) {
            $table->dropUnique('hasil_cutting_distribusi_jenis_unique');
        });
    }
};
