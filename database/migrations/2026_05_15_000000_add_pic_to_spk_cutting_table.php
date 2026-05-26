<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('spk_cutting')) {
            return;
        }

        Schema::table('spk_cutting', function (Blueprint $table) {
            if (!Schema::hasColumn('spk_cutting', 'pic')) {
                $table->string('pic')->nullable()->after('id_spk_cutting');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('spk_cutting') || !Schema::hasColumn('spk_cutting', 'pic')) {
            return;
        }

        Schema::table('spk_cutting', function (Blueprint $table) {
            $table->dropColumn('pic');
        });
    }
};
