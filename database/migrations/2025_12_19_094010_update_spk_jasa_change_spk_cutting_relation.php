<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateSpkJasaChangeSpkCuttingRelation extends Migration
{
     public function up(): void
    {
        Schema::table('spk_jasa', function (Blueprint $table) {

            // hapus FK dulu jika ada
            if (Schema::hasColumn('spk_jasa', 'spk_cutting_id')) {
                $table->dropForeign(['spk_cutting_id']);
                $table->dropColumn('spk_cutting_id');
            }

            // tambah kolom baru
            $table->foreignId('spk_cutting_distribusi_id')
                ->after('tukang_jasa_id')
                ->constrained('spk_cutting_distribusi')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('spk_jasa', function (Blueprint $table) {

            $table->dropForeign(['spk_cutting_distribusi_id']);
            $table->dropColumn('spk_cutting_distribusi_id');

            $table->foreignId('spk_cutting_id')
                ->after('tukang_jasa_id')
                ->constrained('spk_cutting')
                ->cascadeOnDelete();
        });
    }
}
