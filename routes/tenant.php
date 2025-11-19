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
    Route::middleware(['auth', 'verified', VerifyTenantAccess::class])->group(function () {
        // Dashboard
        Route::get('/dashboard', function () {
            return Inertia::render('tenant/dashboard', [
                'tenant' => tenant(),
            ]);
        })->name('tenant.dashboard');

        // Projects (CRUD example)
        Route::prefix('projects')->name('projects.')->group(function () {
            Route::get('/', function () {
                $projects = \App\Models\Project::with('user')->paginate(15);

                return Inertia::render('tenant/projects/index', [
                    'projects' => $projects,
                ]);
            })->name('index');

            Route::get('/create', function () {
                return Inertia::render('tenant/projects/create');
            })->name('create');

            // store, show, edit, update, destroy serão implementados em ProjectController
        });

        // Team Management
        Route::prefix('team')->name('team.')->group(function () {
            Route::get('/', function () {
                $tenant = tenant();
                $members = $tenant->users()
                    ->withPivot('role', 'permissions', 'joined_at')
                    ->orderBy('tenant_user.joined_at', 'desc')
                    ->get();

                return Inertia::render('tenant/team/index', [
                    'members' => $members,
                    'tenant' => $tenant,
                ]);
            })->name('index');

            // invite, remove, update-role serão implementados em TeamController
        });

        // Billing (Laravel Cashier)
        Route::prefix('billing')->name('billing.')->group(function () {
            Route::get('/', function () {
                $tenant = tenant();

                return Inertia::render('tenant/billing/index', [
                    'tenant' => $tenant,
                    'subscription' => $tenant->subscription('default'),
                ]);
            })->name('index');

            // subscribe, portal, cancel serão implementados em BillingController
        });

        // Settings do tenant (já existe em routes/settings.php, mas podemos adicionar aqui também)
        require __DIR__.'/settings.php';
    });
});
