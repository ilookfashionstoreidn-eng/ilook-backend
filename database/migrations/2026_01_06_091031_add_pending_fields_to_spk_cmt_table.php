<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPendingFieldsToSpkCmtTable extends Migration
{

  public function up(): void
    {
        Schema::table('spk_cmt', function (Blueprint $table) {
            $table->timestamp('pending_at')->nullable()->after('status');
            $table->date('pending_until')->nullable()->after('pending_at');
        });
    }

    public function down(): void
    {
        Schema::table('spk_cmt', function (Blueprint $table) {
            $table->dropColumn(['pending_at', 'pending_until']);
        });
    }
}
