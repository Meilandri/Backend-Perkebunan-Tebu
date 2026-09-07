<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('laporans', function (Blueprint $table) {
            if (!Schema::hasColumn('laporans', 'tim_penanggung_jawab')) {
                $table->string('tim_penanggung_jawab')->nullable()->after('status_penanganan');
            }
            if (!Schema::hasColumn('laporans', 'kendala')) {
                $table->string('kendala')->nullable()->after('tim_penanggung_jawab');
            }
            if (!Schema::hasColumn('laporans', 'catatan_selesai')) {
                $table->text('catatan_selesai')->nullable()->after('kendala');
            }
            if (!Schema::hasColumn('laporans', 'durasi_penanganan')) {
                $table->string('durasi_penanganan')->nullable()->after('catatan_selesai');
            }
            if (!Schema::hasColumn('laporans', 'alat_digunakan')) {
                $table->string('alat_digunakan')->nullable()->after('durasi_penanganan');
            }
            if (!Schema::hasColumn('laporans', 'tgl_selesai')) {
                $table->timestamp('tgl_selesai')->nullable()->after('alat_digunakan');
            }
            if (!Schema::hasColumn('laporans', 'foto_selesai')) {
                $table->string('foto_selesai')->nullable()->after('tgl_selesai');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('laporans', function (Blueprint $table) {
            $columnsToDrop = [];
            foreach ([
                'tim_penanggung_jawab',
                'kendala',
                'catatan_selesai',
                'durasi_penanganan',
                'alat_digunakan',
                'tgl_selesai',
                'foto_selesai',
            ] as $column) {
                if (Schema::hasColumn('laporans', $column)) {
                    $columnsToDrop[] = $column;
                }
            }

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
