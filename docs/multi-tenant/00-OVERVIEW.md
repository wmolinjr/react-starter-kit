# Multi-Tenant SaaS Boilerplate - Visão Geral

## Índice

- [Introdução](#introdução)
- [Stack Tecnológico](#stack-tecnológico)
- [Arquitetura Geral](#arquitetura-geral)
- [Decisões Arquiteturais](#decisões-arquiteturais)
- [Fluxo de Autenticação](#fluxo-de-autenticação)
- [Diagrama de Relacionamentos](#diagrama-de-relacionamentos)
- [Funcionalidades Principais](#funcionalidades-principais)

---

## Introdução

Este projeto é um **Multi-Tenant SaaS Boilerplate** construído com Laravel 12 e React 19 usando Inertia.js. O objetivo é fornecer uma base sólida para criar aplicações SaaS multi-tenant com autenticação, billing, gerenciamento de equipes e mais.

### O que é Multi-Tenancy?

Multi-tenancy é uma arquitetura onde uma única instância da aplicação serve múltiplos clientes (tenants). Cada tenant tem seus dados isolados, mas compartilha a mesma infraestrutura.

**Benefícios:**
- ✅ Redução de custos de infraestrutura
- ✅ Manutenção centralizada
- ✅ Escalabilidade eficiente
- ✅ Deploy único para todos os clientes

---

## Stack Tecnológico

### Backend
- **Laravel 12** - Framework PHP moderno
- **Laravel Fortify** - Autenticação (já instalado)
- **archtechx/tenancy** - Multi-tenancy core
- **Laravel Cashier (Stripe)** - Billing e assinaturas
- **Spatie MediaLibrary** - Gerenciamento de arquivos
- **Laravel Sanctum** - API tokens

### Frontend
- **React 19** - UI library
- **TypeScript** - Type safety
- **Inertia.js** - Bridge Laravel ↔ React
- **Tailwind CSS 4** - Styling
- **shadcn/ui** - Componentes UI
- **Radix UI** - Primitivos acessíveis
- **Lucide Icons** - Ícones

### Build & Tools
- **Vite 7** - Build tool
- **Laravel Wayfinder** - Type-safe routes
- **Telescope** - Debug e monitoring
- **Pint** - PHP code formatter
- **ESLint + Prettier** - JS/TS linting

---

## Arquitetura Geral

### Single Database Strategy

Este boilerplate usa **single database com tenant_id isolation**:

```
┌─────────────────────────────────────────┐
│         PostgreSQL Database              │
│                                          │
│  ┌─────────────┐  ┌──────────────────┐ │
│  │   users     │  │     tenants      │ │
│  │  (global)   │  │  - id            │ │
│  │  - id       │  │  - name          │ │
│  │  - email    │  │  - slug          │ │
│  └──────┬──────┘  │  - settings      │ │
│         │         └────────┬─────────┘ │
│         │                  │           │
│         └──────────┬───────┘           │
│                    │                   │
│         ┌──────────▼──────────────┐   │
│         │    tenant_user (N:N)    │   │
│         │  - tenant_id            │   │
│         │  - user_id              │   │
│         │  - role (owner/admin)   │   │
│         └─────────────────────────┘   │
│                                        │
│  ┌────────────────────────────────┐  │
│  │   Tenant-Scoped Tables         │  │
│  │  (all have tenant_id)          │  │
│  │                                 │  │
│  │  - projects                     │  │
│  │  - invoices                     │  │
│  │  - documents                    │  │
│  │  - media                        │  │
│  └────────────────────────────────┘  │
└─────────────────────────────────────────┘
```

**Isolamento de Dados:**
- Cada tabela tenant-scoped tem coluna `tenant_id`
- Global scopes automáticos via `BelongsToTenant` trait
- Índices em `tenant_id` para performance
- Queries sempre filtradas pelo tenant atual

---

## Decisões Arquiteturais

### 1. **Relacionamento User ↔ Tenant: N:N**

✅ **Escolhido:** Um usuário pode pertencer a múltiplos tenants

**Justificativa:**
- Usuários podem ser convidados para múltiplas organizações
- Não duplica registros de usuários
- Flexibilidade para colaboração entre tenants
- Padrão comum em SaaS modernos (Slack, GitHub, etc.)

**Implementação:**
```php
// Tabela pivot: tenant_user
tenant_id | user_id | role       | invited_at | joined_at
----------|---------|-----------|------------|----------
1         | 10      | owner     | NULL       | 2025-01-15
1         | 11      | admin     | 2025-01-15 | 2025-01-16
2         | 10      | member    | 2025-01-18 | 2025-01-18
```

### 2. **Estratégia de Isolamento: Single Database**

✅ **Escolhido:** Tenant ID em todas as tabelas (single database)

**Alternativas descartadas:**
- ❌ Multi-database (complexidade desnecessária para <10k tenants)
- ❌ Schema-based (PostgreSQL-only, maior complexidade)

**Justificativa:**
- Simpler infrastructure
- Melhor custo-benefício
- Backups e migrations unificados
- Escalável até ~10k tenants com indexes corretos
- Mais fácil de manter

### 3. **Autorização: Pivot Table Roles**

✅ **Escolhido:** Roles no pivot `tenant_user.role`

**Alternativas descartadas:**
- ❌ spatie/laravel-permission tenant-scoped (over-engineering)

**Justificativa:**
- Simpler para maioria dos SaaS
- 4 roles são suficientes: `owner`, `admin`, `member`, `guest`
- Melhor performance (sem joins extras)
- Mais fácil de entender

**Roles:**
- **Owner** - Criador do tenant, acesso total, gerencia billing
- **Admin** - Gerencia equipe e configurações (exceto billing)
- **Member** - Usa a aplicação, acesso limitado
- **Guest** - Apenas leitura (read-only)

### 4. **Billing: Tenant como Entidade Billable**

✅ **Escolhido:** Tenant implementa `Billable` (Cashier)

**Justificativa:**
- Uma assinatura por organização (não por usuário)
- Owner gerencia billing
- Padrão comum em SaaS B2B
- Simples de implementar

**Planos:**
- **Starter** - $9/mês (10 usuários, 50 projetos)
- **Professional** - $29/mês (50 usuários, projetos ilimitados)
- **Enterprise** - $99/mês (ilimitado + suporte prioritário)

### 5. **Identificação de Tenant: Domain-Based**

✅ **Escolhido:** Subdomain + Custom Domains

**Funcionamento:**
```
tenant.myapp.com  →  Tenant identificado por subdomain "tenant"
custom.com        →  Tenant identificado via tabela domains
```

**Tabela `domains`:**
```php
id | tenant_id | domain           | is_primary
---|-----------|------------------|------------
1  | 1         | acme.myapp.com   | true
2  | 1         | acme.com         | false
3  | 2         | startup.myapp.com| true
```

---

## Fluxo de Autenticação

### 1. Registro de Novo Tenant (Sign Up)

```
User                    Central App              Database
  │                          │                       │
  ├─ GET /register           │                       │
  │◄─────────────────────────┤                       │
  │                          │                       │
  ├─ POST /register          │                       │
  │  (name, email, org)      │                       │
  │──────────────────────────►                       │
  │                          ├─ Create User          │
  │                          ├─────────────────────► │
  │                          │                       │
  │                          ├─ Create Tenant        │
  │                          │   (slug from org)     │
  │                          ├─────────────────────► │
  │                          │                       │
  │                          ├─ Create Domain        │
  │                          │   (slug.myapp.com)    │
  │                          ├─────────────────────► │
  │                          │                       │
  │                          ├─ Attach User          │
  │                          │   (role: owner)       │
  │                          ├─────────────────────► │
  │                          │                       │
  │◄─ Redirect to            │                       │
  │   slug.myapp.com/dashboard                      │
```

### 2. Login em Tenant Existente

```
User                    Tenant App              Tenancy Middleware
  │                          │                       │
  ├─ GET acme.myapp.com      │                       │
  │──────────────────────────►                       │
  │                          ├─ InitializeTenancyByDomain
  │                          │   Extract "acme"      │
  │                          │   Find Tenant         │
  │                          │   Set tenant context  │
  │                          │◄──────────────────────┤
  │                          │                       │
  │◄─ Login page (tenant-aware)                     │
  │                          │                       │
  ├─ POST /login             │                       │
  │  (email, password)       │                       │
  │──────────────────────────►                       │
  │                          ├─ Verify user has      │
  │                          │   access to tenant    │
  │                          │   (tenant_user pivot) │
  │                          │                       │
  │◄─ Dashboard (tenant scope active)               │
```

### 3. Switching Between Tenants

```tsx
// React Component
import { usePage } from '@inertiajs/react';

function TenantSwitcher() {
  const { auth } = usePage().props;

  return (
    <select onChange={(e) => {
      window.location.href = `https://${e.target.value}.myapp.com/dashboard`;
    }}>
      {auth.user.tenants.map(tenant => (
        <option key={tenant.id} value={tenant.slug}>
          {tenant.name}
        </option>
      ))}
    </select>
  );
}
```

---

## Diagrama de Relacionamentos

```
┌──────────────────────────────────────────────────────────────┐
│                       CENTRAL APP                             │
│                    (app.myapp.com)                           │
│                                                               │
│  - Landing page                                              │
│  - Sign up (create tenant + user)                           │
│  - Pricing                                                   │
│  - Docs                                                      │
└──────────────────────────────────────────────────────────────┘
                              │
                              │ User signs up
                              ▼
┌──────────────────────────────────────────────────────────────┐
│                      CREATE TENANT                            │
│                                                               │
│  1. Create User (users table)                                │
│  2. Create Tenant (tenants table)                            │
│  3. Create Domain (domains table)                            │
│     - slug.myapp.com                                         │
│  4. Attach User to Tenant (tenant_user pivot)               │
│     - role: owner                                            │
│                                                               │
│  Redirect to: slug.myapp.com/dashboard                      │
└──────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌──────────────────────────────────────────────────────────────┐
│                      TENANT APP                               │
│                   (slug.myapp.com)                           │
│                                                               │
│  [InitializeTenancyByDomain Middleware]                     │
│   ↓                                                          │
│  Extract subdomain "slug"                                    │
│   ↓                                                          │
│  Find Tenant via domains table                              │
│   ↓                                                          │
│  Set global tenant context                                  │
│   ↓                                                          │
│  All queries auto-scoped to tenant_id                       │
│                                                               │
│  ┌────────────────────────────────────────────────────────┐ │
│  │ Protected Routes (auth middleware)                     │ │
│  │                                                         │ │
│  │  - /dashboard                                          │ │
│  │  - /projects                                           │ │
│  │  - /team (can:manage-team)                            │ │
│  │  - /billing (can:manage-billing, owner only)          │ │
│  │  - /settings                                           │ │
│  └────────────────────────────────────────────────────────┘ │
└──────────────────────────────────────────────────────────────┘
```

### Relacionamento de Dados

```
users (global)
  ├──◄ belongsToMany ───► tenants
  │                        ├── hasMany ──► domains
  │                        ├── hasMany ──► subscriptions (Cashier)
  │                        └── hasMany ──► projects (tenant-scoped)
  │                                         ├── belongsTo ──► user
  │                                         └── hasMany ──► media
  │
  └──◄ tenant_user pivot ►
       - role (owner/admin/member/guest)
       - invited_at
       - joined_at
```

---

## Funcionalidades Principais

### ✅ 1. Team Management
- Convidar usuários por email
- Sistema de tokens de convite
- Gerenciamento de roles (owner/admin/member/guest)
- Remover membros
- Atualizar permissões

### ✅ 2. Tenant Settings & Branding
- Logo customizado (por tenant)
- Cores do tema (primary color)
- Custom CSS
- Feature flags (habilitar/desabilitar funcionalidades)
- Limites por plano (max users, storage, etc.)

### ✅ 3. Impersonation
- Super admin pode fazer impersonate de qualquer tenant
- Super admin pode fazer impersonate de qualquer usuário
- Session-based com proteção
- Middleware para prevenir ações sensíveis durante impersonation

### ✅ 4. API Tokens & Webhooks
- Laravel Sanctum tenant-scoped
- Cada tenant pode gerar tokens de API
- Rate limiting por tenant
- Webhooks configuráveis (em breve)

### ✅ 5. Billing & Subscriptions
- Integração com Stripe via Cashier
- Planos: Starter ($9), Pro ($29), Enterprise ($99)
- Customer Portal (gerenciar cartões, invoices)
- Webhook handling automático
- Trial period (14 dias)
- Subscription enforcement middleware

### ✅ 6. File Storage
- Arquivos isolados por tenant
- Spatie MediaLibrary
- Conversões de imagens (thumbnails, etc.)
- S3-compatible storage com prefixo por tenant
- Download URLs assinadas

### ✅ 7. Custom Domains
- Subdomain padrão: `{slug}.myapp.com`
- Custom domains ilimitados (Pro+)
- Domínio principal por tenant
- SSL automático (requer Cloudflare/Let's Encrypt)

---

## Próximos Passos

Agora que você entendeu a visão geral, siga a documentação em ordem:

1. **[01-SETUP.md](./01-SETUP.md)** - Instalação e configuração inicial
2. **[02-DATABASE.md](./02-DATABASE.md)** - Schema e migrations
3. **[03-MODELS.md](./03-MODELS.md)** - Models e relacionamentos
4. **[04-ROUTING.md](./04-ROUTING.md)** - Estratégia de rotas
5. **[05-AUTHORIZATION.md](./05-AUTHORIZATION.md)** - Roles e permissões
6. **[06-TEAM-MANAGEMENT.md](./06-TEAM-MANAGEMENT.md)** - Gerenciamento de equipes
7. **[07-BILLING.md](./07-BILLING.md)** - Sistema de assinaturas
8. E mais...

---

## Recursos Adicionais

- **archtechx/tenancy docs:** https://tenancyforlaravel.com/docs/v4
- **Laravel Cashier:** https://laravel.com/docs/12.x/cashier
- **Spatie MediaLibrary:** https://spatie.be/docs/laravel-medialibrary
- **Inertia.js:** https://inertiajs.com

---

**Versão:** 1.0
**Última atualização:** 2025-11-19
