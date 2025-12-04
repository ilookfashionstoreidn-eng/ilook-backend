<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (Schema::hasColumn('pembelian_bahan', 'satuan')) {
            Schema::table('pembelian_bahan', function (Blueprint $table) {
                $table->dropColumn('satuan');
            });
        }
    }

    public function down()
    {
        if (!Schema::hasColumn('pembelian_bahan', 'satuan')) {
            Schema::table('pembelian_bahan', function (Blueprint $table) {
                $table->string('satuan')->nullable()->after('gramasi');
            });
        }
    }
};

