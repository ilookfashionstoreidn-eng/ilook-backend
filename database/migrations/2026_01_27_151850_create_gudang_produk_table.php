<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGudangProdukTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   
    public function up(): void
    {
        Schema::create('gudang_produk', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['draft', 'terverifikasi'])->default('draft');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
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
        Schema::dropIfExists('gudang_produk');
    }
}
