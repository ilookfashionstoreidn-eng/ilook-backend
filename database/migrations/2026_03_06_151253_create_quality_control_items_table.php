<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQualityControlItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('quality_control_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quality_control_id')->constrained('quality_controls')->onDelete('cascade');
            $table->enum('status', ['lolos', 'reject']);
            $table->string('sku');
            $table->integer('jumlah');
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
        Schema::dropIfExists('quality_control_items');
    }
}
