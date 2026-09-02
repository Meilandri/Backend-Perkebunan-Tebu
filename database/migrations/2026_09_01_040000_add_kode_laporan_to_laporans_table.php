<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Nomor laporan yang dilihat pengguna diganti dari `#1`, `#2`, dst
     * (angka urut ID database) menjadi kode berbasis waktu pembuatan
     * laporan, format: detik-menit-jam-hari-bulan-tahun, mis.
     * "45-30-08-01-09-26-005" (3 digit terakhir = id_laporan, dipakai
     * SEMATA-MATA sebagai jaminan keunikan kalau ada 2 laporan masuk
     * persis di detik yang sama -- bukan bagian dari "nomor urut" yang
     * ditonjolkan).
     *
     * `id_laporan` (primary key, auto-increment) TETAP dipertahankan apa
     * adanya -- itu tetap dipakai untuk relasi database & routing URL.
     * Kolom `kode_laporan` ini murni untuk tampilan/identitas yang dilihat
     * pengguna.
     */
    public function up(): void
    {
        Schema::table('laporans', function (Blueprint $table) {
            $table->string('kode_laporan', 40)->nullable()->unique()->after('id_laporan');
        });

        // Backfill kode untuk baris yang sudah ada, dari waktu_lapor +
        // id_laporan masing-masing (supaya tidak ada baris lama yang kode-nya kosong).
        DB::table('laporans')->orderBy('id_laporan')->chunk(200, function ($rows) {
            foreach ($rows as $row) {
                $waktu = $row->waktu_lapor ? \Carbon\Carbon::parse($row->waktu_lapor) : \Carbon\Carbon::parse($row->created_at);
                $kode = $waktu->format('s-i-H-d-m-y') . '-' . str_pad($row->id_laporan, 3, '0', STR_PAD_LEFT);
                DB::table('laporans')->where('id_laporan', $row->id_laporan)->update(['kode_laporan' => $kode]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('laporans', function (Blueprint $table) {
            $table->dropColumn('kode_laporan');
        });
    }
};
