<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('product_lists')) {
            return;
        }

        $columns = array_map('strtolower', Schema::getColumnListing('product_lists'));

        Schema::table('product_lists', function (Blueprint $table) use ($columns) {
            if (!in_array('ld', $columns, true)) {
                $table->string('ld')->nullable();
            }

            if (!in_array('berat_pjg', $columns, true)) {
                $table->string('berat_pjg')->nullable();
            }

            if (!in_array('satuan_bp', $columns, true)) {
                $table->string('satuan_bp')->nullable();
            }

            if (!in_array('bp_combi', $columns, true)) {
                $table->string('bp_combi')->nullable();
            }

            if (!in_array('satuan_bp_combi', $columns, true)) {
                $table->string('satuan_bp_combi')->nullable();
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('product_lists')) {
            return;
        }

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