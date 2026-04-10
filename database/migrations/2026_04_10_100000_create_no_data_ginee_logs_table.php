<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNoDataGineeLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('no_data_ginee_logs', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_number');
            $table->string('scanner_name');
            $table->foreignId('order_id')->nullable()->constrained('order')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('tracking_number');
            $table->index('scanner_name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('no_data_ginee_logs');
    }
}
