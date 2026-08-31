<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LaporanController;

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

// Protected Routes (Butuh autentikasi Sanctum / Session)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Manajemen Laporan
    Route::get('/laporan', [LaporanController::class, 'index']);
    Route::get('/laporan/{id}', [LaporanController::class, 'show']);
    Route::patch('/laporan/{id}/status', [LaporanController::class, 'updateStatus']);
});