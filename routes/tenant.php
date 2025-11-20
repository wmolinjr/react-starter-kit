<?php

declare(strict_types=1);

use App\Http\Middleware\InitializeTenancyByDomainExceptTests;
use App\Http\Middleware\PreventAccessFromCentralDomainsExceptTests;
use App\Http\Middleware\VerifyTenantAccess;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Stancl\Tenancy\Features\UserImpersonation;

/*
|--------------------------------------------------------------------------
| Tenant Routes (*.setor3.app)
|--------------------------------------------------------------------------
|
| Rotas tenant-scoped. Todas as rotas aqui têm acesso ao tenant context.
| Middleware aplicado:
| - InitializeTenancyByDomainExceptTests: identifica tenant pelo domínio (pula se já inicializado)
| - PreventAccessFromCentralDomainsExceptTests: previne acesso via domínios centrais (pula em tests)
| - VerifyTenantAccess: verifica se usuário tem acesso ao tenant
|
| Em testes: o tenant é inicializado manualmente no TenantTestCase::setUp()
| Em produção: usa InitializeTenancyByDomain normalmente
|
*/

Route::middleware([
    'web',
    InitializeTenancyByDomainExceptTests::class,
    PreventAccessFromCentralDomainsExceptTests::class,
])->group(function () {
    // Impersonation token consumption (Stancl/Tenancy official implementation)
    // Uses the official UserImpersonation::makeResponse() from the package
    // This route is NOT authenticated because the token provides the authentication
    Route::get('/impersonate/{token}', function ($token) {
        return UserImpersonation::makeResponse($token);
    })->name('impersonate.consume');

    // Redirect root para dashboard
    Route::get('/', function () {
        return redirect()->route('tenant.dashboard');
    });

    // Rotas autenticadas do tenant
    Route::middleware(['auth', 'verified', VerifyTenantAccess::class, 'prevent.impersonation'])->group(function () {
        // Dashboard
        Route::get('/dashboard', function () {
            return Inertia::render('tenant/dashboard', [
                'tenant' => tenant(),
            ]);
        })->name('tenant.dashboard');

        // Projects (CRUD + File Upload)
        Route::prefix('projects')->name('projects.')->group(function () {
            // Read operations (no rate limiting)
            Route::get('/', [\App\Http\Controllers\ProjectController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\ProjectController::class, 'create'])->name('create');
            Route::get('/{project}', [\App\Http\Controllers\ProjectController::class, 'show'])->name('show');
            Route::get('/{project}/edit', [\App\Http\Controllers\ProjectController::class, 'edit'])->name('edit');

            // Write operations (rate limited)
            Route::middleware('throttle:tenant-actions')->group(function () {
                Route::post('/', [\App\Http\Controllers\ProjectController::class, 'store'])->name('store');
                Route::patch('/{project}', [\App\Http\Controllers\ProjectController::class, 'update'])->name('update');
                Route::delete('/{project}', [\App\Http\Controllers\ProjectController::class, 'destroy'])->name('destroy');
            });

            // Media Management (stricter rate limiting for uploads)
            // Note: Media isolation is handled by BelongsToTenant global scope
            // Route model binding automatically filters by tenant_id
            Route::middleware('throttle:uploads')->post('/{project}/media', [\App\Http\Controllers\ProjectController::class, 'uploadFile'])->name('media.upload');
            Route::get('/{project}/media/{media}', [\App\Http\Controllers\ProjectController::class, 'downloadFile'])->name('media.download');
            Route::middleware('throttle:tenant-actions')->delete('/{project}/media/{media}', [\App\Http\Controllers\ProjectController::class, 'deleteFile'])->name('media.delete');
        });

        // Team Management
        Route::prefix('team')->name('team.')->group(function () {
            // Read operations
            Route::get('/', [\App\Http\Controllers\TeamController::class, 'index'])->name('index');

            // Write operations (rate limited)
            Route::middleware('throttle:tenant-actions')->group(function () {
                Route::post('/invite', [\App\Http\Controllers\TeamController::class, 'invite'])->name('invite');
                Route::patch('/{user}/role', [\App\Http\Controllers\TeamController::class, 'updateRole'])->name('update-role');
                Route::delete('/{user}', [\App\Http\Controllers\TeamController::class, 'remove'])->name('remove');
            });
        });

        // Billing (Laravel Cashier)
        Route::prefix('billing')->name('billing.')->group(function () {
            Route::get('/', [\App\Http\Controllers\BillingController::class, 'index'])->name('index');
            Route::post('/checkout', [\App\Http\Controllers\BillingController::class, 'checkout'])->name('checkout');
            Route::get('/success', [\App\Http\Controllers\BillingController::class, 'success'])->name('success');
            Route::get('/portal', [\App\Http\Controllers\BillingController::class, 'portal'])->name('portal');
            Route::get('/invoice/{invoiceId}', [\App\Http\Controllers\BillingController::class, 'invoice'])->name('invoice');
        });

        // Settings do tenant (já existe em routes/settings.php, mas podemos adicionar aqui também)
        require __DIR__.'/settings.php';
    });

    /*
    |--------------------------------------------------------------------------
    | API Routes (Tenant-Scoped with Sanctum)
    |--------------------------------------------------------------------------
    |
    | Rotas API com autenticação Sanctum e tenant context.
    | Todas as rotas aqui são isoladas por tenant e rate-limited.
    |
    */
    Route::middleware(['auth:sanctum', 'throttle:api'])
        ->prefix('api')
        ->name('api.')
        ->group(function () {
            // API Tokens Management
            Route::get('/tokens', [\App\Http\Controllers\ApiTokenController::class, 'index'])->name('tokens.index');
            Route::post('/tokens', [\App\Http\Controllers\ApiTokenController::class, 'store'])->name('tokens.store');
            Route::patch('/tokens/{tokenId}', [\App\Http\Controllers\ApiTokenController::class, 'update'])->name('tokens.update');
            Route::delete('/tokens/{tokenId}', [\App\Http\Controllers\ApiTokenController::class, 'destroy'])->name('tokens.destroy');

            // Projects API (tenant-scoped)
            Route::apiResource('projects', \App\Http\Controllers\Api\ProjectController::class);
        });
});

// Tenant Settings (Branding, Domains, Features, Notifications)
Route::middleware([
    'web',
    InitializeTenancyByDomainExceptTests::class,
    PreventAccessFromCentralDomainsExceptTests::class,
    'auth',
    'verified',
    VerifyTenantAccess::class,
    'prevent.impersonation',
])->prefix('tenant-settings')->name('tenant.settings.')->group(function () {
    // Read operations
    Route::get('/', [\App\Http\Controllers\TenantSettingsController::class, 'index'])->name('index');
    Route::get('/branding', [\App\Http\Controllers\TenantSettingsController::class, 'branding'])->name('branding');
    Route::get('/domains', [\App\Http\Controllers\TenantSettingsController::class, 'domains'])->name('domains');

    // Write operations (rate limited)
    Route::middleware('throttle:tenant-actions')->group(function () {
        Route::post('/branding', [\App\Http\Controllers\TenantSettingsController::class, 'updateBranding'])->name('branding.update');
        Route::post('/domains', [\App\Http\Controllers\TenantSettingsController::class, 'addDomain'])->name('domains.add');
        Route::delete('/domains/{domainId}', [\App\Http\Controllers\TenantSettingsController::class, 'removeDomain'])->name('domains.remove');
        Route::post('/features', [\App\Http\Controllers\TenantSettingsController::class, 'updateFeatures'])->name('features.update');
        Route::post('/notifications', [\App\Http\Controllers\TenantSettingsController::class, 'updateNotifications'])->name('notifications.update');
    });
});
