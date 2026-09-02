<?php

namespace Database\Seeders;

use App\Models\KategoriKejadian;
use Illuminate\Database\Seeder;

class KategoriKejadianSeeder extends Seeder
{
    /**
     * Seed kategori kejadian awal.
     *
     * PENTING: string di sini SENGAJA disamakan persis (huruf besar/kecil
     * termasuk) dengan yang di-hardcode di
     * LaporanController::summaryMetrics() (key 'by_jenis'), supaya
     * statistik "Jenis Insiden" di dashboard Ringkasan Manajemen ikut
     * terhitung benar. Kalau kamu ubah/tambah kategori lewat menu
     * Pengaturan nanti, kategori baru di luar 5 ini TIDAK akan masuk
     * hitungan by_jenis (itu keterbatasan controller saat ini, bukan
     * masalah seeder).
     */
    public function run(): void
    {
        $kategoris = [
            'Kebakaran tebu',
            'Serangan hama',
            'Penyakit tanaman',
            'Banjir/genangan',
            'Kendala lainnya',
        ];

        foreach ($kategoris as $nama) {
            KategoriKejadian::firstOrCreate(['nama_kategori' => $nama]);
        }
    }
}