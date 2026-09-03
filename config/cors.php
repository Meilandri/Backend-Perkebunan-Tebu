<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    // "sanctum/csrf-cookie" & "login"/"logout" tanpa prefix sudah tidak
    // dipakai lagi sejak pindah ke Bearer token auth (lihat AuthController),
    // tapi dibiarkan di sini karena tidak mengganggu apa pun.
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],

    'allowed_methods' => ['*'],

    // FRONTEND_URL diisi persis dengan domain Vercel kamu, TANPA trailing
    // slash, misal: https://agrowatch.vercel.app
    // Kalau Vercel bikin banyak preview URL (per-branch/PR) dan kamu mau
    // semuanya ikut diizinkan, tambahkan pola di allowed_origins_patterns
    // di bawah (contoh sudah disiapkan, tinggal uncomment & sesuaikan).
    'allowed_origins' => array_filter([
        env('FRONTEND_URL'),
        'http://localhost:5173',
        'http://127.0.0.1:5173',
    ]),

    'allowed_origins_patterns' => [
        // '#^https://.*\.vercel\.app$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Sudah tidak pakai cookie/session (lihat AuthController) -- auth
    // sepenuhnya lewat header Authorization: Bearer, jadi credentials
    // (cookie) tidak perlu diizinkan lagi.
    'supports_credentials' => false,

];