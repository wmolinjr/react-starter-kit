# Central Frontend Reorganization Plan

**Status**: IMPLEMENTED
**Created**: 2025-12-06
**Completed**: 2025-12-06
**Priority**: HIGH

## Executive Summary

O frontend central possui vários problemas legados que precisam ser corrigidos:
1. Menu do usuário não aparece após login
2. Múltiplas rotas de login causam confusão
3. Tabs redundantes no sidebar (Administration/Account)
4. Fortify configurado apenas para contexto tenant

---

## Current State Analysis

### 1. Authentication Architecture (Dual Guard System)

O projeto usa **Tenant-Only Architecture (Option C)** com dois guards:

| Guard | Model | Database | Purpose |
|-------|-------|----------|---------|
| `central` | `Central\User` | Central DB | Central admins (super-admin, central-admin, support-admin) |
| `tenant` | `Tenant\User` | Tenant DB | Tenant users (owner, admin, member) |

**Arquivos Relevantes:**
- `config/auth.php` - Define ambos guards
- `app/Models/Central/User.php` - Admin model com Spatie roles
- `app/Models/Tenant/User.php` - Tenant user model

### 2. Login Routes (Problema: Múltiplas Rotas)

| Route | Controller | Guard | Domínio |
|-------|------------|-------|---------|
| `/admin/login` | `AdminLoginController` | `central` | localhost |
| `/login` | Fortify | `tenant` | *.localhost |

**Problema Identificado:**
- Fortify routes (`/login`) funcionam no domínio central mas usam guard `tenant`
- Quando admin tenta `/login` em `localhost`, autentica contra tabela vazia de tenant users
- Causa confusão sobre qual rota usar

**Arquivos:**
- `routes/central.php:107-111` - Custom admin auth routes
- `app/Http/Controllers/Central/Auth/AdminLoginController.php`
- `config/fortify.php` - Configurado apenas para tenant guard

### 3. Frontend Layouts

**Três Layouts Centrais:**

| Layout | Usado Por | Propósito |
|--------|---------|---------|
| `CentralAdminLayout` | `/admin/*` | Super admin dashboard |
| `CentralPanelLayout` | `/painel/*` | User panel (gerenciar tenants) |
| `SharedLayout` | Settings | Seletor de layout por contexto |

**Arquivos:**
- `resources/js/layouts/central-admin-layout.tsx`
- `resources/js/layouts/central-panel-layout.tsx`
- `resources/js/layouts/shared-layout.tsx`

### 4. User Menu Visibility (CRÍTICO)

**Problema no HandleInertiaRequests:**

```php
// app/Http/Middleware/Shared/HandleInertiaRequests.php
public function share(Request $request): array
{
    // Usa $request->user() que retorna o guard DEFAULT
    // Default guard é 'tenant' (config/auth.php:17)
    // Para central admin, $request->user() retorna NULL
}
```

**Resultado:**
- `auth.user` é `null` no frontend para central admins
- `NavUser` component retorna `null` quando `!auth.user`
- Menu do usuário não aparece

### 5. Tabs Redundantes no Sidebar

**Arquivo:** `resources/js/components/sidebar/central-admin-sidebar.tsx:59-94`

O componente tem tabs "Administration" e "Account" que:
- Confundem sobre onde o usuário está navegando
- Parecem duas opções de menu separadas
- Account tab mostra rotas `/painel/*` e settings

### 6. Fortify Configuration

**Atual (`config/fortify.php`):**
```php
'guard' => 'tenant',
'passwords' => 'tenant_users',
'middleware' => ['web', 'universal', InitializeTenancyByDomain::class],
```

**Problemas:**
- Fortify só funciona para guard `tenant`
- Central admins não podem usar: password reset, 2FA
- `universal` middleware expõe Fortify routes no domínio central

---

## Issues Summary

### Critical Issues

| # | Issue | Impact | Files |
|---|-------|--------|-------|
| 1 | User menu não aparece para central admins | UX quebrada | `HandleInertiaRequests.php`, `nav-user.tsx` |
| 2 | Logout route usa guard errado | Logout falha | `user-menu-content.tsx` |
| 3 | `/login` no domínio central usa tenant guard | Confusão de auth | `routes/central.php` |

### Medium Issues

| # | Issue | Impact | Files |
|---|-------|--------|-------|
| 4 | Tabs redundantes no sidebar | UX confusa | `central-admin-sidebar.tsx` |
| 5 | `SharedLayout` depende de `auth.user` | Layout selection falha | `shared-layout.tsx` |

### Low Issues

| # | Issue | Impact | Files |
|---|-------|--------|-------|
| 6 | Sem password reset para central admins | Feature missing | Fortify config |
| 7 | Sem 2FA routes para central admins | Feature missing | Fortify config |

---

## Proposed Changes

### Phase 1: Fix User Authentication Detection (CRITICAL)

**1.1 Update HandleInertiaRequests**

```php
// app/Http/Middleware/Shared/HandleInertiaRequests.php

public function share(Request $request): array
{
    // Check central guard FIRST, then default guard
    $user = auth('central')->user() ?? $request->user();

    return [
        // ...existing code...
        'auth' => $this->getAuthData($user),
    ];
}

protected function getAuthData($user): array
{
    if (! $user) {
        return [
            'user' => null,
            'tenant' => null,
            'permissions' => [],
            'role' => null,
            'guard' => null,  // NEW
        ];
    }

    if ($user instanceof \App\Models\Central\User) {
        return [
            'user' => $user->toArray(),
            'tenant' => null,
            'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
            'role' => $user->getRoleName(),
            'isSuperAdmin' => $user->isSuperAdmin(),
            'guard' => 'central',  // NEW
        ];
    }

    return [
        // ...existing tenant user data...
        'guard' => 'tenant',  // NEW
    ];
}
```

**1.2 Update TypeScript Types**

```typescript
// resources/js/types/index.d.ts
export interface Auth {
    user: User | null;
    permissions: string[];
    role: string | null;
    guard: 'central' | 'tenant' | null;  // NEW
    isSuperAdmin?: boolean;
}
```

### Phase 2: Fix User Menu Logout (CRITICAL)

**2.1 Create Context-Aware Logout Hook**

```typescript
// resources/js/hooks/use-logout.ts (NEW FILE)
import { usePage } from '@inertiajs/react';
import { logout as fortifyLogout } from '@/routes';
import { logout as adminLogout } from '@/routes/central/admin/auth';

export function useLogout() {
    const { auth } = usePage<PageProps>().props;

    return auth.guard === 'central' ? adminLogout : fortifyLogout;
}
```

**2.2 Update UserMenuContent**

```tsx
// resources/js/components/user-menu-content.tsx
import { useLogout } from '@/hooks/use-logout';

export function UserMenuContent({ user }: UserMenuContentProps) {
    const logout = useLogout();

    // ...rest uses logout() correctly
}
```

### Phase 3: Redirect Fortify Routes on Central Domain (MEDIUM)

**3.1 Add Redirect in Central Routes**

```php
// routes/central.php

// Redirect /login to /admin/login on central domain
Route::get('/login', function () {
    if (auth('central')->check()) {
        return redirect()->route('central.admin.dashboard');
    }
    return redirect()->route('central.admin.auth.login');
})->name('login.redirect');

// Redirect /register to admin login (no public registration for central)
Route::get('/register', function () {
    return redirect()->route('central.admin.auth.login');
});
```

### Phase 4: Simplify Central Admin Sidebar (MEDIUM)

**4.1 Remove Tabs, Use Single Navigation**

```tsx
// resources/js/components/sidebar/central-admin-sidebar.tsx

export function CentralAdminSidebar() {
    const adminNavItems = useCentralAdminNavItems();
    const footerNavItems = useFooterNavItems();

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <AppLogo />
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain
                    items={adminNavItems}
                    label={t('sidebar.administration')}
                />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
```

**4.2 Add Settings to Admin Nav Items**

```tsx
// resources/js/components/sidebar/central-nav-items.tsx

export function useCentralAdminNavItems(): NavItem[] {
    return [
        // ...existing admin items (dashboard, tenants, plans, users)...
        {
            title: t('sidebar.settings'),
            href: '/admin/settings/profile',  // or shared settings route
            icon: Settings,
        },
    ];
}
```

### Phase 5: Clean Up Legacy Routes (LOW)

**5.1 Audit and Remove Unused Routes**

Verificar e remover:
- Rotas duplicadas de autenticação
- Rotas Fortify expostas desnecessariamente no domínio central
- Redirects obsoletos

**5.2 Update Wayfinder Routes**

```bash
sail artisan wayfinder:generate --with-form
```

---

## Implementation Steps

### Step 1: Fix User Menu (Immediate) - COMPLETED

1. [x] Update `HandleInertiaRequests.php` - check central guard first
2. [x] Add `guard` field to auth data
3. [x] Update TypeScript types in `resources/js/types/`
4. [x] Test: Login as admin, verify user menu appears

### Step 2: Fix Logout Route (Immediate) - COMPLETED

1. [x] Create `resources/js/hooks/use-logout.ts`
2. [x] Update `UserMenuContent` to use new hook
3. [x] Test: Click logout, verify redirect to correct login page

### Step 3: Fix Login Redirect (High Priority) - COMPLETED

1. [x] Add `/login` redirect to `/admin/login` in `routes/central.php`
2. [x] Add `/register` redirect
3. [x] Test: Navigate to `localhost/login`, verify redirect

### Step 4: Simplify Navigation (Medium Priority) - COMPLETED

1. [x] Remove tabs from `CentralAdminSidebar`
2. [x] Consolidate nav items into single menu
3. [x] Add settings link to admin nav
4. [x] Test: Navigate sidebar, verify single cohesive menu

### Step 5: Update Types and Tests (Low Priority) - COMPLETED

1. [x] Update `resources/js/types/permissions.d.ts`
2. [x] Update `usePermissions` hook if needed
3. [x] Add/update tests for central admin auth
4. [x] Run full test suite (430 tests passed)

### Step 6: Documentation - COMPLETED

1. [x] Update `CLAUDE.md` with central admin login flow
2. [x] Mark this plan as IMPLEMENTED
3. [ ] Move to `docs/archive/` (optional)

---

## Migration Checklist

### Before Implementation

- [ ] Create feature branch: `git checkout -b feature/central-frontend-reorganization`
- [ ] Run existing tests: `sail artisan test --parallel --processes=20`
- [ ] Document current behavior with screenshots

### During Implementation

- [ ] Implement phases in order (1 → 2 → 3 → 4 → 5)
- [ ] Test each phase before proceeding
- [ ] Keep commits atomic and reversible
- [ ] Run tests after each phase

### After Implementation

- [ ] Run full test suite
- [ ] Test manually:
  - [ ] Central admin login at `/admin/login`
  - [ ] User menu appears after login
  - [ ] Logout works correctly
  - [ ] Sidebar navigation is clean
  - [ ] `/login` redirects to `/admin/login`
- [ ] Test tenant auth still works:
  - [ ] Tenant login at `tenant1.localhost/login`
  - [ ] Tenant user menu works
  - [ ] Tenant logout works
- [ ] Test impersonation flow
- [ ] Update translations if needed

---

## Files to Modify

### Critical (Phase 1-2)

| File | Changes |
|------|---------|
| `app/Http/Middleware/Shared/HandleInertiaRequests.php` | Check central guard first, add guard field |
| `resources/js/types/index.d.ts` | Add guard to Auth interface |
| `resources/js/hooks/use-logout.ts` | NEW: Context-aware logout hook |
| `resources/js/components/user-menu-content.tsx` | Use new logout hook |

### Medium (Phase 3-4)

| File | Changes |
|------|---------|
| `routes/central.php` | Add login/register redirects |
| `resources/js/components/sidebar/central-admin-sidebar.tsx` | Remove tabs, simplify navigation |
| `resources/js/components/sidebar/central-nav-items.tsx` | Add settings to admin items |

### Low (Phase 5)

| File | Changes |
|------|---------|
| `resources/js/types/permissions.d.ts` | Update Auth type |
| `resources/js/hooks/use-permissions.ts` | Handle central context |

---

## Testing Plan

### Unit Tests

```php
// tests/Feature/CentralAdminAuthTest.php
public function test_central_admin_can_login()
public function test_central_admin_sees_user_menu()
public function test_central_admin_can_logout()
public function test_login_redirects_to_admin_login_on_central_domain()
```

### E2E Tests (Playwright)

```typescript
// tests/Browser/central-admin-auth.spec.ts
test('central admin login flow')
test('user menu appears after login')
test('logout redirects to admin login')
test('/login redirects to /admin/login')
```

---

## Rollback Plan

Se problemas surgirem:

1. **Revert HandleInertiaRequests changes**: Restaurar lógica original de `$request->user()`
2. **Remove logout hook**: Reverter para import direto de Fortify logout
3. **Remove route redirects**: Comentar redirects em `routes/central.php`
4. **Restore sidebar tabs**: Reverter `central-admin-sidebar.tsx`

Cada fase pode ser revertida independentemente se necessário.

---

## Future Improvements (Out of Scope)

1. **Central Admin Fortify Features**
   - Password reset flow para central admins
   - 2FA setup para central admins
   - Requires separate Fortify configuration

2. **Unified Auth Experience**
   - Single login page that detects context
   - Auto-redirect based on email domain
   - More complex but cleaner UX

3. **Central Panel Cleanup**
   - Evaluate if CentralPanelLayout is still needed
   - Consider merging with CentralAdminLayout
   - Simplify user journey
