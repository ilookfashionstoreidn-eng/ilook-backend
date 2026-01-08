<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInvoiceFieldsToPendapatanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pendapatan', function (Blueprint $table) {
            $table->boolean('kurangi_hutang')->default(false)->after('status_pembayaran');
            $table->boolean('kurangi_cashbon')->default(false)->after('kurangi_hutang');
            $table->json('detail_aksesoris_ids')->nullable()->after('kurangi_cashbon');
            $table->json('claim_ids')->nullable()->after('detail_aksesoris_ids');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pendapatan', function (Blueprint $table) {
            $table->dropColumn(['kurangi_hutang', 'kurangi_cashbon', 'detail_aksesoris_ids', 'claim_ids']);
        });
    }
}

