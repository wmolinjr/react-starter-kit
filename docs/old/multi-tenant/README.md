# Multi-Tenant SaaS Boilerplate - Documentação Completa

Documentação completa para implementar um SaaS multi-tenant com Laravel 12 + Inertia.js/React 19.

## 🎯 Visão Geral

Este boilerplate fornece uma base sólida para criar aplicações SaaS multi-tenant com:

- ✅ **Multi-tenancy** - Single database com tenant_id isolation
- ✅ **Autenticação** - Laravel Fortify com 2FA opcional
- ✅ **Billing** - Laravel Cashier (Stripe) com planos e assinaturas
- ✅ **Team Management** - Sistema de convites e roles (owner/admin/member/guest)
- ✅ **File Storage** - Spatie MediaLibrary com isolamento por tenant
- ✅ **API Tokens** - Laravel Sanctum tenant-scoped
- ✅ **Custom Domains** - Subdomain + custom domains ilimitados
- ✅ **Branding** - Customização por tenant (logo, cores, CSS)

---

## 📚 Documentação por Etapa

### Fundação

| # | Arquivo | Descrição | Status |
|---|---------|-----------|--------|
| 00 | [OVERVIEW.md](00-OVERVIEW.md) | Visão geral da arquitetura e decisões técnicas | ✅ |
| 01 | [SETUP.md](01-SETUP.md) | Instalação de pacotes e configuração inicial | ✅ |
| 02 | [DATABASE.md](02-DATABASE.md) | Schema completo e migrations | ✅ |
| 03 | [MODELS.md](03-MODELS.md) | Models, relacionamentos e traits | ✅ |

### Core Features

| # | Arquivo | Descrição | Status |
|---|---------|-----------|--------|
| 04 | [ROUTING.md](04-ROUTING.md) | Estratégia de rotas (central vs tenant) | ✅ |
| 05 | [AUTHORIZATION.md](05-AUTHORIZATION.md) | Roles, permissões e gates | ✅ |
| 06 | [TEAM-MANAGEMENT.md](06-TEAM-MANAGEMENT.md) | Sistema de convites e gerenciamento de equipe | ✅ |
| 07 | [BILLING.md](07-BILLING.md) | Cashier, Stripe, planos e assinaturas | ✅ |

### Advanced Features

| # | Arquivo | Descrição | Status |
|---|---------|-----------|--------|
| 08 | [FILE-STORAGE.md](08-FILE-STORAGE.md) | Spatie MediaLibrary tenant-isolated | ✅ |
| 09 | [IMPERSONATION.md](09-IMPERSONATION.md) | Super admin impersonation | ✅ |
| 10 | [API-TOKENS.md](10-API-TOKENS.md) | Laravel Sanctum tenant-scoped | ✅ |
| 11 | [TENANT-SETTINGS.md](11-TENANT-SETTINGS.md) | Settings JSON, branding, custom domains | ✅ |

### Frontend & Testing

| # | Arquivo | Descrição | Status |
|---|---------|-----------|--------|
| 12 | [INERTIA-INTEGRATION.md](12-INERTIA-INTEGRATION.md) | Shared props, hooks, TypeScript types | ✅ |
| 13 | [TESTING.md](13-TESTING.md) | Feature tests com tenant isolation | ✅ |

### Production

| # | Arquivo | Descrição | Status |
|---|---------|-----------|--------|
| 14 | [DEPLOYMENT.md](14-DEPLOYMENT.md) | Nginx, SSL, queue workers, deploy | ✅ |
| 15 | [SECURITY.md](15-SECURITY.md) | Checklist completo de segurança | ✅ |
| 99 | [TROUBLESHOOTING.md](99-TROUBLESHOOTING.md) | Problemas comuns e soluções | ✅ |

---

## 🚀 Quick Start

### 1. Leia a Visão Geral

Comece por [00-OVERVIEW.md](00-OVERVIEW.md) para entender a arquitetura geral.

### 2. Siga em Ordem

A documentação está organizada em ordem de implementação. Siga os passos:

```
01-SETUP → 02-DATABASE → 03-MODELS → ... → 15-SECURITY
```

### 3. Implementação Faseada

Você pode implementar em fases:

#### Fase 1: MVP Básico (1-2 semanas)
- ✅ 01-SETUP: Instalação
- ✅ 02-DATABASE: Migrations
- ✅ 03-MODELS: Models básicos
- ✅ 04-ROUTING: Rotas centrais + tenant
- ✅ 05-AUTHORIZATION: Roles básicos
- ✅ 06-TEAM-MANAGEMENT: Convites

#### Fase 2: Monetização (1 semana)
- ✅ 07-BILLING: Stripe + Cashier
- ✅ 11-TENANT-SETTINGS: Limites por plano

#### Fase 3: Features Avançadas (1-2 semanas)
- ✅ 08-FILE-STORAGE: Upload de arquivos
- ✅ 09-IMPERSONATION: Super admin
- ✅ 10-API-TOKENS: API REST
- ✅ 12-INERTIA-INTEGRATION: Frontend polido

#### Fase 4: Produção (1 semana)
- ✅ 13-TESTING: Testes automatizados
- ✅ 14-DEPLOYMENT: Deploy
- ✅ 15-SECURITY: Hardening

---

## 📦 Stack Tecnológico

### Backend
- **Laravel 12** - Framework PHP
- **archtechx/tenancy** - Multi-tenancy core
- **Laravel Cashier (Stripe)** - Billing
- **Spatie MediaLibrary** - File management
- **Laravel Sanctum** - API authentication
- **PostgreSQL 18** - Database

### Frontend
- **React 19** - UI library
- **Inertia.js** - Bridge Laravel ↔ React
- **TypeScript** - Type safety
- **Tailwind CSS 4** - Styling
- **shadcn/ui** - UI components
- **Radix UI** - Accessible primitives

### DevOps
- **Vite 7** - Build tool
- **Laravel Telescope** - Debugging
- **Nginx** - Web server
- **Supervisor** - Queue workers
- **Redis** - Cache & queues

---

## 🎓 Conceitos-Chave

### Multi-Tenancy

Este boilerplate usa **single database com tenant_id isolation**:

- Um único banco de dados PostgreSQL
- Cada tabela tenant-scoped tem coluna `tenant_id`
- Global scopes automáticos via `BelongsToTenant` trait
- Identificação via domain (subdomain ou custom domain)

**Vantagens:**
- Simpler infrastructure
- Custo-benefício melhor
- Backups e migrations centralizados
- Escalável até ~10k tenants

### User ↔ Tenant (N:N)

Usuários podem pertencer a múltiplos tenants:

```
User 1 → Tenant A (owner)
      → Tenant B (admin)
      → Tenant C (member)
```

Relacionamento via pivot table `tenant_user` com roles.

### Domain Identification

Cada tenant pode ter múltiplos domínios:

```
Tenant "Acme Corp":
  - acme.setor3.app (subdomain padrão, is_primary=true)
  - acme.com (custom domain, is_primary=false)
  - acmeinc.com (custom domain 2)
```

Middleware `InitializeTenancyByDomain` detecta automaticamente.

---

## 🛠️ Ferramentas de Desenvolvimento

### Context7 MCP (Documentação)

**⚠️ SEMPRE consultar ANTES de implementar features:**

```bash
# Resolver library ID
context7.resolve-library-id("inertia react")

# Obter docs
context7.get-library-docs("/inertiajs/inertia", topic="forms")
```

### Telescope MCP (Debugging)

**⚠️ SEMPRE verificar após mudanças no backend:**

- **Requests** - Ver HTTP requests e responses
- **Queries** - Detectar N+1 problems
- **Exceptions** - Stack traces de erros
- **Logs** - Application logs

Acesse: `http://localhost/telescope`

### Playwright MCP (Testing Frontend)

**⚠️ Testar páginas após criar/modificar:**

```javascript
browser_navigate("http://tenant.myapp.test/dashboard")
browser_console_messages(onlyErrors: true)
browser_snapshot()
```

---

## 📖 Recursos Adicionais

### Documentação Oficial

- **archtechx/tenancy:** https://tenancyforlaravel.com/docs/v4
- **Laravel 12:** https://laravel.com/docs/12.x
- **Laravel Cashier:** https://laravel.com/docs/12.x/cashier
- **Spatie MediaLibrary:** https://spatie.be/docs/laravel-medialibrary
- **Inertia.js:** https://inertiajs.com
- **React 19:** https://react.dev

### Comunidades

- Laravel Discord
- Stack Overflow (tag: laravel-tenancy)
- GitHub Issues dos pacotes

---

## 🤝 Contribuindo

Esta documentação está viva! Se encontrar erros ou tiver sugestões:

1. Abra uma issue
2. Envie um PR com melhorias
3. Compartilhe feedback

---

## 📝 Checklist Geral de Implementação

Use este checklist para acompanhar seu progresso:

### Setup Inicial
- [ ] Pacotes instalados (archtechx/tenancy, cashier, medialibrary)
- [ ] Configuração do tenancy
- [ ] Domínios locais configurados (Herd/Valet)

### Database
- [ ] Migrations criadas (tenants, domains, tenant_user)
- [ ] Indexes em tenant_id
- [ ] Seeder de tenants de teste

### Models
- [ ] Tenant model com Billable
- [ ] User model com relacionamento tenants
- [ ] BelongsToTenant trait criado
- [ ] TenantScope criado

### Rotas & Auth
- [ ] routes/web.php (central app)
- [ ] routes/tenant.php (tenant app)
- [ ] Middleware configurados
- [ ] Gates e policies

### Features
- [ ] Team management (convites, roles)
- [ ] Billing (Cashier + Stripe)
- [ ] File storage (MediaLibrary)
- [ ] API tokens (Sanctum)
- [ ] Tenant settings (branding, domains)

### Frontend
- [ ] Shared props (tenant, permissions)
- [ ] TypeScript types
- [ ] Hooks (useTenant, usePermissions)
- [ ] Componentes (TenantSwitcher, Can)

### Produção
- [ ] Testes automatizados
- [ ] Deploy configurado (Nginx, SSL)
- [ ] Security checklist
- [ ] Monitoring (Telescope, Sentry)

---

## 🎉 Conclusão

Siga a documentação em ordem, consulte os MCPs quando necessário, e você terá um SaaS multi-tenant robusto e escalável!

**Boa sorte! 🚀**

---

## 🤖 Agente Especialista

### Multi-Tenant SaaS Builder Agent

Criamos um **agente especializado** que implementa o sistema seguindo rigorosamente esta documentação!

**Características:**
- ✅ **Rigoroso:** Testa tudo antes de concluir
- ✅ **Consultivo:** Pergunta quando tem dúvidas
- ✅ **Documentado:** Registra cada etapa em IMPLEMENTATION-LOG.md
- ✅ **Seguro:** Não pula etapas ou ignora erros
- ✅ **Integrado:** Usa Context7, Telescope e Playwright MCPs

### Como Usar o Agente

**Leia primeiro:** [HOW-TO-USE-AGENT.md](HOW-TO-USE-AGENT.md)

**Quick Start:**
```
"Implemente o Multi-Tenant SaaS seguindo AGENT-GUIDE.md.
Comece pela Etapa 1 (Setup Inicial)."
```

**O agente irá:**
1. Ler a documentação da etapa
2. Perguntar se tiver dúvidas
3. Implementar código
4. Testar (Telescope + Playwright + PHPUnit)
5. Documentar em IMPLEMENTATION-LOG.md
6. Perguntar se pode prosseguir

### Arquivos do Agente

| Arquivo | Descrição |
|---------|-----------|
| [AGENT-GUIDE.md](AGENT-GUIDE.md) | Guia completo do agente (workflow, regras, princípios) |
| [HOW-TO-USE-AGENT.md](HOW-TO-USE-AGENT.md) | Como invocar e interagir com o agente |
| [IMPLEMENTATION-LOG.md](IMPLEMENTATION-LOG.md) | Log de progresso (atualizado pelo agente) |

---

**Versão da Documentação:** 1.0
**Última Atualização:** 2025-11-19
**Compatibilidade:** Laravel 12, React 19, Inertia.js 2
