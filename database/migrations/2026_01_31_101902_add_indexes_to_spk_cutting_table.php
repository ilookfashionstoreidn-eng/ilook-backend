<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexesToSpkCuttingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    /**
     * ✅ OPTIMASI: Tambahkan indexes untuk meningkatkan performa query
     * 
     * @return void
     */
    public function up()
    {
        Schema::table('spk_cutting', function (Blueprint $table) {
            // Index untuk filter status_cutting (sering digunakan)
            $table->index('status_cutting', 'idx_spk_cutting_status');
            
            // Index untuk filter jenis_spk
            $table->index('jenis_spk', 'idx_spk_cutting_jenis_spk');
            
            // Index untuk filter created_at (untuk date range queries)
            $table->index('created_at', 'idx_spk_cutting_created_at');
            
            // Index untuk tukang_cutting_id (untuk generateSpkNumber dan relasi)
            $table->index('tukang_cutting_id', 'idx_spk_cutting_tukang_cutting_id');
            
            // Composite index untuk status + created_at (untuk progress cards)
            $table->index(['status_cutting', 'created_at'], 'idx_spk_cutting_status_created');
            
            // Composite index untuk tukang_cutting_id + id_spk_cutting (untuk generateSpkNumber LIKE query)
            $table->index(['tukang_cutting_id', 'id_spk_cutting'], 'idx_spk_cutting_tukang_spk');
            
            // Index untuk id_spk_cutting (untuk search)
            $table->index('id_spk_cutting', 'idx_spk_cutting_id_spk');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('spk_cutting', function (Blueprint $table) {
            $table->dropIndex('idx_spk_cutting_status');
            $table->dropIndex('idx_spk_cutting_jenis_spk');
            $table->dropIndex('idx_spk_cutting_created_at');
            $table->dropIndex('idx_spk_cutting_tukang_cutting_id');
            $table->dropIndex('idx_spk_cutting_status_created');
            $table->dropIndex('idx_spk_cutting_tukang_spk');
            $table->dropIndex('idx_spk_cutting_id_spk');
        });
    }
}
