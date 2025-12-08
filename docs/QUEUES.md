# Sistema de Filas (Queues)

Este documento descreve a arquitetura de filas do sistema e como configurar workers para desenvolvimento e produção.

## Estrutura de Filas

O sistema usa múltiplas filas para separar diferentes tipos de jobs por prioridade e características:

| Fila | Prioridade | Descrição | Timeout | Tries |
|------|------------|-----------|---------|-------|
| `high` | 1 (máxima) | Jobs críticos: emails, notificações, webhooks | 60s | 3 |
| `default` | 2 | Jobs padrão: tenant seeding, permission sync | 300s | 3 |
| `federation` | 3 | Sincronização de usuários entre tenants | 600s | 5 |
| `media` | 4 (mínima) | Conversões de imagem (MediaLibrary) | 900s | 3 |

## Jobs por Fila

### Fila `high`
- `App\Mail\Tenant\TeamInvitation` - Convites de equipe
- Notificações críticas
- Webhooks do Stripe (processamento rápido)

### Fila `default`
- `App\Jobs\Central\SeedTenantDatabase` - Seed inicial do tenant
- `App\Jobs\Central\SyncTenantPermissions` - Sincronização de permissões
- Jobs gerais sem fila específica

### Fila `federation`
- `SyncUserToFederatedTenantsJob` - Sync de usuário para tenants federados
- `PropagatePasswordChangeJob` - Propagação de mudança de senha
- `PropagateTwoFactorChangeJob` - Propagação de 2FA
- `SyncAllUsersToTenantJob` - Sync em massa
- `RetryFailedSyncsJob` - Retry de falhas

### Fila `media`
- `Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob` - Conversões de imagem
- Processamento de thumbnails
- Otimização de imagens

## Configuração

### Desenvolvimento (Worker Único)

Para desenvolvimento, use um único worker que processa todas as filas em ordem de prioridade:

```bash
# Comando completo
sail artisan queue:work redis --queue=high,default,federation,media --tries=3 --timeout=300

# Via script (recomendado)
./bin/dev-start.sh
```

O script `bin/dev-start.sh` já está configurado para processar todas as filas.

### Produção (Workers Dedicados)

Em produção, use workers separados para cada fila com Supervisor:

```bash
# Instalar Supervisor
sudo apt-get install supervisor
```

#### Configuração do Supervisor

Crie o arquivo `/etc/supervisor/conf.d/laravel-queues.conf`:

```ini
; =============================================================================
; Queue Workers for Laravel Application
; =============================================================================
; Workers process queues in isolation for better resource management.
; Each worker has its own timeout and retry configuration.
;
; Priority order: high > default > federation > media
; =============================================================================

; -----------------------------------------------------------------------------
; HIGH PRIORITY QUEUE
; Critical jobs: emails, notifications, webhooks
; -----------------------------------------------------------------------------
[program:queue-high]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work redis --queue=high --sleep=3 --tries=3 --timeout=60 --max-jobs=1000 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/queue-high.log
stdout_logfile_maxbytes=10MB
stopwaitsecs=3600

; -----------------------------------------------------------------------------
; DEFAULT QUEUE
; Standard jobs: tenant seeding, permission sync
; -----------------------------------------------------------------------------
[program:queue-default]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work redis --queue=default --sleep=3 --tries=3 --timeout=300 --max-jobs=500 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/queue-default.log
stdout_logfile_maxbytes=10MB
stopwaitsecs=3600

; -----------------------------------------------------------------------------
; FEDERATION QUEUE
; User sync across tenants (can be slow)
; -----------------------------------------------------------------------------
[program:queue-federation]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work redis --queue=federation --sleep=3 --tries=5 --timeout=600 --max-jobs=200 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/queue-federation.log
stdout_logfile_maxbytes=10MB
stopwaitsecs=3600

; -----------------------------------------------------------------------------
; MEDIA QUEUE
; MediaLibrary conversions (CPU intensive, long running)
; -----------------------------------------------------------------------------
[program:queue-media]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work redis --queue=media --sleep=3 --tries=3 --timeout=900 --max-jobs=100 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/queue-media.log
stdout_logfile_maxbytes=10MB
stopwaitsecs=3600

; -----------------------------------------------------------------------------
; GROUP: All Queue Workers
; -----------------------------------------------------------------------------
[group:laravel-queues]
programs=queue-high,queue-default,queue-federation,queue-media
priority=999
```

#### Comandos do Supervisor

```bash
# Recarregar configuração
sudo supervisorctl reread
sudo supervisorctl update

# Iniciar todos os workers
sudo supervisorctl start laravel-queues:*

# Parar todos os workers
sudo supervisorctl stop laravel-queues:*

# Reiniciar todos os workers
sudo supervisorctl restart laravel-queues:*

# Ver status
sudo supervisorctl status

# Ver logs em tempo real
tail -f /var/www/html/storage/logs/queue-high.log
```

## Monitoramento

### Via Artisan

```bash
# Ver jobs em tempo real
sail artisan queue:monitor high,default,federation,media

# Ver jobs falhos
sail artisan queue:failed

# Reprocessar job falho específico
sail artisan queue:retry <job-id>

# Reprocessar todos os falhos
sail artisan queue:retry all

# Limpar jobs falhos
sail artisan queue:flush
```

### Via Telescope

Acesse http://app.test/telescope/jobs para:
- Ver jobs processados
- Ver jobs falhos com stack traces
- Monitorar tempo de execução
- Identificar gargalos

## Laravel Horizon (Recomendado)

O projeto já vem com Laravel Horizon configurado para gerenciamento visual de filas.

### Dashboard

Acesse http://app.test/horizon para:
- Dashboard em tempo real
- Métricas de throughput e tempo de execução
- Visualização de jobs falhos com stack traces
- Tags automáticas por tenant
- Retry de jobs via interface

### Desenvolvimento

```bash
# Iniciar Horizon (substitui queue:work)
sail artisan horizon

# Via script automatizado
./bin/dev-start.sh --horizon

# Modo completo (todos os serviços)
./bin/dev-start.sh --full
```

### Produção com Supervisor

Crie o arquivo `/etc/supervisor/conf.d/horizon.conf`:

```ini
[program:horizon]
process_name=%(program_name)s
command=php /var/www/html/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/horizon.log
stopwaitsecs=3600
```

```bash
# Recarregar e iniciar
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start horizon
```

### Configuração

O Horizon está configurado em `config/horizon.php` com 4 supervisors:

| Supervisor | Fila | Processos | Timeout | Memória |
|------------|------|-----------|---------|---------|
| supervisor-high | high | 1-3 (prod: 2-5) | 60s | 128MB |
| supervisor-default | default | 1-3 (prod: 2-5) | 300s | 128MB |
| supervisor-federation | federation | 1-2 (prod: 1-3) | 600s | 256MB |
| supervisor-media | media | 1-2 (prod: 1-3) | 900s | 512MB |

### Acesso em Produção

Em produção, o Horizon só é acessível por super admins (`is_super_admin = true`).

### Comandos Úteis

```bash
# Pausar processamento
sail artisan horizon:pause

# Continuar processamento
sail artisan horizon:continue

# Terminar Horizon graciosamente
sail artisan horizon:terminate

# Ver status
sail artisan horizon:status

# Limpar jobs antigos
sail artisan horizon:clear
```

## Boas Práticas

### 1. Definir Fila no Job

```php
class MyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('high'); // Definir fila específica
    }
}
```

### 2. Definir Timeout Apropriado

```php
class LongRunningJob implements ShouldQueue
{
    public int $timeout = 600; // 10 minutos

    public int $tries = 3;

    public int $maxExceptions = 2;
}
```

### 3. Retry com Backoff

```php
class RetryableJob implements ShouldQueue
{
    public array $backoff = [60, 120, 300]; // 1min, 2min, 5min

    public function retryUntil(): DateTime
    {
        return now()->addHours(24);
    }
}
```

### 4. Handling de Falhas

```php
class ImportantJob implements ShouldQueue
{
    public function failed(Throwable $exception): void
    {
        // Notificar admin, log, etc.
        Log::error('Job failed', [
            'exception' => $exception->getMessage(),
            'job' => get_class($this),
        ]);
    }
}
```

### 5. Rate Limiting

```php
use Illuminate\Support\Facades\RateLimiter;

class RateLimitedJob implements ShouldQueue
{
    public function handle(): void
    {
        if (RateLimiter::tooManyAttempts('api-calls', 100)) {
            $this->release(60); // Retry em 60 segundos
            return;
        }

        RateLimiter::hit('api-calls');

        // Process job...
    }
}
```

## Troubleshooting

### Jobs não estão sendo processados

1. Verificar se o worker está rodando:
   ```bash
   ps aux | grep queue:work
   ```

2. Verificar conexão Redis:
   ```bash
   sail exec redis redis-cli ping
   ```

3. Verificar filas:
   ```bash
   sail artisan queue:monitor high,default,federation,media
   ```

### Jobs falhando repetidamente

1. Ver detalhes do erro:
   ```bash
   sail artisan queue:failed
   ```

2. Verificar logs:
   ```bash
   tail -f storage/logs/laravel.log
   ```

3. Verificar Telescope em http://app.test/telescope/jobs

### Filas acumulando

1. Aumentar número de workers (`numprocs` no Supervisor)
2. Verificar se há jobs lentos bloqueando
3. Considerar separar jobs pesados em filas dedicadas

## Referências

- [Laravel Queues](https://laravel.com/docs/queues)
- [Laravel Horizon](https://laravel.com/docs/horizon)
- [Supervisor Documentation](http://supervisord.org/)
- [Redis Queue Driver](https://laravel.com/docs/queues#redis)
