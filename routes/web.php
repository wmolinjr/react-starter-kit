<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

/*
|--------------------------------------------------------------------------
| Central App Routes (localhost)
|--------------------------------------------------------------------------
|
| Rotas da aplicação central (landing page, registro de tenants, pricing).
| Estas rotas são explicitamente limitadas aos central_domains configurados
| para evitar que a tenancy middleware seja inicializada.
|
| Pattern: Route::domain() para cada domínio central
|
*/

// Central domain routes - only accessible on localhost (or configured central domains)
foreach (config('tenancy.central_domains') as $domain) {
    Route::domain($domain)->middleware('web')->group(function () {
        // Landing page
        Route::get('/', function () {
            return Inertia::render('central/welcome', [
                'canRegister' => Features::enabled(Features::registration()),
            ]);
        })->name('home');

        // Login é gerenciado pelo Laravel Fortify (não precisa definir aqui)

        // Register tenant + user (será implementado em RegisterController)
        // POST /register será adicionado quando criar o controller

        // Central dashboard (lista de tenants do usuário)
        Route::get('/dashboard', function () {
            $user = auth()->user();

            $tenants = $user?->tenants()
                ->get()
                ->map(function ($tenant) use ($user) {
                    return [
                        'id' => $tenant->id,
                        'name' => $tenant->name,
                        'slug' => $tenant->slug,
                        'role' => $user->roleOn($tenant), // Usa Spatie Permission
                        'joined_at' => $tenant->pivot->joined_at,
                        'url' => "http://{$tenant->slug}.".config('tenancy.central_domains')[0],
                    ];
                }) ?? collect();

            return Inertia::render('central/dashboard', [
                'tenants' => $tenants,
            ]);
        })->middleware(['auth', 'verified'])->name('dashboard');
    });
}
