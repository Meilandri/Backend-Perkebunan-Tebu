<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class RiwayatAktivitas extends Model
{
    protected $table = 'riwayat_aktivitas';

    protected $fillable = [
        'user_id',
        'nama_user',
        'role',
        'aksi',
        'deskripsi',
    ];

    /**
     * Helper statis untuk mencatat riwayat aktivitas secara otomatis.
     */
    public static function catat($aksi, $deskripsi = null, $user = null)
    {
        $currentUser = $user ?? Auth::user() ?? request()->user('sanctum');

        // SEBELUMNYA di sini pakai $currentUser->nama_user -- tapi kolom
        // itu tidak ada di model User (lihat User.php, kolomnya "name").
        // Akibatnya nama_user yang tersimpan di setiap baris riwayat selalu
        // null, walau user-nya jelas login. Diganti ke $currentUser->name.
        self::create([
            'user_id' => $currentUser ? $currentUser->id : null,
            'nama_user' => $currentUser ? $currentUser->name : 'Sistem / Guest',
            'role' => $currentUser ? $currentUser->peran_user : 'System',
            'aksi' => $aksi,
            'deskripsi' => $deskripsi,
        ]);
    }
}
