<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('pembelian_bahan', function (Blueprint $table) {
            $table->string('sku')->nullable()->after('foto_surat_jalan');
        });
    }

    public function down()
    {
        Schema::table('pembelian_bahan', function (Blueprint $table) {
            $table->dropColumn(['sku',]);
        });
    }
};

