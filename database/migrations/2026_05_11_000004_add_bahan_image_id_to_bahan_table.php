<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('bahan', function (Blueprint $table) {
            if (!Schema::hasColumn('bahan', 'bahan_image_id')) {
                $table->foreignId('bahan_image_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('bahan_images')
                    ->nullOnDelete();
            }
        });
    }

    public function down()
    {
        Schema::table('bahan', function (Blueprint $table) {
            if (Schema::hasColumn('bahan', 'bahan_image_id')) {
                $table->dropConstrainedForeignId('bahan_image_id');
            }
        });
    }
};
