# Plano de Reorganização de Rotas

## Objetivo

Padronizar todos os nomes de rotas com prefixos claros:
- `central.*` - Rotas do domínio central (localhost)
- `tenant.*` - Rotas do domínio tenant (*.localhost)
- `universal.*` - Rotas que funcionam em ambos os contextos

**Importante:** Apenas os NOMES das rotas mudam. Os paths de URL permanecem iguais.

---

## Estrutura Atual vs Nova

### 1. Rotas Central (`central.*`)

#### 1.1 Rotas Públicas/Usuário (web.php)

| Atual | Novo | Arquivo |
|-------|------|---------|
| `home` | `central.home` | web.php |
| `dashboard` (central) | `central.dashboard` | web.php |

#### 1.2 Rotas Admin (admin.php) → `*`

| Atual | Novo | Arquivo |
|-------|------|---------|
| `admin.dashboard` | `dashboard` | admin.php |
| `admin.tenants.*` | `tenants.*` | admin.php |
| `admin.users.*` | `users.*` | admin.php |
| `admin.addons.*` | `addons.*` | admin.php |
| `admin.catalog.*` | `catalog.*` | admin.php |
| `admin.features.*` | `features.*` | admin.php |
| `admin.limits.*` | `limits.*` | admin.php |
| `admin.plans.*` | `plans.*` | admin.php |
| `admin.roles.*` | `roles.*` | admin.php |
| `admin.impersonate.*` | `impersonate.*` | admin.php |

### 2. Rotas Tenant (`tenant.*`)

| Atual | Novo | Arquivo |
|-------|------|---------|
| `tenant.dashboard` | `tenant.dashboard` | tenant.php (já correto) |
| `projects.*` | `tenant.projects.*` | tenant.php |
| `team.*` | `tenant.team.*` | tenant.php |
| `billing.*` | `tenant.billing.*` | tenant.php |
| `audit.*` | `tenant.audit.*` | tenant.php |
| `tenant.addons.*` | `tenant.addons.*` | tenant.php (já correto) |
| `tenant.settings.*` | `tenant.settings.*` | tenant.php (já correto) |
| `api.*` | `tenant.api.*` | tenant.php |
| `invitation.*` | `tenant.invitation.*` | tenant.php |
| `impersonate.consume` | `tenant.impersonate.consume` | tenant.php |
| `impersonate.stop` | `tenant.impersonate.stop` | tenant.php |

### 3. Rotas Universais (`universal.*`)

#### 3.1 Rotas Fortify (MANTIDAS SEM PREFIXO)

Rotas do Laravel Fortify permanecem com nomes originais por simplicidade:

| Rota | Mantém | Motivo |
|------|--------|--------|
| `login`, `login.store` | Sim | Fortify padrão |
| `logout` | Sim | Fortify padrão |
| `register`, `register.store` | Sim | Fortify padrão |
| `password.*` | Sim | Fortify padrão |
| `verification.*` | Sim | Fortify padrão |
| `two-factor.login`, `two-factor.login.store` | Sim | Fortify padrão |

#### 3.2 Rotas Settings (RECEBEM PREFIXO)

| Atual | Novo | Arquivo |
|-------|------|---------|
| `profile.edit` | `universal.profile.edit` | settings.php |
| `profile.update` | `universal.profile.update` | settings.php |
| `profile.destroy` | `universal.profile.destroy` | settings.php |
| `user-password.edit` | `universal.password.edit` | settings.php |
| `user-password.update` | `universal.password.update` | settings.php |
| `appearance.edit` | `universal.appearance.edit` | settings.php |
| `two-factor.show` | `universal.two-factor.show` | settings.php |

### 4. Rotas que Permanecem (Pacotes Externos)

| Rota | Motivo |
|------|--------|
| `telescope.*` | Laravel Telescope (dev tool) |
| `cashier.*` | Laravel Cashier (Stripe) |
| `sanctum.*` | Laravel Sanctum (API auth) |
| `storage.local` | File storage |
| `stancl.tenancy.*` | Stancl Tenancy assets |

---

## Arquivos a Modificar

### Backend (PHP)

#### Reorganização de Controllers

**Estrutura Atual:**
```
app/Http/Controllers/
├── Admin/                    # ❌ Mover para Central/Admin/
│   ├── AddonCatalogController.php
│   ├── AddonManagementController.php
│   ├── FeatureDefinitionController.php
│   ├── LimitDefinitionController.php
│   ├── PlanCatalogController.php
│   ├── RoleManagementController.php
│   ├── TenantManagementController.php
│   └── UserManagementController.php
├── Central/
│   ├── AdminController.php   # ✅ Manter (ou mover para Admin/)
│   └── ImpersonationController.php  # ✅ Mover para Admin/
├── Tenant/                   # ✅ OK
├── Universal/                # ✅ OK
└── Billing/                  # ✅ OK (webhooks)
```

**Estrutura Nova:**
```
app/Http/Controllers/
├── Central/
│   ├── DashboardController.php      # Renomear AdminController
│   └── Admin/                        # Mover de Admin/
│       ├── AddonCatalogController.php
│       ├── AddonManagementController.php
│       ├── DashboardController.php   # Admin dashboard
│       ├── FeatureDefinitionController.php
│       ├── ImpersonationController.php
│       ├── LimitDefinitionController.php
│       ├── PlanCatalogController.php
│       ├── RoleManagementController.php
│       ├── TenantManagementController.php
│       └── UserManagementController.php
├── Tenant/                   # ✅ OK
├── Universal/                # ✅ OK
└── Billing/                  # ✅ OK (webhooks)
```

#### Arquivos de Rotas

1. **`routes/web.php`**
   - Adicionar prefixo `central.` nas rotas

2. **`routes/admin.php`**
   - Mudar `->name('admin.')` para `->name('')`
   - Atualizar imports dos controllers para `Central\Admin\*`

3. **`routes/tenant.php`**
   - Adicionar prefixo `tenant.` nas rotas: projects, team, billing, audit, api, invitation, impersonate

4. **`routes/settings.php`**
   - Adicionar prefixo `universal.` nas rotas

5. **Controllers que usam `route()` helper**
   - Atualizar referências de nomes de rotas

6. **Middlewares que usam nomes de rotas**
   - CheckFeature.php: `billing.index` → `tenant.billing.index`

### Frontend (TypeScript)

#### Páginas React (JÁ ORGANIZADAS ✅)

```
resources/js/pages/
├── central/
│   ├── welcome.tsx
│   ├── dashboard.tsx
│   └── admin/           # ✅ Já está correto!
│       ├── addons/
│       ├── catalog/
│       ├── features/
│       ├── limits/
│       ├── plans/
│       ├── roles/
│       ├── tenants/
│       └── users/
├── tenant/              # ✅ OK
├── universal/           # ✅ OK
```

#### Arquivos a Atualizar

1. **`resources/js/routes/*`**
   - Regenerar com `sail artisan wayfinder:generate --with-form`

2. **Componentes React**
   - Atualizar imports de rotas (nomes mudaram)
   - Atualizar referências de rotas em links e navegação

3. **Hooks**
   - `use-permissions.ts` - se usar nomes de rotas
   - `use-plan.ts` - se usar nomes de rotas

---

## Ordem de Execução

### Fase 0: Reorganização de Controllers (PHP)

0.1. [ ] Criar diretório `app/Http/Controllers/Central/Admin/`
0.2. [ ] Mover controllers de `Admin/` para `Central/Admin/`
0.3. [ ] Mover `Central/ImpersonationController.php` para `Central/Admin/`
0.4. [ ] Renomear `Central/AdminController.php` para `Central/DashboardController.php`
0.5. [ ] Atualizar namespaces em todos os controllers movidos
0.6. [ ] Remover diretório `Admin/` vazio

### Fase 1: Backend Routes (PHP)

1. [ ] `routes/web.php` - Adicionar prefixo `central.`
2. [ ] `routes/admin.php` - Mudar `admin.` para `` + atualizar imports
3. [x] `routes/tenant.php` - Adicionar prefixo `tenant.admin.` ✅ CONCLUÍDO (com pontos separadores: tenant., admin., projects., etc.)
   - [x] Testes atualizados com nomes completos: `tenant.admin.projects.*`, `tenant.admin.team.*`, etc.
4. [ ] `routes/settings.php` - Adicionar prefixo `universal.`
5. [x] Fortify routes - MANTER SEM PREFIXO (decisão tomada)

### Fase 2: Backend References (PHP)

6. [x] Atualizar `route()` calls em Controllers (tenant) ✅ CONCLUÍDO
7. [x] Atualizar `route()` calls em Middlewares (CheckFeature) ✅ CONCLUÍDO
8. [x] Atualizar `redirect()->route()` calls (tenant) ✅ CONCLUÍDO
9. [ ] Atualizar `to_route()` calls

### Fase 3: Frontend (TypeScript)

10. [x] Regenerar rotas Wayfinder ✅ CONCLUÍDO
11. [x] Atualizar imports em componentes (tenant) ✅ CONCLUÍDO
12. [x] Atualizar referências em links (tenant) ✅ CONCLUÍDO
13. [x] Atualizar breadcrumbs (tenant) ✅ CONCLUÍDO

### Fase 4: Validação

14. [x] Rodar `sail artisan route:list` para verificar ✅ CONCLUÍDO
15. [x] Rodar `sail npm run types` para verificar TS ✅ CONCLUÍDO
16. [x] Rodar `sail npm run lint` para verificar lint ✅ CONCLUÍDO
17. [ ] Testar navegação manualmente

---

## Considerações Especiais

### Fortify Routes

**Decisão:** Manter rotas Fortify com nomes originais (sem prefixo `universal.`).

Motivo: Laravel Fortify não suporta prefixo de nome de rota nativamente, e criar wrappers adiciona complexidade desnecessária. As rotas de autenticação (`login`, `register`, `logout`, `password.*`, `verification.*`) são bem conhecidas e não causam conflito.

### Wayfinder Regeneration

Após modificar rotas PHP:
```bash
sail artisan wayfinder:generate --with-form
```

Isso regenera todos os arquivos em `resources/js/routes/`.

### Impacto em Testes

Testes que usam nomes de rotas precisarão ser atualizados:
- `$this->get(route('dashboard'))` → `$this->get(route('central.dashboard'))`
- `$this->get(route('projects.index'))` → `$this->get(route('tenant.projects.index'))`

---

## Resumo de Mudanças por Arquivo

| Arquivo | Mudanças |
|---------|----------|
| `routes/web.php` | 2 rotas → `central.*` |
| `routes/admin.php` | ~40 rotas → `central.*` |
| `routes/tenant.php` | ~30 rotas → `tenant.*` |
| `routes/settings.php` | 7 rotas → `universal.*` |
| Controllers | ~15 arquivos com `route()` |
| Middlewares | 2-3 arquivos |
| React Components | ~50 arquivos |
| Tests | ~20 arquivos |

**Total estimado:** ~90 arquivos a modificar
