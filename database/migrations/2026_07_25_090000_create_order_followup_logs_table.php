<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderFollowupLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_followup_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('order')->onDelete('cascade');
            $table->string('status'); // PENDING/CONTACTED/UNREACHABLE/CONFIRMED/RESCHEDULED/COLOR_CHANGE/CANCELLED/DONE
            $table->text('notes')->nullable();
            $table->string('performed_by')->nullable(); // nama CS yang mencatat follow-up
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order_followup_logs');
    }
}
