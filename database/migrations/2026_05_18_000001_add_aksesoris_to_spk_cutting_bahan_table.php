<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAksesorisToSpkCuttingBahanTable extends Migration
{
    public function up()
    {
        Schema::table('spk_cutting_bahan', function (Blueprint $table) {
            if (!Schema::hasColumn('spk_cutting_bahan', 'sumber_komponen')) {
                $table->string('sumber_komponen', 20)->default('bahan')->after('spk_cutting_bagian_id');
            }

            if (!Schema::hasColumn('spk_cutting_bahan', 'aksesoris_id')) {
                $table->unsignedBigInteger('aksesoris_id')->nullable()->after('bahan_id');
            }
        });

        Schema::table('spk_cutting_bahan', function (Blueprint $table) {
            if (Schema::hasColumn('spk_cutting_bahan', 'bahan_id')) {
                $table->unsignedBigInteger('bahan_id')->nullable()->change();
            }
        });

        Schema::table('spk_cutting_bahan', function (Blueprint $table) {
            try {
                $table->foreign('aksesoris_id')->references('id')->on('aksesoris')->onDelete('restrict');
            } catch (\Exception $e) {
                // Foreign key may already exist on some environments.
            }
        });
    }

    public function down()
    {
        Schema::table('spk_cutting_bahan', function (Blueprint $table) {
            if (Schema::hasColumn('spk_cutting_bahan', 'aksesoris_id')) {
                try {
                    $table->dropForeign(['aksesoris_id']);
                } catch (\Exception $e) {
                    // Foreign key may not exist on some environments.
                }

                $table->dropColumn('aksesoris_id');
            }

            if (Schema::hasColumn('spk_cutting_bahan', 'sumber_komponen')) {
                $table->dropColumn('sumber_komponen');
            }
        });
    }
}
