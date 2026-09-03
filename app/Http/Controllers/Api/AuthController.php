<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // CATATAN MIGRASI KE TOKEN AUTH
    // ------------------------------
    // SEBELUMNYA controller ini pakai Auth::login()/Auth::attempt() yang
    // mengandalkan session cookie ("web" guard) -- itu cuma reliable kalau
    // FE & BE satu root domain. Karena FE (Vercel) & BE (Railway) dideploy
    // di domain yang beda tanpa custom domain, cookie session cross-site
    // gampang diblokir browser. Sekarang tiap endpoint auth di bawah
    // langsung memverifikasi kredensial secara manual (tanpa menyentuh
    // session sama sekali) lalu menerbitkan Sanctum Personal Access Token
    // yang dikirim balik sebagai field `token` di response JSON. Frontend
    // menyimpan token itu dan mengirimkannya lewat header
    // `Authorization: Bearer <token>` di setiap request selanjutnya.
    //
    // PENTING: pastikan model App\Models\User memakai trait
    // Laravel\Sanctum\HasApiTokens, contoh:
    //
    //     use Laravel\Sanctum\HasApiTokens;
    //     use Illuminate\Notifications\Notifiable;
    //
    //     class User extends Authenticatable
    //     {
    //         use HasApiTokens, Notifiable;
    //         ...
    //     }
    //
    // Tanpa trait ini, pemanggilan $user->createToken(...) di bawah akan
    // error ("Call to undefined method createToken()").

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

        $token = $user->createToken('spa-token')->plainTextToken;

        return response()->json([
            'message' => 'Registrasi berhasil',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! $user->password || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Kredensial yang diberikan tidak cocok.'],
            ]);
        }

        $token = $user->createToken('spa-token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil',
            'user' => $user,
            'token' => $token,
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
            $guestUser = User::firstOrNew(['email' => $request->email]);
            $guestUser->name = $request->name ?: ($guestUser->name ?: 'Petani (Google)');
            $guestUser->peran_user = $guestUser->peran_user ?: 'Petugas Lapangan';
            $guestUser->is_guest = true;
            $guestUser->save();
        } else {
            // Akses Tamu murni tanpa profil (tombol "Akses Tamu" di Login)
            // -- akun tamu anonim baru setiap kali.
            $guestUser = User::create([
                'name' => 'Tamu (Guest ' . rand(1000, 9999) . ')',
                'email' => null,
                'password' => null,
                'peran_user' => 'Petugas Lapangan',
                'is_guest' => true,
            ]);
        }

        $token = $guestUser->createToken('spa-token')->plainTextToken;

        return response()->json([
            'message' => 'Masuk sebagai Tamu berhasil',
            'user' => $guestUser,
            'token' => $token,
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
        // Cabut HANYA token yang dipakai untuk request ini (bukan semua
        // token milik user), supaya kalau user login dari perangkat/tab
        // lain, sesi di tempat lain itu tidak ikut ter-logout.
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logout berhasil']);
    }
}