<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePackingLogExportsTable extends Migration
{
    public function up()
    {
        Schema::create('packing_log_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('queued');
            $table->json('filters')->nullable();
            $table->string('file_name');
            $table->string('file_path')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'idx_packing_log_exports_status_created');
        });
    }

    public function down()
    {
        Schema::dropIfExists('packing_log_exports');
    }
}
