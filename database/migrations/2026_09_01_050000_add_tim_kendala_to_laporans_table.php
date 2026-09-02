<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SEBELUMNYA "Tim Penanggung Jawab" dan "Kendala" tidak punya kolom
     * sendiri -- keduanya digabung jadi satu string di
     * `catatan_tindak_lanjut` (format: "Tim: X (Kendala: Y). instruksi...")
     * lalu tim/kendala tidak pernah dibaca ulang, hanya ditulis sekali saat
     * disimpan. Akibatnya form Tindak Lanjut selalu mulai kosong lagi
     * setiap dibuka ulang untuk laporan yang sama, walau sudah pernah
     * diisi sebelumnya.
     *
     * Kolom terpisah di bawah ini membuat data bisa dibaca ulang secara
     * langsung (bukan di-parse dari gabungan teks yang rapuh), sehingga
     * form Tindak Lanjut bisa benar-benar di-prefill & diedit.
     * `catatan_tindak_lanjut` sekarang murni berisi instruksi/catatan
     * bebas, terpisah dari nama tim & kendala.
     */
    public function up(): void
    {
        Schema::table('laporans', function (Blueprint $table) {
            $table->string('tim_penanggung_jawab')->nullable()->after('status_penanganan');
            $table->string('kendala')->nullable()->after('tim_penanggung_jawab');
        });
    }

    public function down(): void
    {
        Schema::table('laporans', function (Blueprint $table) {
            $table->dropColumn(['tim_penanggung_jawab', 'kendala']);
        });
    }
};
