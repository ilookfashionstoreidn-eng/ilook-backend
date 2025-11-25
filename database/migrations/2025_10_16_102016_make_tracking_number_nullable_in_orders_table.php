<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeTrackingNumberNullableInOrdersTable extends Migration
{
    public function up()
{
    Schema::table('order', function (Blueprint $table) {
        $table->string('tracking_number')->nullable()->change();
    });
}

public function down()
{
    Schema::table('order', function (Blueprint $table) {
        $table->string('tracking_number')->nullable(false)->change();
    });
}

}
