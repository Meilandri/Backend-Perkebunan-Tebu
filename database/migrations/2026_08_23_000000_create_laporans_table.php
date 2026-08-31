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
        Schema::create('laporans', function (Blueprint $table) {
            $table->id('id_laporan');
            $table->foreignId('id_pelapor')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('jenis_kejadian', [
                'Kebakaran tebu',
                'Serangan hama',
                'Penyakit tanaman',
                'Banjir/genangan',
                'Kendala lainnya'
            ]);
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('foto_bukti');
            $table->text('keterangan_tambahan')->nullable();
            $table->enum('status_penanganan', ['Open', 'On-Progress', 'Closed'])->default('Open');
            $table->timestamp('waktu_lapor')->useCurrent();
            $table->timestamps();

            // Database Indexing sesuai spesifikasi PDF (halaman 12)
            $table->index('status_penanganan');
            $table->index('waktu_lapor');
            $table->index(['latitude', 'longitude']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('laporans');
    }
};
