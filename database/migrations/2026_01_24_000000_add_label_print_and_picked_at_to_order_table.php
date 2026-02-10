<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLabelPrintAndPickedAtToOrderTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order', function (Blueprint $table) {
            $table->string('label_print_status')->nullable()->after('is_packed');
            $table->timestamp('label_print_time')->nullable()->after('label_print_status');
            $table->timestamp('picked_at')->nullable()->after('label_print_time');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order', function (Blueprint $table) {
            $table->dropColumn(['label_print_status', 'label_print_time', 'picked_at']);
        });
    }
}
