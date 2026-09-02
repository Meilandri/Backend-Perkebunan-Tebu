<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Kartu "Semua Insiden Hingga Saat Ini" di Ringkasan Operasional
     * SEBELUMNYA menghitung langsung `Laporan::count()` -- jumlah baris
     * yang MASIH ADA di tabel `laporans` saat ini. Akibatnya begitu fitur
     * "Hapus Semua Laporan" dipakai, angka ini ikut jatuh ke 0, padahal
     * maksudnya adalah total historis/akumulatif insiden yang PERNAH
     * dilaporkan sepanjang waktu -- angka itu seharusnya tidak pernah
     * berkurang hanya karena data laporan dibersihkan/diarsipkan.
     *
     * Tabel ini menyimpan satu baris counter independen yang HANYA
     * bertambah setiap kali laporan baru dibuat (lihat
     * LaporanController::store()), dan TIDAK PERNAH dikurangi/direset
     * oleh destroyAll() atau operasi hapus laporan apapun.
     */
    public function up(): void
    {
        Schema::create('laporan_counters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('total_count')->default(0);
            $table->timestamps();
        });

        // Seed baris tunggal, diawali dari jumlah laporan yang sudah ada
        // saat ini supaya angka historis tidak tiba-tiba jatuh ke 0 waktu
        // migration ini pertama kali dijalankan di database yang sudah
        // punya data.
        $existingTotal = Schema::hasTable('laporans') ? DB::table('laporans')->count() : 0;
        DB::table('laporan_counters')->insert([
            'id' => 1,
            'total_count' => $existingTotal,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_counters');
    }
};
