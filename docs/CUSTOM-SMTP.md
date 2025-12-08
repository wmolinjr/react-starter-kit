# Custom SMTP per Tenant (Enterprise Feature)

> **Status:** Implementado (desabilitado por padrão)
> **Requisito:** Plano Enterprise ou feature flag `custom_smtp`
> **Data:** 2025-12-06

## Visão Geral

Permite que tenants Enterprise configurem seu próprio servidor SMTP para envio de emails, substituindo o SMTP padrão da aplicação.

### Casos de Uso

- **White-label completo**: Emails enviados do próprio domínio do cliente
- **Compliance**: Empresas que precisam controlar o fluxo de emails
- **Deliverability**: Clientes com reputação de IP própria
- **Auditoria**: Logs de email no servidor do cliente

## Arquitetura

### Como Funciona

```
Tenant Settings                  MailConfigBootstrapper         Laravel Mail
+------------------+             +----------------------+       +------------------+
| smtp_host        | ---------> | $credentialsMap      | ----> | config('mail.mailers.smtp.host')
| smtp_port        |             | $mapPresets['smtp']  |       | Sobrescreve em runtime!
| smtp_username    |             | mapeia attrs tenant  |       |
| smtp_password    |             |                      |       |
| smtp_encryption  |             |                      |       |
+------------------+             +----------------------+       +------------------+
```

**Nota:** O `MailConfigBootstrapper` já possui presets padrão para SMTP (`$mapPresets['smtp']`) que mapeiam automaticamente host, port, username e password. Apenas `encryption` precisa ser adicionado manualmente.

### Configurações Disponíveis

| Setting | Laravel Config Key | Descrição |
|---------|-------------------|-----------|
| `smtp_host` | `mail.mailers.smtp.host` | Servidor SMTP (ex: smtp.gmail.com) |
| `smtp_port` | `mail.mailers.smtp.port` | Porta (587, 465, 25) |
| `smtp_username` | `mail.mailers.smtp.username` | Usuário de autenticação |
| `smtp_password` | `mail.mailers.smtp.password` | Senha (encriptada) |
| `smtp_encryption` | `mail.mailers.smtp.encryption` | tls, ssl, ou null |

## Implementação

### 1. Habilitar MailConfigBootstrapper

Em `config/tenancy.php`:

```php
'bootstrappers' => [
    Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
    Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
    // ... outros bootstrappers

    // Descomentar para habilitar SMTP customizado
    Stancl\Tenancy\Bootstrappers\MailConfigBootstrapper::class,
],
```

### 2. Configurar Mapeamento de Credenciais

O `MailConfigBootstrapper` já possui presets para SMTP que mapeiam automaticamente:
- `smtp_host` → `mail.mailers.smtp.host`
- `smtp_port` → `mail.mailers.smtp.port`
- `smtp_username` → `mail.mailers.smtp.username`
- `smtp_password` → `mail.mailers.smtp.password`

Para adicionar `encryption`, configure em `app/Providers/TenancyServiceProvider.php`:

```php
use Stancl\Tenancy\Bootstrappers\MailConfigBootstrapper;

public function boot(): void
{
    // ... outras configurações

    // Adicionar mapeamento para encryption (não está no preset padrão)
    MailConfigBootstrapper::$credentialsMap = [
        'mail.mailers.smtp.encryption' => 'smtp_encryption',
    ];
}
```

**Nota:** Os presets do SMTP são automaticamente mesclados com `$credentialsMap` no construtor do bootstrapper.

### 3. Adicionar ao TenantConfigKey Enum

Em `app/Enums/TenantConfigKey.php`:

```php
// SMTP (Enterprise)
case SMTP_HOST = 'smtp_host';
case SMTP_PORT = 'smtp_port';
case SMTP_USERNAME = 'smtp_username';
case SMTP_PASSWORD = 'smtp_password';
case SMTP_ENCRYPTION = 'smtp_encryption';

// Em configKeys():
self::SMTP_HOST => ['mail.mailers.smtp.host'],
self::SMTP_PORT => ['mail.mailers.smtp.port'],
self::SMTP_USERNAME => ['mail.mailers.smtp.username'],
self::SMTP_PASSWORD => ['mail.mailers.smtp.password'],
self::SMTP_ENCRYPTION => ['mail.mailers.smtp.encryption'],

// Em defaultValue():
self::SMTP_HOST => null,
self::SMTP_PORT => null,
self::SMTP_USERNAME => null,
self::SMTP_PASSWORD => null,
self::SMTP_ENCRYPTION => null,

// Em validationRules():
self::SMTP_HOST => ['nullable', 'string', 'max:255'],
self::SMTP_PORT => ['nullable', 'integer', 'min:1', 'max:65535'],
self::SMTP_USERNAME => ['nullable', 'string', 'max:255'],
self::SMTP_PASSWORD => ['nullable', 'string', 'max:255'],
self::SMTP_ENCRYPTION => ['nullable', 'string', 'in:tls,ssl'],
```

### 4. Encriptação de Credenciais

As credenciais SMTP são sensíveis e devem ser encriptadas:

```php
// No modelo Tenant, usar cast encrypted para smtp_password
protected $casts = [
    'settings' => 'array',
];

// Ou usar accessor/mutator específico
public function setSmtpPasswordAttribute($value)
{
    $this->updateSetting('config.smtp_password', encrypt($value));
}

public function getSmtpPasswordAttribute()
{
    $encrypted = $this->getSetting('config.smtp_password');
    return $encrypted ? decrypt($encrypted) : null;
}
```

## Segurança

### Checklist de Segurança

- [ ] Credenciais encriptadas em repouso (Laravel `encrypt()`)
- [ ] Credenciais nunca logadas (filtro em Telescope)
- [ ] Validação de host (prevenir SSRF)
- [ ] Rate limiting por tenant
- [ ] Auditoria de mudanças de configuração

### Validação de Host (Prevenir SSRF)

```php
// Em validationRules() ou FormRequest
'smtp_host' => [
    'nullable',
    'string',
    'max:255',
    function ($attribute, $value, $fail) {
        if ($value && !filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            $fail('O host SMTP deve ser um domínio válido.');
        }
        // Bloquear IPs internos
        if ($value && filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            if (filter_var($value, FILTER_VALIDATE_IP)) {
                $fail('IPs internos não são permitidos.');
            }
        }
    },
],
```

### Filtro no Telescope

Em `config/telescope.php`:

```php
'hide_request_parameters' => [
    '_token',
    'smtp_password',
    'smtp_username',
],
```

## UI Frontend

### Componente React (Enterprise)

```tsx
// resources/js/pages/tenant/admin/settings/smtp.tsx

{hasFeature('custom_smtp') && (
    <Card>
        <CardHeader>
            <CardTitle className="flex items-center gap-2">
                <Server className="h-5 w-5" />
                {t('tenant.config.smtp_settings')}
            </CardTitle>
            <CardDescription>
                {t('tenant.config.smtp_settings_description')}
            </CardDescription>
        </CardHeader>
        <CardContent className="grid gap-6 md:grid-cols-2">
            <div className="space-y-2">
                <Label htmlFor="smtp_host">{t('tenant.config.smtp_host')}</Label>
                <Input
                    id="smtp_host"
                    placeholder="smtp.example.com"
                    value={data.smtp_host}
                    onChange={(e) => setData('smtp_host', e.target.value)}
                />
            </div>
            <div className="space-y-2">
                <Label htmlFor="smtp_port">{t('tenant.config.smtp_port')}</Label>
                <Input
                    id="smtp_port"
                    type="number"
                    placeholder="587"
                    value={data.smtp_port}
                    onChange={(e) => setData('smtp_port', e.target.value)}
                />
            </div>
            <div className="space-y-2">
                <Label htmlFor="smtp_username">{t('tenant.config.smtp_username')}</Label>
                <Input
                    id="smtp_username"
                    placeholder="user@example.com"
                    value={data.smtp_username}
                    onChange={(e) => setData('smtp_username', e.target.value)}
                />
            </div>
            <div className="space-y-2">
                <Label htmlFor="smtp_password">{t('tenant.config.smtp_password')}</Label>
                <Input
                    id="smtp_password"
                    type="password"
                    placeholder="••••••••"
                    value={data.smtp_password}
                    onChange={(e) => setData('smtp_password', e.target.value)}
                />
            </div>
            <div className="space-y-2">
                <Label htmlFor="smtp_encryption">{t('tenant.config.smtp_encryption')}</Label>
                <Select
                    value={data.smtp_encryption}
                    onValueChange={(value) => setData('smtp_encryption', value)}
                >
                    <SelectTrigger>
                        <SelectValue placeholder="Selecione..." />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="tls">TLS</SelectItem>
                        <SelectItem value="ssl">SSL</SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </CardContent>
    </Card>
)}
```

## Testes

### Test de Envio

```php
// tests/Feature/CustomSmtpTest.php

public function test_tenant_can_send_email_with_custom_smtp(): void
{
    $tenant = Tenant::factory()->create([
        'settings' => [
            'config' => [
                'smtp_host' => 'smtp.mailtrap.io',
                'smtp_port' => 587,
                'smtp_username' => 'test',
                'smtp_password' => encrypt('test'),
                'smtp_encryption' => 'tls',
            ],
        ],
    ]);

    $tenant->run(function () {
        // Verificar que config foi sobrescrito
        $this->assertEquals('smtp.mailtrap.io', config('mail.mailers.smtp.host'));

        // Verificar envio (com mock)
        Mail::fake();
        Mail::to('test@example.com')->send(new TestMail());
        Mail::assertSent(TestMail::class);
    });
}
```

## Rollout

### Feature Flag

```php
// Em PlanFeature enum ou database
'custom_smtp' => [
    'starter' => false,
    'professional' => false,
    'enterprise' => true,
],
```

### Migration para Habilitar

```php
// Para habilitar para tenant específico
$tenant->enableFeature('custom_smtp');

// Ou via plan upgrade
$tenant->plan->update(['features->custom_smtp' => true]);
```

## Troubleshooting

### Problemas Comuns

| Problema | Causa | Solução |
|----------|-------|---------|
| "Connection refused" | Porta bloqueada | Verificar firewall do servidor |
| "Authentication failed" | Credenciais incorretas | Verificar usuário/senha |
| "Certificate verify failed" | SSL inválido | Usar `smtp_encryption: null` ou verificar certificado |
| Emails não chegam | Spam filter | Verificar SPF/DKIM/DMARC |

### Logs de Debug

```php
// Habilitar logging de mail em .env
MAIL_LOG_CHANNEL=stack

// Verificar via Telescope
// http://app.test/telescope/mail
```

## Referências

- [Tenancy v4 - MailConfigBootstrapper](https://v4.tenancyforlaravel.com/misc)
- [Laravel Mail Configuration](https://laravel.com/docs/mail)
- [SPF/DKIM Setup Guide](https://www.cloudflare.com/learning/email-security/dmarc-dkim-spf/)
