<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddPackingOutToGudangProdukActivityLogsTypeEnum extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE gudang_produk_activity_logs MODIFY COLUMN `type` ENUM('placement', 'mutation', 'packing_out') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE gudang_produk_activity_logs MODIFY COLUMN `type` ENUM('placement', 'mutation') NOT NULL");
    }
}
