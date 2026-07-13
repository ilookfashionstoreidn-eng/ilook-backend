<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('ginee_order_id')->index();       // orderId dari Ginee
            $table->string('entity')->default('order');       // order / product / dll
            $table->string('action')->nullable();             // CREATE / UPDATE
            $table->string('status')->default('received');    // received / processed / failed
            $table->text('error_message')->nullable();        // jika gagal
            $table->json('raw_payload')->nullable();          // payload lengkap dari Ginee
            $table->unsignedBigInteger('order_id')->nullable()->index(); // relasi ke orders (tanpa FK constraint)
            $table->timestamps();                             // created_at = waktu webhook diterima
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
