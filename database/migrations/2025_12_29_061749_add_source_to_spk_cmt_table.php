<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSourceToSpkCmtTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('spk_cmt', function (Blueprint $table) {
           $table->string('source_type')
                ->after('id_spk')
                ->comment('cutting | jasa');

            $table->unsignedBigInteger('source_id')
                ->after('source_type');

            // optional index biar cepat
            $table->index(['source_type', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('spk_cmt', function (Blueprint $table) {
           $table->dropIndex(['source_type', 'source_id']);
           $table->dropColumn(['source_type', 'source_id']);
        });
    }
}
