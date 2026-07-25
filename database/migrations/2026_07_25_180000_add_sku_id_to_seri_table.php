<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSkuIdToSeriTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('seri', function (Blueprint $table) {
            // Nullable: existing rows are backfilled separately (best-effort exact
            // match, never guessed) — see database/migrations backfill script.
            // Once set here, this is the single source of truth for a seri's SKU;
            // resolveBarcodeDetails() reads it directly instead of re-deriving the
            // sku_id from `sku` text via fuzzy matching on every scan.
            $table->unsignedBigInteger('sku_id')->nullable()->after('sku');
            $table->foreign('sku_id')->references('id')->on('skus')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('seri', function (Blueprint $table) {
            $table->dropForeign(['sku_id']);
            $table->dropColumn('sku_id');
        });
    }
}
