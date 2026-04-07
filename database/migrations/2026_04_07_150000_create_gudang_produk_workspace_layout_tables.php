<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGudangProdukWorkspaceLayoutTables extends Migration
{
    public function up(): void
    {
        Schema::create('gudang_produk_layouts', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('pic')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('name');
        });

        Schema::create('gudang_produk_layout_floors', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique();
            $table->foreignId('layout_id')
                ->constrained('gudang_produk_layouts')
                ->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('label')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['layout_id', 'number']);
        });

        Schema::create('gudang_produk_layout_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique();
            $table->foreignId('floor_id')
                ->constrained('gudang_produk_layout_floors')
                ->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('label')->nullable();
            $table->unsignedTinyInteger('layout_columns')->default(3);
            $table->unsignedInteger('layout_canvas_columns')->default(12);
            $table->unsignedInteger('layout_canvas_rows')->default(10);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['floor_id', 'code']);
        });

        Schema::create('gudang_produk_layout_racks', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique();
            $table->foreignId('block_id')
                ->constrained('gudang_produk_layout_blocks')
                ->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->unsignedInteger('rows')->default(1);
            $table->string('label')->nullable();
            $table->unsignedInteger('position_x')->nullable();
            $table->unsignedInteger('position_y')->nullable();
            $table->unsignedInteger('width_cells')->nullable();
            $table->unsignedInteger('height_cells')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['block_id', 'number']);
        });

        Schema::create('gudang_produk_slot_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('layout_id')
                ->constrained('gudang_produk_layouts')
                ->cascadeOnDelete();
            $table->string('slot_id')->unique();
            $table->string('alias')->nullable();
            $table->timestamps();

            $table->index(['layout_id', 'slot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gudang_produk_slot_aliases');
        Schema::dropIfExists('gudang_produk_layout_racks');
        Schema::dropIfExists('gudang_produk_layout_blocks');
        Schema::dropIfExists('gudang_produk_layout_floors');
        Schema::dropIfExists('gudang_produk_layouts');
    }
}
