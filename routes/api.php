<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LaporanController;
use App\Http\Controllers\Api\SektorController;
use App\Http\Controllers\Api\KategoriController;
use App\Http\Controllers\Api\TimPetugasController;
use App\Http\Controllers\Api\RiwayatController;

/*
|--------------------------------------------------------------------------
| API Routes - Monitoring Map Perkebunan Tebu
|--------------------------------------------------------------------------
*/

// Public Auth Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/guest', [AuthController::class, 'guestLogin']);

// Public Laporan Routes (Petani/Petugas dapat input tanpa harus berbelit auth)
Route::post('/laporan', [LaporanController::class, 'store']);
Route::get('/laporan/map', [LaporanController::class, 'mapData']); // Bounding box map query
Route::get('/laporan/summary', [LaporanController::class, 'summaryMetrics']); // Dashboard summary cache
Route::get('/user/statistics', [LaporanController::class, 'summaryMetrics']); // Alias

// Public Referensi (dibutuhkan Petani/Guest untuk mengisi dropdown form laporan)
Route::get('/sektors', [SektorController::class, 'index']);
Route::get('/sektor', [SektorController::class, 'index']); // Alias
Route::get('/kategoris', [KategoriController::class, 'index']);
Route::get('/kategori', [KategoriController::class, 'index']); // Alias
Route::get('/riwayat', [RiwayatController::class, 'index']); // Public access to riwayat

// Tim Petugas CRUD
Route::apiResource('tim-petugas', TimPetugasController::class);

// Protected Routes (Butuh autentikasi Sanctum / Session)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Manajemen Laporan
    Route::get('/laporan', [LaporanController::class, 'index']);
    Route::delete('/laporan', [LaporanController::class, 'destroyAll'])->middleware('role:Manajemen');
    Route::get('/laporan/{id}', [LaporanController::class, 'show']);
    Route::patch('/laporan/{id}/status', [LaporanController::class, 'updateStatus'])->middleware('role:Manajemen');
    Route::match(['put', 'patch'], '/laporan/{id}/selesai', [LaporanController::class, 'selesai']);

    // Manajemen Sektor & Kategori (index sudah publik di atas, sisanya hanya Manajemen)
    Route::apiResource('sektors', SektorController::class)->except(['show', 'index'])->middleware('role:Manajemen');
    Route::apiResource('sektor', SektorController::class)->except(['show', 'index'])->middleware('role:Manajemen'); // Alias
    Route::apiResource('kategoris', KategoriController::class)->only(['store', 'update', 'destroy'])->middleware('role:Manajemen');
    Route::apiResource('kategori', KategoriController::class)->only(['store', 'update', 'destroy'])->middleware('role:Manajemen'); // Alias
    
    // Riwayat
    Route::post('/riwayat', [RiwayatController::class, 'store']);
});