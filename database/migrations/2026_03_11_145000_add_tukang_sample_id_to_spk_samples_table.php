<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spk_samples', function (Blueprint $table) {
            $table->unsignedBigInteger('tukang_sample_id')->nullable()->after('foto');
            $table->foreign('tukang_sample_id')
                  ->references('id')
                  ->on('tukang_samples')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('spk_samples', function (Blueprint $table) {
            $table->dropForeign(['tukang_sample_id']);
            $table->dropColumn('tukang_sample_id');
        });
    }
};
