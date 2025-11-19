# 04 - Estratégia de Rotas

## Índice

- [Arquitetura de Rotas](#arquitetura-de-rotas)
- [Rotas Centrais](#rotas-centrais)
- [Rotas Tenant](#rotas-tenant)
- [Middleware](#middleware)
- [Exemplos Completos](#exemplos-completos)

---

## Arquitetura de Rotas

### Separação de Domínios

```
┌─────────────────────────────────────────────────────────────┐
│ CENTRAL APP (app.myapp.com)                                 │
│ routes/web.php                                               │
│                                                               │
│ - Landing page                                               │
│ - Pricing                                                    │
│ - Sign up (cria tenant)                                     │
│ - Login redirect                                             │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│ TENANT APP (*.myapp.com ou custom domains)                  │
│ routes/tenant.php                                            │
│                                                               │
│ [InitializeTenancyByDomain middleware]                      │
│   ↓                                                          │
│ Tenant context ativo                                         │
│   ↓                                                          │
│ - Dashboard                                                  │
│ - Projects                                                   │
│ - Team management                                            │
│ - Billing                                                    │
│ - Settings                                                   │
└─────────────────────────────────────────────────────────────┘
```

---

## Rotas Centrais

### `routes/web.php`

```php
<?php

use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central Routes
|--------------------------------------------------------------------------
|
| Rotas do app central (sem tenant context)
|
*/

Route::domain(config('app.central_domain', 'myapp.test'))->group(function () {

    // Landing page pública
    Route::get('/', [LandingController::class, 'index'])->name('landing');
    Route::get('/pricing', [LandingController::class, 'pricing'])->name('pricing');
    Route::get('/features', [LandingController::class, 'features'])->name('features');

    // Auth routes (guest only)
    Route::middleware('guest')->group(function () {
        // Register - cria tenant + user
        Route::get('/register', [RegisterController::class, 'create'])->name('register');
        Route::post('/register', [RegisterController::class, 'store']);

        // Login redirect para tenant
        Route::get('/login', function () {
            return inertia('auth/login-central', [
                'message' => 'Entre com seu email. Redirecionaremos você para sua organização.',
            ]);
        })->name('login');

        Route::post('/login', [RegisterController::class, 'redirectToTenant']);
    });

    // Super admin routes
    Route::middleware(['auth', 'superadmin'])->prefix('admin')->group(function () {
        Route::get('/tenants', [AdminController::class, 'tenants'])->name('admin.tenants');
        Route::get('/impersonate/{tenant}', [ImpersonationController::class, 'start']);
    });
});
```

---

## Rotas Tenant

### `routes/tenant.php`

```php
<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Todas as rotas aqui têm tenant context ativo
|
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {

    // ==========================================
    // PUBLIC TENANT ROUTES (sem auth)
    // ==========================================

    Route::get('/', function () {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        return inertia('tenant/welcome');
    })->name('tenant.home');

    // Auth routes do Fortify (login, logout, etc.)
    // Já configurados no FortifyServiceProvider

    // ==========================================
    // AUTHENTICATED TENANT ROUTES
    // ==========================================

    Route::middleware(['auth', 'verified'])->group(function () {

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // ==========================================
        // TEAM MANAGEMENT (admin/owner only)
        // ==========================================

        Route::middleware('can:manage-team')->prefix('team')->group(function () {
            Route::get('/', [TeamController::class, 'index'])->name('team.index');
            Route::post('/invite', [TeamController::class, 'invite'])->name('team.invite');
            Route::patch('/members/{user}', [TeamController::class, 'updateRole'])->name('team.update-role');
            Route::delete('/members/{user}', [TeamController::class, 'remove'])->name('team.remove');
        });

        // Accept invitation (convite por email)
        Route::get('/invitation/{token}', [TeamController::class, 'acceptInvitation'])->name('invitation.accept');

        // ==========================================
        // BILLING (owner only)
        // ==========================================

        Route::middleware('can:manage-billing')->prefix('billing')->group(function () {
            Route::get('/', [BillingController::class, 'index'])->name('billing.index');
            Route::post('/checkout', [BillingController::class, 'checkout'])->name('billing.checkout');
            Route::get('/success', [BillingController::class, 'success'])->name('billing.success');
            Route::get('/portal', [BillingController::class, 'portal'])->name('billing.portal');
        });

        // ==========================================
        // PROJECTS (exemplo de resource tenant-scoped)
        // ==========================================

        Route::resource('projects', ProjectController::class);

        // ==========================================
        // SETTINGS
        // ==========================================

        Route::prefix('settings')->name('settings.')->group(function () {
            // Profile (todos os users)
            Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
            Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

            // Tenant settings (admin/owner only)
            Route::middleware('can:manage-team')->group(function () {
                Route::get('/general', [SettingsController::class, 'general'])->name('general');
                Route::patch('/general', [SettingsController::class, 'updateGeneral']);

                Route::get('/branding', [SettingsController::class, 'branding'])->name('branding');
                Route::patch('/branding', [SettingsController::class, 'updateBranding');

                Route::get('/domains', [SettingsController::class, 'domains'])->name('domains');
                Route::post('/domains', [SettingsController::class, 'addDomain']);
                Route::delete('/domains/{domain}', [SettingsController::class, 'removeDomain']);
            });
        });

        // ==========================================
        // API TOKENS
        // ==========================================

        Route::prefix('api-tokens')->name('api-tokens.')->group(function () {
            Route::get('/', [ApiTokenController::class, 'index'])->name('index');
            Route::post('/', [ApiTokenController::class, 'store'])->name('store');
            Route::delete('/{token}', [ApiTokenController::class, 'destroy'])->name('destroy');
        });
    });
});
```

---

## Middleware

### 1. InitializeTenancyByDomain

**Fornecido pelo archtechx/tenancy**

Identifica o tenant pelo domínio da request e inicializa o contexto.

```php
// Automático via middleware
// Extrai domain da request
// Busca na tabela domains
// Inicializa tenant context
```

### 2. PreventAccessFromCentralDomains

**Fornecido pelo archtechx/tenancy**

Previne acesso às rotas tenant via domínio central.

```php
// Se request for de myapp.test → 404
// Se request for de tenant.myapp.test → OK
```

### 3. Custom: EnsureSubscriptionIsActive

Criar middleware para verificar assinatura ativa:

```bash
php artisan make:middleware EnsureSubscriptionIsActive
```

```php
<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;

class EnsureSubscriptionIsActive
{
    public function handle(Request $request, Closure $next)
    {
        if (!tenancy()->initialized) {
            return $next($request);
        }

        $tenant = Tenant::find(tenant('id'));

        // Owners sempre têm acesso (para configurar billing)
        if ($request->user()?->isOwner()) {
            return $next($request);
        }

        // Verificar se tem subscription ativa
        if (!$tenant->subscribed('default')) {
            return redirect()->route('billing.index')
                ->with('error', 'Sua assinatura está inativa. Por favor, atualize seu plano.');
        }

        return $next($request);
    }
}
```

**Registrar em `bootstrap/app.php`:**

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'subscribed' => \App\Http\Middleware\EnsureSubscriptionIsActive::class,
    ]);
})
```

**Usar nas rotas:**

```php
Route::middleware(['auth', 'subscribed'])->group(function () {
    Route::resource('projects', ProjectController::class);
});
```

### 4. Custom: VerifyTenantAccess

Verificar se user tem acesso ao tenant:

```bash
php artisan make:middleware VerifyTenantAccess
```

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyTenantAccess
{
    public function handle(Request $request, Closure $next)
    {
        if (!tenancy()->initialized || !$request->user()) {
            return $next($request);
        }

        // Verificar se user pertence ao tenant atual
        if (!$request->user()->belongsToCurrentTenant()) {
            abort(403, 'Você não tem acesso a esta organização.');
        }

        return $next($request);
    }
}
```

---

## Exemplos Completos

### Registro de Novo Tenant

**Controller: `RegisterController.php`**

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    public function create()
    {
        return inertia('auth/register');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
            'organization' => 'required|string|max:255',
        ]);

        return DB::transaction(function () use ($request) {
            // 1. Criar user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($request->password),
            ]);

            // 2. Criar tenant
            $slug = Str::slug($request->organization);

            // Garantir slug único
            $originalSlug = $slug;
            $counter = 1;
            while (Tenant::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter++;
            }

            $tenant = Tenant::create([
                'name' => $request->organization,
                'slug' => $slug,
                'settings' => [
                    'limits' => [
                        'max_users' => 10,  // Starter plan
                        'max_projects' => 50,
                        'storage_mb' => 1000,
                    ],
                ],
            ]);

            // 3. Criar domínio
            $domain = config('app.env') === 'local'
                ? "{$slug}.myapp.test"
                : "{$slug}.myapp.com";

            $tenant->domains()->create([
                'domain' => $domain,
                'is_primary' => true,
            ]);

            // 4. Associar user ao tenant como owner
            $tenant->users()->attach($user->id, [
                'role' => 'owner',
                'joined_at' => now(),
            ]);

            // 5. Login automático
            auth()->login($user);

            // 6. Redirecionar para tenant
            return redirect()->to("http://{$domain}/dashboard");
        });
    }

    public function redirectToTenant(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return back()->withErrors(['email' => 'Usuário não encontrado.']);
        }

        // Obter primeiro tenant do user
        $tenant = $user->tenants()->first();

        if (!$tenant) {
            return back()->withErrors(['email' => 'Nenhuma organização encontrada para este usuário.']);
        }

        $domain = $tenant->primaryDomain()->domain;

        // Redirecionar para login do tenant
        return redirect()->to("http://{$domain}/login");
    }
}
```

### Login no Tenant

**Fortify já configura automaticamente.** Apenas certifique-se de que o middleware está correto:

```php
// config/fortify.php
'domain' => null, // Importante: não definir domain aqui

// FortifyServiceProvider.php
Fortify::loginView(function () {
    return inertia('auth/login');
});

Fortify::authenticateUsing(function (Request $request) {
    $user = User::where('email', $request->email)->first();

    if ($user && Hash::check($request->password, $user->password)) {
        // Verificar se user tem acesso ao tenant atual
        if (tenancy()->initialized && !$user->belongsToCurrentTenant()) {
            throw ValidationException::withMessages([
                'email' => ['Você não tem acesso a esta organização.'],
            ]);
        }

        return $user;
    }
});
```

---

## Checklist

- [ ] `routes/web.php` configurado para central app
- [ ] `routes/tenant.php` configurado para tenant app
- [ ] Middleware `InitializeTenancyByDomain` aplicado
- [ ] Middleware `EnsureSubscriptionIsActive` criado
- [ ] Middleware `VerifyTenantAccess` criado
- [ ] RegisterController criado para sign up
- [ ] Fortify configurado para aceitar apenas users do tenant
- [ ] Teste: acessar central app (`myapp.test`) funciona
- [ ] Teste: acessar tenant app (`tenant.myapp.test`) funciona
- [ ] Teste: criar novo tenant via register funciona
- [ ] Teste: login no tenant correto funciona

---

## Próximo Passo

➡️ **[05-AUTHORIZATION.md](./05-AUTHORIZATION.md)** - Roles e Permissões

---

**Versão:** 1.0
**Última atualização:** 2025-11-19
