<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('bahan', function (Blueprint $table) {
            if (!Schema::hasColumn('bahan', 'harga')) {
                $table->decimal('harga', 15, 2)->nullable()->after('deskripsi');
            }
            if (!Schema::hasColumn('bahan', 'satuan')) {
                $table->string('satuan')->nullable()->after('harga');
            }
        });
    }

    public function down()
    {
        Schema::table('bahan', function (Blueprint $table) {
            if (Schema::hasColumn('bahan', 'satuan')) {
                $table->dropColumn('satuan');
            }
            if (Schema::hasColumn('bahan', 'harga')) {
                $table->dropColumn('harga');
            }
        });
    }
};

