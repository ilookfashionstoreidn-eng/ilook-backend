<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('product_lists', function (Blueprint $table) {
            $table->string('ld')->nullable();
            $table->string('berat_pjg')->nullable();
            $table->string('satuan_bp')->nullable();
            $table->string('bp_combi')->nullable();
            $table->string('satuan_bp_combi')->nullable();
        });
    }

    public function down()
    {
        Schema::table('product_lists', function (Blueprint $table) {
            $table->dropColumn([
                'ld',
                'berat_pjg',
                'satuan_bp',
                'bp_combi',
                'satuan_bp_combi',
            ]);
        });
    }
};
