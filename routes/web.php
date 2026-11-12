<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Panitia\DrawController;
use App\Http\Controllers\Panitia\RosterController;
use App\Http\Controllers\PublicDrawController;
use Illuminate\Support\Facades\Route;

/* ---------- Publik: hanya melihat, tanpa login ---------- */
Route::get('/', [PublicDrawController::class, 'index'])->name('draw.viewer');
Route::get('/draw/state', [PublicDrawController::class, 'state'])
    ->middleware('throttle:300,1')
    ->name('draw.state');

/* ---------- Auth panitia ---------- */
Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('login.store');
Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/* ---------- Panitia: wajib login ---------- */
Route::middleware('auth')->prefix('panitia')->name('panitia.')->group(function () {
    Route::get('/', [DrawController::class, 'index'])->name('console');
    Route::post('/draw', [DrawController::class, 'start'])->name('draw.start');
    Route::post('/draw/{draw}/advance', [DrawController::class, 'advance'])->name('draw.advance');
    Route::post('/draw/{draw}/cancel', [DrawController::class, 'cancel'])->name('draw.cancel');

    // Kelola peserta (satu tabel: diundi + di luar undian). type: p = peserta, x = di luar undian
    Route::get('/peserta', [RosterController::class, 'index'])->name('roster');
    Route::post('/peserta', [RosterController::class, 'store'])->name('roster.store');
    Route::put('/peserta/{type}/{id}', [RosterController::class, 'update'])
        ->whereIn('type', ['p', 'x'])->whereNumber('id')->name('roster.update');
    Route::delete('/peserta/{type}/{id}', [RosterController::class, 'destroy'])
        ->whereIn('type', ['p', 'x'])->whereNumber('id')->name('roster.destroy');
});
