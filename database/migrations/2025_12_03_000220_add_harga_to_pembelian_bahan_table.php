<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('pembelian_bahan', function (Blueprint $table) {
            if (!Schema::hasColumn('pembelian_bahan', 'harga')) {
                $table->decimal('harga', 15, 2)->nullable()->after('sku');
            }
        });
    }

    public function down()
    {
        Schema::table('pembelian_bahan', function (Blueprint $table) {
            if (Schema::hasColumn('pembelian_bahan', 'harga')) {
                $table->dropColumn('harga');
            }
        });
    }
};

