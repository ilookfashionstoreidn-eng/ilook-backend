<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hasil_cutting', function (Blueprint $table) {
            if (!Schema::hasColumn('hasil_cutting', 'spk_cutting_distribusi_id')) {
                $table->foreignId('spk_cutting_distribusi_id')
                    ->nullable()
                    ->after('spk_cutting_id')
                    ->constrained('spk_cutting_distribusi')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('hasil_cutting', 'jenis_hasil')) {
                $table->string('jenis_hasil', 20)
                    ->default('utama')
                    ->after('spk_cutting_distribusi_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hasil_cutting', function (Blueprint $table) {
            if (Schema::hasColumn('hasil_cutting', 'jenis_hasil')) {
                $table->dropColumn('jenis_hasil');
            }

            if (Schema::hasColumn('hasil_cutting', 'spk_cutting_distribusi_id')) {
                $table->dropConstrainedForeignId('spk_cutting_distribusi_id');
            }
        });
    }
};
