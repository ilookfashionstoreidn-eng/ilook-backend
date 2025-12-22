<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class UpdateSpkJasaChangeSpkCuttingRelation extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        // Hapus foreign key dan kolom spk_cutting_id jika ada
        if (Schema::hasColumn('spk_jasa', 'spk_cutting_id')) {
            // Hapus foreign key dengan raw SQL untuk menghindari error jika tidak ada
            try {
                $foreignKeys = DB::select("
                    SELECT CONSTRAINT_NAME  
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'spk_jasa' 
                    AND COLUMN_NAME = 'spk_cutting_id' 
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ");

                foreach ($foreignKeys as $fk) {
                    try {
                        DB::statement("ALTER TABLE spk_jasa DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
                    } catch (\Exception $e) {
                        // Foreign key mungkin sudah dihapus, lanjutkan
                    }
                }
            } catch (\Exception $e) {
                // Query mungkin gagal, lanjutkan
            }

            Schema::table('spk_jasa', function (Blueprint $table) {
                $table->dropColumn('spk_cutting_id');
            });
        }

        // Hapus foreign key dan kolom spk_cutting_distribusi_id jika sudah ada
        if (Schema::hasColumn('spk_jasa', 'spk_cutting_distribusi_id')) {
            // Hapus foreign key dengan raw SQL untuk menghindari error jika tidak ada
            try {
                // Cek foreign key dari information_schema dengan query yang lebih spesifik
                $dbName = DB::getDatabaseName();
                $foreignKeys = DB::select("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = ? 
                    AND TABLE_NAME = 'spk_jasa' 
                    AND COLUMN_NAME = 'spk_cutting_distribusi_id' 
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ", [$dbName]);

                foreach ($foreignKeys as $fk) {
                    try {
                        DB::statement("ALTER TABLE spk_jasa DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
                    } catch (\Exception $e) {
                        // Foreign key mungkin sudah dihapus, lanjutkan
                    }
                }
            } catch (\Exception $e) {
                // Query mungkin gagal, coba hapus dengan nama default
                try {
                    DB::statement("ALTER TABLE spk_jasa DROP FOREIGN KEY spk_jasa_spk_cutting_distribusi_id_foreign");
                } catch (\Exception $e2) {
                    // Foreign key tidak ada, lanjutkan
                }
            }

            Schema::table('spk_jasa', function (Blueprint $table) {
                $table->dropColumn('spk_cutting_distribusi_id');
            });
        }

        // Tambah kolom nullable tanpa constraint terlebih dahulu
        Schema::table('spk_jasa', function (Blueprint $table) {
            $table->unsignedBigInteger('spk_cutting_distribusi_id')
                ->nullable()
                ->after('tukang_jasa_id');
        });

        // Bersihkan data yang tidak valid (set NULL untuk nilai yang tidak ada di tabel spk_cutting_distribusi)
        if (Schema::hasTable('spk_cutting_distribusi')) {
            DB::statement('UPDATE spk_jasa SET spk_cutting_distribusi_id = NULL WHERE spk_cutting_distribusi_id IS NOT NULL AND spk_cutting_distribusi_id NOT IN (SELECT id FROM spk_cutting_distribusi)');
        } else {
            // Jika tabel belum ada, set semua menjadi NULL
            DB::statement('UPDATE spk_jasa SET spk_cutting_distribusi_id = NULL WHERE spk_cutting_distribusi_id IS NOT NULL');
        }

        // Tambahkan foreign key constraint setelah data dibersihkan
        Schema::table('spk_jasa', function (Blueprint $table) {
            $table->foreign('spk_cutting_distribusi_id')
                ->references('id')
                ->on('spk_cutting_distribusi')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('spk_jasa', function (Blueprint $table) {

            $table->dropForeign(['spk_cutting_distribusi_id']);
            $table->dropColumn('spk_cutting_distribusi_id');

            $table->foreignId('spk_cutting_id')
                ->after('tukang_jasa_id')
                ->constrained('spk_cutting')
                ->cascadeOnDelete();
        });
    }
}
