<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    // CATATAN: migration ini dibuat khusus supaya akun Manajemen selalu ada
    // TANPA perlu akses ke Railway console (php artisan tinker). Migration
    // otomatis ikut jalan lewat `php artisan migrate --force` yang sudah
    // ada di proses deploy Railway kamu.
    //
    // Pakai updateOrInsert (bukan create biasa) supaya:
    // 1. Idempotent -- migration Laravel normalnya cuma jalan SEKALI dan
    //    dicatat di tabel `migrations`, jadi aman dijalankan ulang.
    // 2. Kalau suatu saat ada yang menjalankan `migrate:fresh` (drop semua
    //    tabel lalu migrate dari nol -- ini penyebab akun admin hilang
    //    berulang kali sebelumnya), migration ini ikut jalan ulang dari
    //    awal dan otomatis membuat akun adminnya lagi tanpa butuh tinker.
    //
    // Email & password bisa di-override lewat environment variable kalau
    // suatu saat kamu (atau siapa pun yang pegang Railway) mau ganti tanpa
    // perlu ubah kode -- tapi kalau tidak diset, nilai default di bawah
    // tetap dipakai, jadi TIDAK butuh konfigurasi tambahan apa pun di
    // Railway supaya ini berfungsi.
    public function up(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@agrowatch.test');
        $password = env('ADMIN_PASSWORD', 'admin123');

        DB::table('users')->updateOrInsert(
            ['email' => $email],
            [
                'name' => 'Admin AgroWatch',
                'password' => Hash::make($password),
                'peran_user' => 'Manajemen',
                'is_guest' => false,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        // Sengaja dibiarkan kosong -- rollback migration ini TIDAK boleh
        // menghapus akun admin (kalau ada migration lain yang rollback,
        // kita tidak mau tiba-tiba akun manajemen ikut hilang).
    }
};
