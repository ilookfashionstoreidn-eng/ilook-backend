<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (!Schema::hasColumn('pembelian_bahan', 'bahan_id')) {
            Schema::table('pembelian_bahan', function (Blueprint $table) {
                $table->foreignId('bahan_id')->nullable()->constrained('bahan')->after('sku');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('pembelian_bahan', 'bahan_id')) {
            Schema::table('pembelian_bahan', function (Blueprint $table) {
                $table->dropForeign(['bahan_id']);
                $table->dropColumn('bahan_id');
            });
        }
    }
};
