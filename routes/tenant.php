<?php

declare(strict_types=1);

use App\Http\Middleware\VerifyTenantAccess;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes (*.myapp.com)
|--------------------------------------------------------------------------
|
| Rotas tenant-scoped. Todas as rotas aqui têm acesso ao tenant context.
| Middleware aplicado:
| - InitializeTenancyByDomain: inicializa o tenant pelo subdomínio
| - PreventAccessFromCentralDomains: previne acesso de domínios centrais
| - VerifyTenantAccess: verifica se usuário tem acesso ao tenant
|
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
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
            Route::get('/', [\App\Http\Controllers\ProjectController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\ProjectController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\ProjectController::class, 'store'])->name('store');
            Route::get('/{project}', [\App\Http\Controllers\ProjectController::class, 'show'])->name('show');
            Route::get('/{project}/edit', [\App\Http\Controllers\ProjectController::class, 'edit'])->name('edit');
            Route::patch('/{project}', [\App\Http\Controllers\ProjectController::class, 'update'])->name('update');
            Route::delete('/{project}', [\App\Http\Controllers\ProjectController::class, 'destroy'])->name('destroy');

            // Media Management
            Route::post('/{project}/media', [\App\Http\Controllers\ProjectController::class, 'uploadFile'])->name('media.upload');
            Route::get('/{project}/media/{media}', [\App\Http\Controllers\ProjectController::class, 'downloadFile'])->name('media.download');
            Route::delete('/{project}/media/{media}', [\App\Http\Controllers\ProjectController::class, 'deleteFile'])->name('media.delete');
        });

        // Team Management
        Route::prefix('team')->name('team.')->group(function () {
            Route::get('/', [\App\Http\Controllers\TeamController::class, 'index'])->name('index');
            Route::post('/invite', [\App\Http\Controllers\TeamController::class, 'invite'])->name('invite');
            Route::patch('/{user}/role', [\App\Http\Controllers\TeamController::class, 'updateRole'])->name('update-role');
            Route::delete('/{user}', [\App\Http\Controllers\TeamController::class, 'remove'])->name('remove');
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
    | Todas as rotas aqui são isoladas por tenant.
    |
    */
    Route::middleware(['auth:sanctum'])
        ->prefix('api')
        ->name('api.')
        ->group(function () {
            // API Tokens Management
            Route::get('/tokens', [\App\Http\Controllers\ApiTokenController::class, 'index'])->name('tokens.index');
            Route::post('/tokens', [\App\Http\Controllers\ApiTokenController::class, 'store'])->name('tokens.store');
            Route::delete('/tokens/{tokenId}', [\App\Http\Controllers\ApiTokenController::class, 'destroy'])->name('tokens.destroy');
            Route::delete('/tokens', [\App\Http\Controllers\ApiTokenController::class, 'destroyAll'])->name('tokens.destroy-all');

            // Example: API endpoint for projects (tenant-scoped)
            Route::get('/projects', function () {
                return response()->json([
                    'tenant_id' => current_tenant_id(),
                    'projects' => \App\Models\Project::all(),
                ]);
            })->name('projects.index');
        });
});

        // Tenant Settings (Branding, Domains, Features, Notifications)
        Route::prefix('tenant-settings')->name('tenant.settings.')->group(function () {
            Route::get('/', [\App\Http\Controllers\TenantSettingsController::class, 'index'])->name('index');

            // Branding
            Route::get('/branding', [\App\Http\Controllers\TenantSettingsController::class, 'branding'])->name('branding');
            Route::post('/branding', [\App\Http\Controllers\TenantSettingsController::class, 'updateBranding'])->name('branding.update');

            // Domains
            Route::get('/domains', [\App\Http\Controllers\TenantSettingsController::class, 'domains'])->name('domains');
            Route::post('/domains', [\App\Http\Controllers\TenantSettingsController::class, 'addDomain'])->name('domains.add');
            Route::delete('/domains/{domainId}', [\App\Http\Controllers\TenantSettingsController::class, 'removeDomain'])->name('domains.remove');

            // Features
            Route::post('/features', [\App\Http\Controllers\TenantSettingsController::class, 'updateFeatures'])->name('features.update');

            // Notifications
            Route::post('/notifications', [\App\Http\Controllers\TenantSettingsController::class, 'updateNotifications'])->name('notifications.update');
        });
