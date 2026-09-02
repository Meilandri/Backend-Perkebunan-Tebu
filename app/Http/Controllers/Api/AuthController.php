<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'nomor_hp' => 'nullable|string|max:20',
            'peran_user' => 'nullable|in:Petugas Lapangan,Manajemen',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'nomor_hp' => $request->nomor_hp,
            'peran_user' => $request->peran_user ?? 'Petugas Lapangan',
            'is_guest' => false,
        ]);

        Auth::login($user);

        return response()->json([
            'message' => 'Registrasi berhasil',
            'user' => $user
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['Kredensial yang diberikan tidak cocok.'],
            ]);
        }

        $request->session()->regenerate();

        return response()->json([
            'message' => 'Login berhasil',
            'user' => Auth::user()
        ]);
    }

    public function guestLogin(Request $request)
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);

        if ($request->filled('email')) {
            // Dipanggil dari alur Google Login (frontend mengirim nama &
            // email asli dari profil Google). Pakai firstOrNew berdasarkan
            // email, BUKAN selalu User::create, supaya:
            // 1. Login berulang dari akun Google yang sama tidak melanggar
            //    constraint unique('email') di tabel users.
            // 2. Laporan yang pernah dikirim user ini sebelumnya tetap
            //    tercatat di bawah nama yang sama secara konsisten,
            //    bukan berpindah-pindah ke akun tamu baru setiap login.
            // SEBELUMNYA method ini mengabaikan $request sepenuhnya dan
            // selalu membuat akun tamu baru dengan nama random
            // "Tamu (Guest ####)" -- itulah sebabnya nama di halaman
            // Detail Laporan tidak pernah cocok dengan nama Google yang
            // tampil di dashboard.
            $guestUser = User::firstOrNew(['email' => $request->email]);
            $guestUser->name = $request->name ?: ($guestUser->name ?: 'Petani (Google)');
            $guestUser->peran_user = $guestUser->peran_user ?: 'Petugas Lapangan';
            $guestUser->is_guest = true;
            $guestUser->save();
        } else {
            // Akses Tamu murni tanpa profil (tombol "Akses Tamu" di Login)
            // -- perilaku lama tetap dipertahankan: akun tamu anonim baru
            // setiap kali.
            $guestUser = User::create([
                'name' => 'Tamu (Guest ' . rand(1000, 9999) . ')',
                'email' => null,
                'password' => null,
                'peran_user' => 'Petugas Lapangan',
                'is_guest' => true,
            ]);
        }

        Auth::login($guestUser);

        return response()->json([
            'message' => 'Masuk sebagai Tamu berhasil',
            'user' => $guestUser
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user()
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out successfully']);
    }
}