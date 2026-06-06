<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gudang_produk_cancelled_seri_prints')) {
            return;
        }

        Schema::create('gudang_produk_cancelled_seri_prints', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seri_id')->nullable()->index();
            $table->string('nomor_seri')->index();
            $table->unsignedInteger('print_seq')->index();
            $table->string('barcode_seri')->unique();
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gudang_produk_cancelled_seri_prints');
    }
};
