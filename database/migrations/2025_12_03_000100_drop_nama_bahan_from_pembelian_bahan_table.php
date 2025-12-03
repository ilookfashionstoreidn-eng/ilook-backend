<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (Schema::hasColumn('pembelian_bahan', 'nama_bahan')) {
            Schema::table('pembelian_bahan', function (Blueprint $table) {
                $table->dropColumn('nama_bahan');
            });
        }
    }

    public function down()
    {
        if (!Schema::hasColumn('pembelian_bahan', 'nama_bahan')) {
            Schema::table('pembelian_bahan', function (Blueprint $table) {
                $table->string('nama_bahan')->nullable();
            });
        }
    }
};

