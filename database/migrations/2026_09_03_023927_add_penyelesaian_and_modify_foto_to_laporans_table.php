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
            $table->timestamp('tgl_selesai')->nullable()->after('status_penanganan');
            $table->string('durasi_penanganan')->nullable()->after('tgl_selesai');
            $table->text('alat_digunakan')->nullable()->after('durasi_penanganan');
            $table->text('catatan_selesai')->nullable()->after('alat_digunakan');
            $table->text('foto_selesai')->nullable()->after('catatan_selesai');
            
            // Ubah foto_bukti menjadi TEXT jika belum (sebelumnya string)
            $table->text('foto_bukti')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('laporans', function (Blueprint $table) {
            $table->dropColumn(['tgl_selesai', 'durasi_penanganan', 'alat_digunakan', 'catatan_selesai', 'foto_selesai']);
            
            // Revert foto_bukti back to string (default length 255) if necessary
            $table->string('foto_bukti')->nullable()->change();
        });
    }
};
