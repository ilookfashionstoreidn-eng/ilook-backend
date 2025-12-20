<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveKeteranganFromSpkJasaStatusLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   public function up(): void
    {
        Schema::table('spk_jasa_status_log', function (Blueprint $table) {
            $table->dropColumn('keterangan');
        });
    }

    public function down(): void
    {
        Schema::table('spk_jasa_status_log', function (Blueprint $table) {
            $table->text('keterangan')->nullable();
        });
    }
}
