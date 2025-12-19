<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTukangPolaIdToSpkCuttingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('spk_cutting', function (Blueprint $table) {
            if (!Schema::hasColumn('spk_cutting', 'tukang_pola_id')) {
                $table->foreignId('tukang_pola_id')->nullable()->after('tukang_cutting_id')->constrained('tukang_pola')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('spk_cutting', function (Blueprint $table) {
            if (Schema::hasColumn('spk_cutting', 'tukang_pola_id')) {
                $table->dropForeign(['tukang_pola_id']);
                $table->dropColumn('tukang_pola_id');
            }
        });
    }
}
