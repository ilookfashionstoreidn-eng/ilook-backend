<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('bahan', function (Blueprint $table) {
            if (!Schema::hasColumn('bahan', 'group_bahan')) {
                $table->string('group_bahan')->nullable()->after('id');
            }

            if (!Schema::hasColumn('bahan', 'warna_bahan')) {
                $table->string('warna_bahan')->nullable()->after('satuan');
            }
        });
    }

    public function down()
    {
        Schema::table('bahan', function (Blueprint $table) {
            if (Schema::hasColumn('bahan', 'warna_bahan')) {
                $table->dropColumn('warna_bahan');
            }

            if (Schema::hasColumn('bahan', 'group_bahan')) {
                $table->dropColumn('group_bahan');
            }
        });
    }
};
