<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveStatusJasaFromSpkJasaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   public function up(): void
    {
        Schema::table('spk_jasa', function (Blueprint $table) {
            if (Schema::hasColumn('spk_jasa', 'status_jasa')) {
                $table->dropColumn('status_jasa');
            }
        });
    }

    public function down(): void
    {
        Schema::table('spk_jasa', function (Blueprint $table) {
            $table->string('status_jasa')->nullable();
        });
    }
}
