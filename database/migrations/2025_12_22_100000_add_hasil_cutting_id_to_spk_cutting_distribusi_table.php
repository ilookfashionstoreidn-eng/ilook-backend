<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHasilCuttingIdToSpkCuttingDistribusiTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('spk_cutting_distribusi', function (Blueprint $table) {
            $table->foreignId('hasil_cutting_id')
                ->nullable()
                ->after('spk_cutting_id')
                ->constrained('hasil_cutting')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('spk_cutting_distribusi', function (Blueprint $table) {
            $table->dropForeign(['hasil_cutting_id']);
            $table->dropColumn('hasil_cutting_id');
        });
    }
}
