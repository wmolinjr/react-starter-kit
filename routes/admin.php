<?php

declare(strict_types=1);

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ImpersonationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Routes (Super Admin)
|--------------------------------------------------------------------------
|
| Rotas exclusivas para super administradores.
| Inclui dashboard admin e funcionalidades de impersonation.
|
*/

Route::middleware(['web', 'auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    // Dashboard com lista de tenants
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');

    // Impersonation
    Route::prefix('impersonate')->name('impersonate.')->group(function () {
        // Iniciar impersonation de tenant (e opcionalmente usuário específico)
        Route::post('/tenant/{tenant}', [ImpersonationController::class, 'start'])->name('start');
        Route::post('/tenant/{tenant}/user/{user}', [ImpersonationController::class, 'start'])->name('start.user');

        // Parar impersonation
        Route::post('/stop', [ImpersonationController::class, 'stop'])->name('stop');
    });
});
