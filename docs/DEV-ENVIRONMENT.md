# Guia do Ambiente de Desenvolvimento

Este documento orienta desenvolvedores sobre como configurar e executar o ambiente de desenvolvimento completo.

## Pré-requisitos

- **Docker Desktop** (Windows/Mac) ou **Docker Engine** (Linux)
- **Git**
- **Stripe CLI** (opcional, para webhooks)
- **PhpStorm** ou **VS Code**

### Instalação do Stripe CLI

```bash
# Linux (Debian/Ubuntu)
curl -s https://packages.stripe.dev/api/security/keypair/stripe-cli-gpg/public | gpg --dearmor | sudo tee /usr/share/keyrings/stripe.gpg
echo "deb [signed-by=/usr/share/keyrings/stripe.gpg] https://packages.stripe.dev/stripe-cli-debian-local stable main" | sudo tee -a /etc/apt/sources.list.d/stripe.list
sudo apt update && sudo apt install stripe

# macOS
brew install stripe/stripe-cli/stripe

# Windows (via Scoop)
scoop install stripe
```

## Configuração Inicial

### 1. Clone e Configure

```bash
git clone <repository-url>
cd react-starter-kit
cp .env.example .env
```

### 2. Configure o /etc/hosts

Adicione as seguintes entradas ao seu arquivo hosts:

```bash
# Linux/Mac: /etc/hosts
# Windows: C:\Windows\System32\drivers\etc\hosts

127.0.0.1 app.test
127.0.0.1 tenant1.test
127.0.0.1 tenant2.test
127.0.0.1 tenant3.test
```

### 3. Inicie os Containers

```bash
# Primeira vez - build necessário
./vendor/bin/sail build --no-cache

# Iniciar containers
./vendor/bin/sail up -d

# Instalar dependências
./vendor/bin/sail composer install
./vendor/bin/sail npm install
```

### 4. Configure o Banco de Dados

```bash
# Criar tabelas e dados de teste
./vendor/bin/sail artisan migrate:fresh --seed
```

### 5. Build Inicial

```bash
./vendor/bin/sail npm run build
```

## Iniciando o Ambiente de Desenvolvimento

### Opção 1: Script Automatizado (Recomendado)

O script `bin/dev-start.sh` inicia todos os serviços na ordem correta:

```bash
# Básico: Containers + Vite + Queue Worker
./bin/dev-start.sh

# Com Laravel Horizon (dashboard de filas)
./bin/dev-start.sh --horizon

# Com Scheduler
./bin/dev-start.sh --with-scheduler

# Com Stripe Webhooks
./bin/dev-start.sh --with-stripe

# Completo: Todos os serviços (inclui Horizon)
./bin/dev-start.sh --full
```

**O que o script faz:**
1. Inicia containers Docker (`sail up -d`)
2. Aguarda PostgreSQL estar pronto (health check)
3. Aguarda Redis estar pronto (health check)
4. Inicia Vite dev server
5. Inicia Queue Worker (ou Horizon com `--horizon`/`--full`)
6. (Opcional) Inicia Scheduler
7. (Opcional) Inicia Stripe webhook listener

**Para parar:** Pressione `Ctrl+C`

### Opção 2: PhpStorm Run Configurations

No PhpStorm, use as configurações pré-definidas:

| Configuração | Descrição |
|--------------|-----------|
| `Dev Start (All Services)` | Script sequencial - Containers + Vite + Queue |
| `Dev Start (Full)` | Script completo - Inclui Scheduler + Stripe |
| `[Dev Quick Start]` | 3 serviços em paralelo (sem esperar containers) |
| `[Full Dev Environment]` | 5 serviços em paralelo |

**Para usar:** Run → Select Configuration → Run (▶️)

### Opção 3: Terminais Manuais

Se preferir controle individual de cada serviço:

```bash
# Terminal 1 - Containers
sail up

# Terminal 2 - Vite (após containers prontos)
sail npm run dev

# Terminal 3 - Queue Worker (processa todas as filas em ordem de prioridade)
sail artisan queue:work redis --queue=high,default,federation,media --tries=3 --timeout=300

# Terminal 4 - Scheduler (opcional)
sail artisan schedule:work

# Terminal 5 - Stripe (opcional)
stripe listen --forward-to http://app.test/stripe/webhook
```

## URLs do Ambiente

| Serviço | URL | Descrição |
|---------|-----|-----------|
| **App Central** | http://app.test | Área central/administrativa |
| **Admin Login** | http://app.test/admin/login | Login de administradores |
| **Tenant 1** | http://tenant1.test | Primeiro tenant de teste |
| **Tenant 2** | http://tenant2.test | Segundo tenant de teste |
| **Tenant 3** | http://tenant3.test | Terceiro tenant de teste |
| **Mailpit** | http://localhost:8025 | Interface de emails de teste |
| **Telescope** | http://app.test/telescope | Debug e monitoring |
| **Horizon** | http://app.test/horizon | Dashboard de filas (Horizon) |
| **Vite** | http://localhost:5173 | Dev server (HMR) |

## Usuários de Teste

### Administradores Centrais (app.test/admin/login)

| Email | Senha | Role |
|-------|-------|------|
| `admin@setor3.app` | `password` | Super Admin |
| `support@setor3.app` | `password` | Support Admin |

### Usuários de Tenant

| Tenant | Email | Senha | Role |
|--------|-------|-------|------|
| tenant1.test | `john@acme.com` | `password` | Owner |
| tenant2.test | `jane@startup.com` | `password` | Owner |
| tenant3.test | `mike@enterprise.com` | `password` | Owner |

## Serviços e Portas

| Serviço | Porta | Função |
|---------|-------|--------|
| **Laravel** | 80 | Aplicação principal |
| **PostgreSQL** | 5432 | Banco de dados |
| **Redis** | 6379 | Cache, Sessions, Queue |
| **Vite** | 5173 | HMR e assets |
| **Mailpit SMTP** | 1025 | Recebe emails |
| **Mailpit Web** | 8025 | Interface de emails |

## Comandos Úteis

### Desenvolvimento Diário

```bash
# Ver logs em tempo real
sail logs -f

# Acessar shell do container
sail shell

# Rodar testes
sail artisan test

# Rodar testes em paralelo (mais rápido)
sail artisan test --parallel --processes=20

# Limpar caches
sail artisan optimize:clear

# Regenerar rotas TypeScript
sail artisan wayfinder:generate --with-form
```

### Banco de Dados

```bash
# Resetar banco com dados de teste
sail artisan migrate:fresh --seed

# Rodar migrations
sail artisan migrate

# Rollback última migration
sail artisan migrate:rollback

# Tinker (REPL)
sail artisan tinker

# Tinker no contexto de um tenant
sail artisan tenant:tinker <tenant-id>
```

### Multi-Tenancy

```bash
# Migrar todos os tenants
sail artisan tenants:migrate

# Migrar em paralelo (mais rápido)
sail artisan tenants:migrate -p 4

# Listar tenants
sail artisan tenants:list

# Sincronizar permissions
sail artisan permissions:sync
```

### Frontend

```bash
# Dev server com HMR
sail npm run dev

# Build produção
sail npm run build

# Build com SSR
sail npm run build:ssr

# Lint e format
sail npm run lint
sail npm run format

# Type check
sail npm run types
```

### Qualidade de Código

```bash
# Formatar PHP
vendor/bin/pint

# Verificar formatação
vendor/bin/pint --test

# Formatar JS/TS
sail npm run format

# Lint JS/TS
sail npm run lint
```

## Jobs e Filas

O sistema usa múltiplas filas separadas por prioridade:

| Fila | Prioridade | Jobs |
|------|------------|------|
| `high` | 1 (máxima) | Emails, notificações, webhooks |
| `default` | 2 | Tenant seeding, permission sync |
| `federation` | 3 | Sincronização de usuários federados |
| `media` | 4 (mínima) | Conversões de imagem (MediaLibrary) |

### Jobs por Fila

| Fila | Job | Descrição |
|------|-----|-----------|
| `default` | `CreateDatabase` | Cria banco do tenant |
| `default` | `MigrateDatabase` | Roda migrations do tenant |
| `default` | `SeedTenantDatabase` | Seed inicial do tenant |
| `default` | `SyncTenantPermissions` | Sincroniza permissions |
| `high` | `TeamInvitation` | Envia convites de equipe |
| `federation` | `SyncUserToFederatedTenantsJob` | Sync de usuários federados |
| `media` | `PerformConversionsJob` | Converte imagens (MediaLibrary) |

### Comando do Worker

```bash
# Desenvolvimento (todas as filas em ordem de prioridade)
sail artisan queue:work redis --queue=high,default,federation,media --tries=3 --timeout=300

# Ou via script automatizado
./bin/dev-start.sh
```

### Monitorar Jobs

```bash
# Ver jobs em tempo real
sail artisan queue:monitor high,default,federation,media

# Ver jobs falhos
sail artisan queue:failed

# Reprocessar job falho
sail artisan queue:retry <job-id>

# Reprocessar todos os falhos
sail artisan queue:retry all
```

### Laravel Horizon

Para um dashboard visual completo de filas, use o Laravel Horizon:

```bash
# Iniciar Horizon (substitui queue:work)
sail artisan horizon

# Via script automatizado
./bin/dev-start.sh --horizon

# Ou modo completo
./bin/dev-start.sh --full
```

**Dashboard**: http://app.test/horizon

**Features do Horizon:**
- Dashboard em tempo real
- Métricas de throughput e tempo de execução
- Visualização de jobs falhos com stack traces
- Tags automáticas por tenant
- Retry de jobs via interface

**Produção:** Veja [QUEUES.md](QUEUES.md) para configuração do Supervisor com Horizon.

## Agendamentos (Scheduler)

O Scheduler executa tarefas automáticas:

| Frequência | Tarefa |
|------------|--------|
| Diário | Limpeza de tokens expirados |
| Diário | Prune de Telescope entries |
| Horário | Verificação de subscriptions |

**Nota:** Em desenvolvimento, use `sail artisan schedule:work` para executar o scheduler em tempo real.

## Stripe Webhooks

Para testar pagamentos localmente:

```bash
# Autenticar (primeira vez)
stripe login

# Iniciar listener
stripe listen --forward-to http://app.test/stripe/webhook
```

O CLI mostrará um webhook signing secret. Adicione ao `.env`:

```env
STRIPE_WEBHOOK_SECRET=whsec_xxxxx
```

### Testar Eventos

```bash
# Simular evento de checkout
stripe trigger checkout.session.completed

# Simular cancelamento
stripe trigger customer.subscription.deleted
```

### Limpeza do Ambiente Stripe

O comando `stripe:cleanup` permite limpar recursos do ambiente de teste do Stripe:

```bash
# Listar todos os recursos (sem deletar)
sail artisan stripe:cleanup --list

# Limpar apenas produtos e preços
sail artisan stripe:cleanup --products

# Limpar apenas customers
sail artisan stripe:cleanup --customers

# Cancelar todas as subscriptions
sail artisan stripe:cleanup --subscriptions

# Limpar tudo (nuclear option)
sail artisan stripe:cleanup --all

# Limpar tudo sem confirmação
sail artisan stripe:cleanup --all --force
```

**Segurança:**
- Só funciona em ambiente `local` ou `testing`
- Só aceita chaves de teste (`sk_test_*`)
- Requer confirmação (use `--force` para pular)

**Comportamento:**
- **Subscriptions:** Canceladas (não podem ser deletadas)
- **Customers:** Deletados permanentemente
- **Prices:** Arquivados (Stripe não permite deletar preços)
- **Products:** Deletados se possível, arquivados caso contrário

**Quando usar:**
- Ambiente de dev com muitos dados de teste acumulados
- Antes de recriar planos/produtos com nova estrutura
- Limpeza periódica do ambiente de desenvolvimento

## Debugging

### Telescope

Acesse http://app.test/telescope para:

- **Requests:** Ver todas as requisições HTTP
- **Exceptions:** Stack traces de erros
- **Queries:** Queries SQL (detecta N+1)
- **Jobs:** Jobs processados/falhos
- **Mail:** Emails enviados
- **Cache:** Operações de cache
- **Redis:** Comandos Redis

### Logs

```bash
# Ver logs da aplicação
sail logs -f

# Ver apenas logs do Laravel
sail logs -f laravel.test

# Ver logs do PostgreSQL
sail logs -f pgsql

# Arquivo de log
tail -f storage/logs/laravel.log
```

### Xdebug

O Xdebug está configurado para debug remoto. No PhpStorm:

1. File → Settings → PHP → Debug
2. Configure porta 9003
3. Inicie listener (telefone verde)
4. Adicione breakpoints
5. Acesse a aplicação

## Troubleshooting

### Containers não iniciam

```bash
# Verificar se Docker está rodando
docker info

# Resetar containers
sail down -v
sail up -d --build
```

### Erro de conexão com banco

```bash
# Verificar se PostgreSQL está pronto
sail exec pgsql pg_isready -U sail -d laravel

# Verificar conexões
sail exec pgsql psql -U sail -d laravel -c "SELECT count(*) FROM pg_stat_activity;"
```

### Vite não conecta (HMR não funciona)

```bash
# Verificar se Vite está rodando
curl http://localhost:5173

# Reiniciar Vite
# Ctrl+C no terminal do Vite
sail npm run dev
```

### Permissões de arquivo

```bash
# Corrigir permissões de storage
sail artisan storage:link
chmod -R 775 storage bootstrap/cache
```

### Redis não responde

```bash
# Verificar Redis
sail exec redis redis-cli ping

# Limpar cache do Redis
sail artisan cache:clear
```

### Migrations falham

```bash
# Verificar status das migrations
sail artisan migrate:status

# Resetar banco completamente
sail artisan migrate:fresh --seed
```

## Boas Práticas

### Antes de Commitar

```bash
# Rodar testes
sail artisan test

# Verificar formatação
vendor/bin/pint --test
sail npm run lint

# Type check
sail npm run types
```

### Ao Criar Nova Feature

1. Crie branch a partir de `main`
2. Implemente a feature
3. Adicione testes
4. Rode `sail artisan test`
5. Formate código (`pint` + `npm run format`)
6. Commit com mensagem descritiva
7. Abra PR

### Ao Modificar Banco de Dados

1. Crie migration: `sail artisan make:migration`
2. Teste localmente: `sail artisan migrate`
3. Se multi-tenant: `sail artisan tenants:migrate`
4. Adicione rollback: método `down()`
5. Atualize seeders se necessário

## Referências

- [CLAUDE.md](../CLAUDE.md) - Guia completo do projeto
- [QUEUES.md](QUEUES.md) - Sistema de filas e Supervisor
- [PERMISSIONS.md](PERMISSIONS.md) - Sistema de permissões
- [SESSION-SECURITY.md](SESSION-SECURITY.md) - Segurança de sessões
- [MCP-WORKFLOW.md](MCP-WORKFLOW.md) - Workflow com ferramentas MCP
- [API-RESOURCES.md](API-RESOURCES.md) - Padrões de API Resources
