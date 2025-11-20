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
            return Inertia::render('welcome', [
                'canRegister' => Features::enabled(Features::registration()),
            ]);
        })->name('home');

        // Pricing page
        Route::get('/pricing', function () {
            return Inertia::render('pricing');
        })->name('pricing');

        // Login é gerenciado pelo Laravel Fortify (não precisa definir aqui)

        // Register tenant + user (será implementado em RegisterController)
        // POST /register será adicionado quando criar o controller

        // Central dashboard (lista de tenants do usuário)
        Route::get('/dashboard', function () {
            $user = auth()->user();

            $tenants = $user?->tenants()
                ->withPivot('role', 'joined_at')
                ->get()
                ->map(function ($tenant) {
                    return [
                        'id' => $tenant->id,
                        'name' => $tenant->name,
                        'slug' => $tenant->slug,
                        'role' => $tenant->pivot->role,
                        'joined_at' => $tenant->pivot->joined_at,
                        'url' => "http://{$tenant->slug}.".config('tenancy.central_domains')[0],
                    ];
                }) ?? collect();

            return Inertia::render('dashboard', [
                'tenants' => $tenants,
            ]);
        })->middleware(['auth', 'verified'])->name('dashboard');

        // Accept invitation (rota pública, pode não estar autenticado)
        Route::get('/accept-invitation', function () {
            $token = request()->query('token');

            return Inertia::render('accept-invitation', [
                'token' => $token,
            ]);
        })->name('accept-invitation');

        Route::post('/accept-invitation', [\App\Http\Controllers\TeamController::class, 'acceptInvitation'])
            ->middleware('auth')
            ->name('accept-invitation.store');
    });
}
