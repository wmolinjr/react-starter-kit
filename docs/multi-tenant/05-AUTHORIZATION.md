# 05 - Authorization (Roles e Permissões)

## Índice

- [Sistema de Roles](#sistema-de-roles)
- [Gates e Policies](#gates-e-policies)
- [Middleware de Autorização](#middleware-de-autorização)
- [Integração com Inertia](#integração-com-inertia)
- [React Permission Checking](#react-permission-checking)

---

## Sistema de Roles

### Roles Disponíveis

| Role    | Descrição                          | Permissões                                              |
|---------|------------------------------------|---------------------------------------------------------|
| owner   | Criador do tenant, acesso total    | Tudo, incluindo billing e deletar tenant               |
| admin   | Administrador do tenant            | Gerenciar equipe, settings (exceto billing)            |
| member  | Membro regular                     | Usar aplicação, CRUD em resources próprios             |
| guest   | Acesso limitado (read-only)        | Apenas visualizar                                      |

### Implementação via Pivot Table

Roles são armazenados na coluna `tenant_user.role`:

```sql
SELECT * FROM tenant_user WHERE user_id = 1;

tenant_id | user_id | role   | joined_at
----------|---------|--------|--------------------
1         | 1       | owner  | 2025-01-15 10:00
2         | 1       | admin  | 2025-01-16 11:00
3         | 1       | member | 2025-01-17 12:00
```

---

## Gates e Policies

### Registrar Gates

**`app/Providers/AuthServiceProvider.php`:**

```php
<?php

namespace App\Providers;

use App\Models\Project;
use App\Models\User;
use App\Policies\ProjectPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Project::class => ProjectPolicy::class,
    ];

    public function boot(): void
    {
        // ==========================================
        // SUPER ADMIN BYPASS
        // ==========================================

        Gate::before(function (User $user, string $ability) {
            if ($user->is_super_admin) {
                return true; // Super admin tem acesso total
            }
        });

        // ==========================================
        // TENANT-LEVEL GATES
        // ==========================================

        // Owner bypass (exceto billing, que é específico)
        Gate::before(function (User $user, string $ability) {
            if ($ability !== 'manage-billing' && tenancy()->initialized) {
                if ($user->isOwner()) {
                    return true;
                }
            }
        });

        // Gerenciar equipe (admin e owner)
        Gate::define('manage-team', function (User $user) {
            return $user->hasAnyRole(['owner', 'admin']);
        });

        // Gerenciar billing (apenas owner)
        Gate::define('manage-billing', function (User $user) {
            return $user->isOwner();
        });

        // Gerenciar settings (admin e owner)
        Gate::define('manage-settings', function (User $user) {
            return $user->hasAnyRole(['owner', 'admin']);
        });

        // Criar recursos
        Gate::define('create-resources', function (User $user) {
            return $user->hasAnyRole(['owner', 'admin', 'member']);
        });

        // Ver recursos (todos autenticados)
        Gate::define('view-resources', function (User $user) {
            return tenancy()->initialized && $user->belongsToCurrentTenant();
        });
    }
}
```

### Policy Example: ProjectPolicy

```bash
php artisan make:policy ProjectPolicy --model=Project
```

```php
<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Determinar se user pode ver qualquer project
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['owner', 'admin', 'member', 'guest']);
    }

    /**
     * Determinar se user pode ver um project específico
     */
    public function view(User $user, Project $project): bool
    {
        // Verificar se project pertence ao tenant atual
        return $project->tenant_id === current_tenant_id();
    }

    /**
     * Determinar se user pode criar project
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['owner', 'admin', 'member']);
    }

    /**
     * Determinar se user pode atualizar project
     */
    public function update(User $user, Project $project): bool
    {
        // Owners e admins podem editar qualquer project
        if ($user->hasAnyRole(['owner', 'admin'])) {
            return true;
        }

        // Members podem editar apenas seus próprios projects
        return $user->hasRole('member') && $project->user_id === $user->id;
    }

    /**
     * Determinar se user pode deletar project
     */
    public function delete(User $user, Project $project): bool
    {
        // Apenas owner, admin ou criador do project
        return $user->hasAnyRole(['owner', 'admin'])
            || $project->user_id === $user->id;
    }
}
```

---

## Middleware de Autorização

### 1. Verificar Role

```bash
php artisan make:middleware EnsureUserHasRole
```

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string $role)
    {
        if (!$request->user() || !$request->user()->hasRole($role)) {
            abort(403, 'Você não tem permissão para acessar este recurso.');
        }

        return $next($request);
    }
}
```

**Registrar:**

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'role' => \App\Http\Middleware\EnsureUserHasRole::class,
    ]);
})
```

**Usar:**

```php
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin/dashboard', [AdminController::class, 'index']);
});
```

### 2. Usar Gates nas Rotas

Mais limpo e flexível:

```php
Route::middleware(['auth', 'can:manage-team'])->group(function () {
    Route::get('/team', [TeamController::class, 'index']);
});
```

---

## Integração com Inertia

### Compartilhar Permissões

**`app/Http/Middleware/HandleInertiaRequests.php`:**

```php
<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user(),
                'permissions' => $request->user() ? [
                    // Gates
                    'canManageTeam' => Gate::allows('manage-team'),
                    'canManageBilling' => Gate::allows('manage-billing'),
                    'canManageSettings' => Gate::allows('manage-settings'),
                    'canCreateResources' => Gate::allows('create-resources'),

                    // Role atual
                    'role' => $request->user()->currentTenantRole(),
                    'isOwner' => $request->user()->isOwner(),
                    'isAdmin' => $request->user()->hasRole('admin'),
                    'isAdminOrOwner' => $request->user()->isAdminOrOwner(),
                ] : null,
            ],
            'tenant' => tenancy()->initialized ? [
                'id' => tenant('id'),
                'name' => tenant('name'),
                'slug' => current_tenant()->slug,
                'domain' => request()->getHost(),
                'settings' => current_tenant()->settings,
            ] : null,
        ]);
    }
}
```

---

## React Permission Checking

### Hook Customizado

```typescript
// resources/js/hooks/use-permissions.ts

import { usePage } from '@inertiajs/react';

interface Permissions {
  canManageTeam: boolean;
  canManageBilling: boolean;
  canManageSettings: boolean;
  canCreateResources: boolean;
  role: string | null;
  isOwner: boolean;
  isAdmin: boolean;
  isAdminOrOwner: boolean;
}

export function usePermissions(): Permissions {
  const { auth } = usePage<{ auth: { permissions: Permissions } }>().props;

  return auth?.permissions || {
    canManageTeam: false,
    canManageBilling: false,
    canManageSettings: false,
    canCreateResources: false,
    role: null,
    isOwner: false,
    isAdmin: false,
    isAdminOrOwner: false,
  };
}
```

### Componentes Condicionais

```tsx
// resources/js/components/can.tsx

import { ReactNode } from 'react';
import { usePermissions } from '@/hooks/use-permissions';

interface CanProps {
  permission: keyof ReturnType<typeof usePermissions>;
  children: ReactNode;
  fallback?: ReactNode;
}

export function Can({ permission, children, fallback = null }: CanProps) {
  const permissions = usePermissions();

  if (permissions[permission]) {
    return <>{children}</>;
  }

  return <>{fallback}</>;
}

// Uso:
<Can permission="canManageTeam">
  <Button onClick={() => navigate('/team')}>
    Gerenciar Equipe
  </Button>
</Can>

<Can permission="isOwner" fallback={<div>Apenas owners</div>}>
  <BillingSettings />
</Can>
```

### Exemplo Completo: Dashboard

```tsx
// resources/js/pages/dashboard.tsx

import { Head } from '@inertiajs/react';
import { Can } from '@/components/can';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';

export default function Dashboard() {
  const permissions = usePermissions();

  return (
    <AppLayout>
      <Head title="Dashboard" />

      <div className="py-12">
        <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
          <h1 className="text-3xl font-bold">Dashboard</h1>

          <div className="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
            {/* Todos podem ver */}
            <Card>
              <CardHeader>
                <CardTitle>Meus Projetos</CardTitle>
              </CardHeader>
              <CardContent>
                <p>5 projetos ativos</p>
              </CardContent>
            </Card>

            {/* Apenas owners/admins */}
            <Can permission="canManageTeam">
              <Card>
                <CardHeader>
                  <CardTitle>Equipe</CardTitle>
                </CardHeader>
                <CardContent>
                  <p>10 membros</p>
                  <Button asChild>
                    <Link href="/team">Gerenciar</Link>
                  </Button>
                </CardContent>
              </Card>
            </Can>

            {/* Apenas owners */}
            <Can permission="canManageBilling">
              <Card>
                <CardHeader>
                  <CardTitle>Billing</CardTitle>
                </CardHeader>
                <CardContent>
                  <p>Plano Pro - $29/mês</p>
                  <Button asChild>
                    <Link href="/billing">Gerenciar</Link>
                  </Button>
                </CardContent>
              </Card>
            </Can>
          </div>

          {/* Mensagem customizada por role */}
          {permissions.isOwner && (
            <Alert className="mt-6">
              <AlertTitle>Você é owner desta organização</AlertTitle>
              <AlertDescription>
                Você tem acesso total a todas as configurações.
              </AlertDescription>
            </Alert>
          )}
        </div>
      </div>
    </AppLayout>
  );
}
```

---

## Checklist

- [ ] Gates definidos no `AuthServiceProvider`
- [ ] Policies criadas para models principais
- [ ] Middleware `EnsureUserHasRole` criado (opcional)
- [ ] Permissões compartilhadas via `HandleInertiaRequests`
- [ ] Hook `use-permissions.ts` criado
- [ ] Componente `Can` criado
- [ ] Testes: apenas owners acessam billing
- [ ] Testes: admins e owners acessam team management
- [ ] Testes: members não acessam configurações de admin

---

## Próximo Passo

➡️ **[06-TEAM-MANAGEMENT.md](./06-TEAM-MANAGEMENT.md)** - Gerenciamento de Equipes

---

**Versão:** 1.0
**Última atualização:** 2025-11-19
