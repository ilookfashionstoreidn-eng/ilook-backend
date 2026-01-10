<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSpkCuttingStatusLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   public function up()
    {
        Schema::create('spk_cutting_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spk_cutting_id')
                ->constrained('spk_cutting')
                ->cascadeOnDelete();

            $table->string('status');
            $table->text('keterangan')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('spk_cutting_status_logs');
    }
}
