<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_packing_result_serials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_packing_result_id')
                ->constrained('order_packing_results')
                ->onDelete('cascade');
            $table->string('serial_number');
            $table->timestamps();

            $table->index('serial_number', 'idx_order_packing_result_serials_serial');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_packing_result_serials');
    }
};
