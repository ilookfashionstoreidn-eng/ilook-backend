<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterOrdersSkuToText extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
  public function up()
{
    Schema::table('order', function (Blueprint $table) {
        $table->text('sku')->change();
    });
}

public function down()
{
    Schema::table('order', function (Blueprint $table) {
        $table->string('sku')->change();
    });
}

}
