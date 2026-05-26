<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (!Schema::hasTable('bahan') || !Schema::hasColumn('bahan', 'pabrik_bahan')) {
            return;
        }

        DB::table('bahan')
            ->whereNull('pabrik_bahan')
            ->update(['pabrik_bahan' => '-']);

        DB::table('bahan')
            ->where('pabrik_bahan', '')
            ->update(['pabrik_bahan' => '-']);
    }

    public function down()
    {
        if (!Schema::hasTable('bahan') || !Schema::hasColumn('bahan', 'pabrik_bahan')) {
            return;
        }

        DB::table('bahan')
            ->where('pabrik_bahan', '-')
            ->update(['pabrik_bahan' => null]);
    }
};
