<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('spk_jasa', function (Blueprint $table) {
            $table->string('foto')->nullable()->after('tanggal_ambil');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('spk_jasa', function (Blueprint $table) {
            $table->dropColumn('foto');
        });
    }
};
