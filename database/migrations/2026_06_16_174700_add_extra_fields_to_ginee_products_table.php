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
        Schema::table('ginee_products', function (Blueprint $table) {
            $table->string('category')->nullable()->after('size');
            $table->string('status')->nullable()->after('category');
            $table->text('description')->nullable()->after('image_url');
            $table->timestamp('created_at_ginee')->nullable()->after('description');
            $table->timestamp('updated_at_ginee')->nullable()->after('created_at_ginee');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ginee_products', function (Blueprint $table) {
            $table->dropColumn([
                'category',
                'status',
                'description',
                'created_at_ginee',
                'updated_at_ginee'
            ]);
        });
    }
};
