# 15 - Security Checklist

## Tenant Isolation

### ✅ Checklist Crítico

- [ ] **Global Scopes:** Todos os models tenant-scoped têm `BelongsToTenant` trait
- [ ] **Indexes:** Todas as tabelas tenant-scoped têm index em `tenant_id`
- [ ] **Controller Validation:** Verificar `tenant_id` em updates/deletes
- [ ] **API Routes:** Tenant context inicializado em rotas de API
- [ ] **Queue Jobs:** Jobs incluem `tenant_id` e restauram contexto
- [ ] **File Storage:** Arquivos isolados por tenant (disk ou S3 prefix)
- [ ] **Cache Keys:** Cache inclui `tenant_id` como prefixo
- [ ] **Database Queries:** NUNCA usar `withoutGlobalScope` sem verificar super admin

### Verificação Automática

```php
// tests/Feature/TenantIsolationTest.php

/** @test */
public function users_cannot_access_data_from_other_tenants()
{
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();

    $user1 = User::factory()->create();
    $tenant1->users()->attach($user1, ['role' => 'owner', 'joined_at' => now()]);

    $project2 = Project::factory()->create(['tenant_id' => $tenant2->id]);

    tenancy()->initialize($tenant1);
    $this->actingAs($user1);

    // Verificar que não aparece em queries
    $this->assertEquals(0, Project::count());

    // Verificar que não pode acessar diretamente
    $this->get("/projects/{$project2->id}")->assertNotFound();
}
```

---

## Authentication & Authorization

- [ ] **Two-Factor Auth:** Disponível via Fortify (opcional por tenant)
- [ ] **Email Verification:** Ativa (`implements MustVerifyEmail`)
- [ ] **Password Policy:** Mínimo 8 caracteres, validação no frontend
- [ ] **Session Security:** `SESSION_SECURE_COOKIE=true` em produção
- [ ] **CSRF Protection:** Inertia automático via middleware
- [ ] **Rate Limiting:** Aplicado em login, register, API
- [ ] **Password Reset:** Tokens expiram em 60 minutos

### Rate Limiting

```php
// app/Http/Kernel.php

protected $middlewareGroups = [
    'api' => [
        'throttle:60,1', // 60 requests por minuto
        'auth:sanctum',
    ],
];

// Customizado por tenant
RateLimiter::for('tenant-api', function (Request $request) {
    return Limit::perMinute(100)->by(
        $request->user()?->id . '@' . current_tenant_id()
    );
});
```

---

## Input Validation

- [ ] **Request Validation:** SEMPRE validar inputs via `$request->validate()`
- [ ] **XSS Prevention:** Laravel Blade escapa automático, React também
- [ ] **SQL Injection:** NUNCA usar raw queries, sempre Eloquent ou bindings
- [ ] **File Upload:** Validar tipo, tamanho, verificar MIME type real
- [ ] **Mass Assignment:** `$fillable` ou `$guarded` em todos os models

### Exemplo: Validação Robusta

```php
$request->validate([
    'email' => ['required', 'email:rfc,dns', 'max:255'],
    'file' => ['required', 'file', 'mimes:pdf,docx', 'max:10240'], // 10MB
    'url' => ['nullable', 'url', 'active_url'],
    'domain' => ['nullable', 'regex:/^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,6}$/'],
]);
```

---

## Sensitive Data Protection

- [ ] **Environment Variables:** NUNCA commit `.env` no git
- [ ] **Secrets Encryption:** `php artisan config:cache` em produção
- [ ] **Stripe Keys:** Usar test keys em dev, live keys em prod
- [ ] **Database Backups:** Encrypted backups diários
- [ ] **Logs:** Não logar senhas, tokens, cartões
- [ ] **Error Pages:** Não mostrar stack traces em produção (`APP_DEBUG=false`)

### Filtering Sensitive Data (Telescope)

```php
// config/telescope.php

'ignore_paths' => [
    'nova-api*',
],

'ignore_commands' => [
    //
],

'watchers' => [
    Watchers\RequestWatcher::class => [
        'enabled' => env('TELESCOPE_ENABLED', true),
        'size_limit' => env('TELESCOPE_RESPONSE_SIZE_LIMIT', 64),

        // Filtrar headers sensíveis
        'ignore_headers' => [
            'authorization',
            'cookie',
            'set-cookie',
            'x-csrf-token',
        ],
    ],
],
```

---

## API Security

- [ ] **Authentication:** Sanctum tokens tenant-scoped
- [ ] **Authorization:** Gates/policies verificam tenant ownership
- [ ] **Rate Limiting:** 100 req/min por tenant
- [ ] **CORS:** Configurado apenas para domínios permitidos
- [ ] **API Versioning:** `/api/v1/...` para breaking changes
- [ ] **Webhook Signatures:** Validar Stripe webhook signatures

```php
// config/cors.php

'allowed_origins' => [
    config('app.url'),
    'https://*.myapp.com',
],

'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],

'allowed_headers' => ['*'],

'exposed_headers' => [],

'max_age' => 0,

'supports_credentials' => true,
```

---

## Infrastructure Security

- [ ] **HTTPS Only:** Force HTTPS em produção
- [ ] **Security Headers:** CSP, X-Frame-Options, etc. (nginx config)
- [ ] **Firewall:** Apenas portas 80, 443 abertas
- [ ] **Database:** Acesso restrito (não público)
- [ ] **Redis:** Password-protected
- [ ] **SSH:** Chaves públicas, disable password auth
- [ ] **Updates:** Sistema operacional e packages atualizados

### Security Headers (Nginx)

```nginx
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "no-referrer-when-downgrade" always;
add_header Content-Security-Policy "default-src 'self' https: data: 'unsafe-inline' 'unsafe-eval';" always;
```

---

## Monitoring & Incident Response

- [ ] **Error Tracking:** Sentry ou Bugsnag configurado
- [ ] **Uptime Monitoring:** Oh Dear, Pingdom, etc.
- [ ] **Log Management:** Centralized logging (Papertrail, Logtail)
- [ ] **Backups:** Automatizados e testados (recovery drill)
- [ ] **Incident Plan:** Documentado e time treinado

---

## Compliance

- [ ] **GDPR:** Data export, data deletion, privacy policy
- [ ] **Terms of Service:** Atualizado e aceito no sign up
- [ ] **Cookie Consent:** Banner de cookies (se aplicável)
- [ ] **Data Retention:** Política de retenção documentada
- [ ] **Audit Logs:** Spatie Activity Log para ações críticas

---

## Checklist Final Antes do Launch

```bash
# 1. Environment
APP_DEBUG=false
APP_ENV=production

# 2. SSL
curl -I https://myapp.com | grep -i strict

# 3. Headers
curl -I https://myapp.com | grep -i x-frame

# 4. Database
# Verificar que não há database pública

# 5. Backups
php artisan backup:run

# 6. Tests
php artisan test

# 7. Performance
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 8. Monitoring
# Verificar Sentry, logs, uptime monitor
```

---

**Versão:** 1.0
**Última atualização:** 2025-11-19
