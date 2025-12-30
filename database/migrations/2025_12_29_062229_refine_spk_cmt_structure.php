<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RefineSpkCmtStructure extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
  public function up()
    {
        Schema::table('spk_cmt', function (Blueprint $table) {

            if (Schema::hasColumn('spk_cmt', 'id_produk')) {

                // 🔥 paksa drop FK kalau ada (aman walau FK tidak ada)
                DB::statement("
                    ALTER TABLE spk_cmt
                    DROP FOREIGN KEY spk_cmt_id_produk_foreign
                ");

                $table->dropColumn('id_produk');
            }

            if (Schema::hasColumn('spk_cmt', 'jumlah_produk')) {
                $table->dropColumn('jumlah_produk');
            }
        });
    }

    public function down()
    {
        Schema::table('spk_cmt', function (Blueprint $table) {
            $table->unsignedBigInteger('id_produk')->nullable();
            $table->integer('jumlah_produk')->nullable();
        });
    }

}
