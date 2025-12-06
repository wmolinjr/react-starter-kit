# MCP Tools Workflow

Este projeto tem acesso a ferramentas MCP para debugging, testes e documentação. **Use estas ferramentas proativamente durante o desenvolvimento.**

## Usuários de Teste (Seeders)

**Rodar Seeders**:
```bash
sail artisan migrate:fresh --seed
```

### Usuários Criados

| Tipo | Nome | Email | Senha | Domain | Role | Permissions |
|------|------|-------|-------|--------|------|-------------|
| **Super Admin** | Super Admin | `admin@setor3.app` | `password` | Global | Super Admin | Todas (bypass) |
| **Tenant 1 Owner** | John Doe | `john@acme.com` | `password` | tenant1.localhost | owner | 22 permissions |
| **Tenant 2 Owner** | Jane Smith | `jane@startup.com` | `password` | tenant2.localhost | owner | 22 permissions |

### Detalhes dos Tenants

**Tenant 1 - Acme Corporation**:
- **Domain**: `http://tenant1.localhost`
- **Owner**: John Doe (john@acme.com)
- **Settings**:
  - Primary color: `#3b82f6` (blue)
  - Max users: 50
  - Max projects: 100

**Tenant 2 - Startup Inc**:
- **Domain**: `http://tenant2.localhost`
- **Owner**: Jane Smith (jane@startup.com)
- **Settings**:
  - Primary color: `#10b981` (green)
  - Max users: 10
  - Max projects: 25

### Exemplos de Uso com Playwright MCP

**Testar como Super Admin**:
```javascript
// Login como super admin (sem tenant - acesso global)
browser_navigate("http://localhost/login")
browser_fill_form([
  {name: "email", value: "admin@setor3.app"},
  {name: "password", value: "password"}
])
browser_click("button[type=submit]")
// Super Admin pode acessar qualquer tenant via impersonation
```

**Testar como Tenant Owner**:
```javascript
// Login no Tenant 1
browser_navigate("http://tenant1.localhost/login")
browser_fill_form([
  {name: "email", value: "john@acme.com"},
  {name: "password", value: "password"}
])
browser_click("button[type=submit]")
// Acesso completo ao Tenant 1 (22 permissions)
```

**Testar Isolamento de Tenants**:
```javascript
// 1. Login no Tenant 1
browser_navigate("http://tenant1.localhost/login")
// Login com john@acme.com

// 2. Criar projeto no Tenant 1
browser_navigate("http://tenant1.localhost/projects/create")
// ... criar projeto

// 3. Tentar acessar do Tenant 2 (deve falhar)
browser_navigate("http://tenant2.localhost/login")
// Login com jane@startup.com

browser_navigate("http://tenant2.localhost/projects")
// Não deve ver projetos do Tenant 1 (isolamento garantido)
```

**Testar Permissions**:
```javascript
// Owner tem acesso a billing
browser_navigate("http://tenant1.localhost/settings/billing")
browser_console_messages(onlyErrors: true)
// Deve carregar sem erros (owner tem billing:view)

// Admin NÃO tem acesso a billing (criar user admin primeiro)
// Tentativa de acesso deve resultar em 403 ou redirect
```

## 1. Laravel Telescope MCP (Monitoramento Backend)

**Status**: Instalado e configurado (`laravel/telescope` + `lucianotonet/laravel-telescope-mcp`)

**Acesso**:
- Interface Web: `http://localhost/telescope`
- MCP Endpoint: `http://127.0.0.1:8000/telescope-mcp`

**⚠️ USO OBRIGATÓRIO**: Sempre verificar Telescope MCP **AUTOMATICAMENTE** após **QUALQUER** mudança no backend.

### Quando Verificar

- ✅ Após criar ou modificar Controllers
- ✅ Após criar ou modificar Models (verificar queries N+1)
- ✅ Após criar Jobs ou Events
- ✅ Após modificar Migrations
- ✅ Após testes falharem (verificar exceptions)
- ✅ Após qualquer request Inertia

### Ferramentas Disponíveis (19 no total)

| Ferramenta | Uso | O que Verificar |
|------------|-----|-----------------|
| **Requests** | HTTP requests | Status codes, tempo de resposta, payloads |
| **Exceptions** | Erros da aplicação | Stack traces, erros não tratados |
| **Queries** | Database queries | Queries lentas, N+1 problems, duplicatas |
| **Logs** | Application logs | Erros, warnings, debug info |
| **Jobs** | Queue jobs | Jobs falhados, retry attempts, payloads |
| **Models** | Eloquent operations | Queries geradas, eventos disparados |
| **Events** | Event dispatches | Listeners executados, ordem de eventos |
| **Mail** | Emails enviados | Recipients, assunto, conteúdo |
| **Cache** | Cache operations | Hits/misses, keys, TTL |
| **Redis** | Redis commands | Operações, performance |
| **HTTP Client** | Outgoing HTTP | APIs externas, responses, timeouts |
| **Notifications** | Notificações | Canais, status, conteúdo |
| **Commands** | Artisan commands | Execuções, status, output |
| **Views** | View renders | Templates renderizados, dados passados |
| **Dumps** | var_dump/dd() | Debug outputs, valores |
| **Gates** | Authorization | Checks de permissão, results |
| **Schedule** | Scheduled tasks | Cron jobs, execuções |
| **Batches** | Batch operations | Jobs em batch, progresso |
| **Prune** | Limpar dados antigos | Limpeza de entries |

### Exemplo de Workflow

```
1. Criar ProfileController com método update()
2. ✅ OBRIGATÓRIO: Verificar Telescope MCP
   - Ferramenta: Requests (verificar request POST/PATCH)
   - Ferramenta: Queries (verificar se não há N+1)
   - Ferramenta: Exceptions (verificar se não há erros)
3. Se encontrar problema:
   - Analisar stack trace em Exceptions
   - Verificar queries geradas em Queries
   - Ver logs em Logs
4. Corrigir e verificar novamente
```

### Queries N+1 - Exemplo

```php
// ❌ Problema N+1 detectado no Telescope
User::all()->each(fn($user) => $user->posts);
// Telescope mostrará: 1 query para users + N queries para posts

// ✅ Solução verificada no Telescope
User::with('posts')->get();
// Telescope mostrará: 2 queries apenas (users + posts)
```

## 2. Context7 MCP (Documentação de Bibliotecas)

**Status**: Disponível via MCP

**⚠️ PRIORIDADE MÁXIMA**: **SEMPRE** consultar Context7 **ANTES** de implementar qualquer feature.

### Quando Usar

1. ✅ **Antes de implementar** features com Inertia, React, Laravel
2. ✅ **Para buscar exemplos** de código corretos
3. ✅ **Para verificar best practices** das bibliotecas
4. ✅ **Quando encontrar erros** - buscar soluções primeiro no Context7
5. ✅ **Antes de usar** novas bibliotecas ou APIs

### Bibliotecas Principais

- `/laravel/framework` - Laravel 12 documentation
- `/inertiajs/inertia-laravel` - Inertia.js backend
- `/inertiajs/inertia` - Inertia.js core
- `/facebook/react` - React 19
- `/tailwindlabs/tailwindcss` - Tailwind CSS 4
- `/radix-ui/primitives` - Radix UI components
- `/shadcn/ui` - shadcn/ui components

### Como Usar

```
1. Identificar a biblioteca: "preciso usar Inertia Form"
2. Resolver library ID: Context7.resolve-library-id("inertia")
3. Buscar docs: Context7.get-library-docs("/inertiajs/inertia", topic="forms")
4. Ler exemplos e best practices
5. Implementar seguindo os padrões
```

### Exemplo de Workflow

```
Tarefa: Criar formulário de login com Inertia

1. ✅ Consultar Context7 PRIMEIRO
   - resolve-library-id("inertia react")
   - get-library-docs("/inertiajs/inertia", topic="forms")
   - get-library-docs("/inertiajs/inertia", topic="validation")

2. Aprender o padrão correto:
   - Form component com render props
   - Wayfinder para routes type-safe
   - Error handling automático

3. Implementar seguindo documentação

4. ✅ Verificar no Telescope que funcionou
```

## 3. Playwright MCP (Testes Frontend)

**Status**: Disponível via MCP (20+ ferramentas de browser)

### Quando Usar

1. ✅ **Após criar/modificar** páginas Inertia
2. ✅ **Testar fluxos de formulários** end-to-end
3. ✅ **Verificar erros de console** JavaScript
4. ✅ **Capturar screenshots** para review visual
5. ✅ **Validar navegação** entre páginas

### Ferramentas Principais

| Ferramenta | Uso |
|------------|-----|
| `browser_navigate` | Navegar para URLs |
| `browser_snapshot` | Capturar estado da página (accessibility tree) |
| `browser_click` | Clicar em elementos |
| `browser_fill_form` | Preencher formulários |
| `browser_type` | Digitar em inputs |
| `browser_console_messages` | Ver erros/logs do console |
| `browser_take_screenshot` | Capturar screenshot |
| `browser_wait_for` | Esperar elementos/texto |
| `browser_network_requests` | Ver requisições de rede |

### Exemplo de Workflow - Testar Página de Perfil

```
1. Criar/modificar resources/js/pages/settings/profile.tsx

2. ✅ Testar com Playwright:
   a. browser_navigate("http://localhost/settings/profile")
   b. browser_console_messages() - verificar erros JavaScript
   c. browser_snapshot() - ver estrutura da página
   d. browser_fill_form([
        {name: "name", value: "Teste User"},
        {name: "email", value: "test@example.com"}
      ])
   e. browser_click("button[type=submit]")
   f. browser_wait_for(text: "Profile updated")
   g. browser_network_requests() - verificar request Inertia

3. ✅ Verificar no Telescope MCP:
   - Requests: ver PATCH /settings/profile
   - Queries: verificar update executado
   - Exceptions: garantir sem erros

4. Se houver erros:
   - Console: browser_console_messages(onlyErrors: true)
   - Network: browser_network_requests() - ver failed requests
   - Telescope: Exceptions - ver backend errors
```

## Workflows Completos

### Ao Criar/Modificar Backend

```
1. ✅ Consultar Context7
   - Buscar best practices Laravel/Inertia
   - Ver exemplos de código correto
   - Verificar API correta

2. ✅ Implementar mudanças
   - Seguir padrões da documentação
   - Usar type hints e validações

3. ✅ OBRIGATÓRIO: Verificar Telescope MCP
   - Exceptions: garantir sem erros
   - Queries: verificar performance (sem N+1)
   - Requests: validar request/response
   - Logs: verificar warnings

4. ✅ Rodar testes
   - sail artisan test
   - Se falhar: verificar Telescope Exceptions

5. ✅ Validação final
   - Todas as ferramentas Telescope em verde
   - Testes passando
   - Sem queries lentas
```

### Ao Criar/Modificar Frontend

```
1. ✅ Consultar Context7
   - Buscar best practices React/Inertia
   - Ver exemplos de Form, usePage, Link
   - Verificar hooks corretos

2. ✅ Implementar componente/página
   - Seguir padrões de arquitetura
   - Usar TypeScript com tipos corretos
   - Importar de @/routes (Wayfinder)

3. ✅ OBRIGATÓRIO: Testar com Playwright MCP
   - browser_navigate: acessar página
   - browser_console_messages: verificar erros
   - browser_snapshot: ver renderização
   - browser_fill_form + click: testar formulários
   - browser_take_screenshot: capturar visual

4. ✅ Verificar Telescope MCP
   - Requests: ver requests Inertia (JSON)
   - Exceptions: garantir sem backend errors
   - Queries: verificar dados carregados

5. ✅ Validação final
   - Console sem erros JavaScript
   - Formulários funcionando
   - Navegação correta
   - Backend respondendo JSON
```

### Ao Encontrar Erros

```
1. ✅ Identificar origem
   - Frontend? browser_console_messages
   - Backend? Telescope Exceptions
   - Ambos? Verificar os dois

2. ✅ Context7: Buscar solução
   - Procurar erro na documentação
   - Ver exemplos corretos
   - Verificar breaking changes

3. ✅ Telescope MCP: Detalhes do erro
   - Stack trace completo
   - Request/response data
   - Queries executadas

4. ✅ Playwright MCP: Reproduzir erro
   - browser_navigate para página
   - Executar ações que causam erro
   - Verificar console e network

5. ✅ Corrigir e validar
   - Implementar correção
   - Testar novamente com Playwright
   - Verificar Telescope sem erros
   - Confirmar testes passando
```

## Checklist Antes de Considerar Tarefa Completa

- [ ] Context7 consultado para best practices
- [ ] Código implementado seguindo padrões
- [ ] Telescope MCP verificado (sem exceptions, queries OK)
- [ ] Playwright MCP testado (página funciona, sem console errors)
- [ ] Testes automatizados passando
- [ ] TypeScript sem erros (`sail npm run types`)
- [ ] ESLint sem warnings (`sail npm run lint`)
- [ ] Código formatado (`sail npm run format`)
