<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (!Schema::hasTable('product_list_images')) {
            Schema::create('product_list_images', function (Blueprint $table) {
                $table->id();
                $table->string('image_path');
                $table->timestamps();
            });
        }

        if (Schema::hasTable('product_lists') && !Schema::hasColumn('product_lists', 'product_list_image_id')) {
            Schema::table('product_lists', function (Blueprint $table) {
                $table->foreignId('product_list_image_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('product_list_images')
                    ->nullOnDelete();
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('product_lists') && Schema::hasColumn('product_lists', 'product_list_image_id')) {
            Schema::table('product_lists', function (Blueprint $table) {
                $table->dropConstrainedForeignId('product_list_image_id');
            });
        }

        Schema::dropIfExists('product_list_images');
    }
};
