# 99 - Troubleshooting

## Problemas Comuns e Soluções

### 1. "No tenant found for domain"

**Sintoma:** Ao acessar `tenant.myapp.test`, recebe erro 404 ou "No tenant found".

**Causas:**
- Domínio não existe na tabela `domains`
- Tenant não está inicializado
- Central domain está configurado incorretamente

**Solução:**

```bash
# Verificar se domain existe
php artisan tinker
>>> \App\Models\Domain::where('domain', 'tenant.myapp.test')->first()

# Se não existe, criar:
>>> $tenant = \App\Models\Tenant::where('slug', 'tenant')->first();
>>> $tenant->domains()->create(['domain' => 'tenant.myapp.test', 'is_primary' => true]);

# Verificar config central_domains
>>> config('tenancy.central_domains')
```

---

### 2. Subdomain não resolve localmente

**Sintoma:** `tenant.myapp.test` não carrega, "site can't be reached".

**Solução:**

```bash
# Opção 1: Herd/Valet
valet links

# Opção 2: /etc/hosts
sudo nano /etc/hosts
# Adicionar:
127.0.0.1 tenant.myapp.test

# Opção 3: dnsmasq (macOS)
sudo brew install dnsmasq
echo 'address=/.test/127.0.0.1' > /usr/local/etc/dnsmasq.conf
sudo brew services start dnsmasq

# Limpar cache DNS
sudo dscacheutil -flushcache
sudo killall -HUP mDNSResponder
```

---

### 3. Queries retornam dados de outro tenant

**Sintoma:** Usuário vê dados de outros tenants.

**Causas:**
- Global scope não está sendo aplicado
- Model não tem `BelongsToTenant` trait
- Usando `withoutGlobalScope()` incorretamente

**Solução:**

```php
// Verificar se model tem trait
class Project extends Model
{
    use BelongsToTenant; // ✅ Adicionar se faltando
}

// Verificar se scope está ativo
Project::query()->toSql();
// Deve incluir: WHERE tenant_id = X

// Telescope: verificar queries
// Todas devem ter WHERE tenant_id =
```

---

### 4. Tenant context não está inicializado

**Sintoma:** `tenant()` retorna `null` ou erro "No tenant initialized".

**Causas:**
- Middleware `InitializeTenancyByDomain` não está aplicado
- Request não está passando pelo middleware correto
- Domínio está na lista de `central_domains`

**Solução:**

```php
// routes/tenant.php
Route::middleware([
    'web',
    InitializeTenancyByDomain::class, // ✅ Verificar se está aqui
])->group(function () {
    // rotas
});

// Verificar no controller
if (!tenancy()->initialized) {
    dd('Tenant not initialized!');
}

// Debug middleware
Route::get('/debug', function () {
    return [
        'tenant_initialized' => tenancy()->initialized,
        'tenant_id' => tenant('id'),
        'domain' => request()->getHost(),
    ];
});
```

---

### 5. "Subscription not found" mesmo após checkout

**Sintoma:** Cashier não encontra subscription após checkout bem-sucedido.

**Causas:**
- Webhook não foi processado
- `STRIPE_WEBHOOK_SECRET` incorreto
- Stripe não consegue alcançar webhook URL

**Solução:**

```bash
# 1. Verificar webhook secret
# .env
STRIPE_WEBHOOK_SECRET=whsec_...

# 2. Testar localmente com Stripe CLI
stripe listen --forward-to http://myapp.test/stripe/webhook

# 3. Trigger evento teste
stripe trigger customer.subscription.created

# 4. Verificar logs
tail -f storage/logs/laravel.log | grep -i stripe

# 5. Manualmente criar subscription (temporário)
php artisan tinker
>>> $tenant = Tenant::find(1);
>>> $tenant->subscriptions()->create([
...     'type' => 'default',
...     'stripe_id' => 'sub_xxx',
...     'stripe_status' => 'active',
...     'stripe_price' => 'price_xxx',
... ]);
```

---

### 6. Queue jobs perdem tenant context

**Sintoma:** Jobs processados sem tenant context, queries retornam vazio.

**Causas:**
- Job não implementa `ShouldBeUnique` ou `BelongsToTenant`
- Tenant context não está sendo restaurado

**Solução:**

```php
use Stancl\Tenancy\Contracts\SyncMaster;

class ProcessInvoice implements ShouldQueue, SyncMaster
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tenant; // ✅ Adicionar propriedade

    public function __construct(public Invoice $invoice)
    {
        $this->tenant = tenancy()->initialized
            ? Tenant::find(tenant('id'))
            : $invoice->tenant;
    }

    public function handle()
    {
        if ($this->tenant) {
            tenancy()->initialize($this->tenant);
        }

        // Processar invoice
    }
}
```

---

### 7. N+1 Queries em tenant-scoped models

**Sintoma:** Telescope mostra muitas queries duplicadas.

**Solução:**

```php
// ❌ N+1 Problem
$projects = Project::all();
foreach ($projects as $project) {
    echo $project->user->name; // Query por iteration
}

// ✅ Solução: Eager Loading
$projects = Project::with('user')->get();
foreach ($projects as $project) {
    echo $project->user->name; // Sem query extra
}

// Telescope: verificar
// Queries → Projects → Query count deve ser 2 (projects + users)
```

---

### 8. "This action is unauthorized" mesmo sendo owner

**Sintoma:** Owner recebe 403 em rotas que deveria ter acesso.

**Causas:**
- Gate não está registrado corretamente
- User role não está sendo detectado
- Middleware está aplicado na ordem errada

**Solução:**

```php
// Verificar role do user
auth()->user()->currentTenantRole(); // Deve retornar 'owner'

// Verificar gate
Gate::allows('manage-billing'); // Deve retornar true

// Debug no AuthServiceProvider
Gate::before(function (User $user, string $ability) {
    \Log::info('Gate check', [
        'user_id' => $user->id,
        'ability' => $ability,
        'role' => $user->currentTenantRole(),
        'tenant_id' => tenant('id'),
    ]);
});

// Verificar ordem de middleware
// InitializeTenancyByDomain DEVE vir ANTES de auth checks
```

---

### 9. Cashier migrations falham

**Sintoma:** `php artisan migrate` falha ao rodar migrations do Cashier.

**Solução:**

```bash
# 1. Verificar que publicou migrations
php artisan vendor:publish --tag="cashier-migrations"

# 2. Se já rodou migrations, adicionar tenant_id depois
php artisan make:migration add_tenant_id_to_subscriptions_table

# 3. Rodar migrations
php artisan migrate

# 4. Se falhar com foreign key error
# Verificar que tabela tenants existe ANTES
# Migration subscriptions deve rodar DEPOIS
```

---

### 10. Performance lenta com muitos tenants

**Sintoma:** Queries lentas, timeout em requisições.

**Solução:**

```bash
# 1. Verificar indexes
# TODAS as tabelas tenant-scoped DEVEM ter index em tenant_id

# 2. Adicionar indexes faltando
php artisan make:migration add_missing_tenant_indexes

# Migration:
Schema::table('projects', function (Blueprint $table) {
    $table->index('tenant_id');
    $table->index(['tenant_id', 'created_at']); // Composite para ordenação
});

# 3. Analisar queries no Telescope
# Queries → Slow Queries → Verificar se está usando index

# 4. Otimizar queries
# Usar select() para limitar colunas
# Usar chunk() para grandes datasets
Project::select('id', 'name')->chunk(100, function ($projects) {
    // Processar
});

# 5. Cache agressivo
Cache::remember("tenant.{$tenant->id}.stats", 3600, function () {
    return Project::count();
});
```

---

## Debug Checklist

Quando algo não funciona, siga esta ordem:

1. ✅ **Logs:** `tail -f storage/logs/laravel.log`
2. ✅ **Telescope:** `/telescope` → Requests, Queries, Exceptions
3. ✅ **Tinker:** `php artisan tinker` → Testar queries manualmente
4. ✅ **Config Cache:** `php artisan config:clear` → Limpar cache
5. ✅ **Route Cache:** `php artisan route:clear` → Limpar rotas
6. ✅ **Environment:** Verificar `.env` variáveis corretas
7. ✅ **Migrations:** `php artisan migrate:status` → Verificar rodou tudo
8. ✅ **Dependencies:** `composer dump-autoload` → Atualizar autoload

---

## Comandos Úteis de Debug

```bash
# Ver tenant atual
php artisan tinker
>>> tenancy()->initialized
>>> tenant('id')
>>> tenant('name')

# Ver todos os tenants
>>> \App\Models\Tenant::with('domains')->get()

# Ver users de um tenant
>>> \App\Models\Tenant::find(1)->users

# Inicializar tenant manualmente
>>> tenancy()->initialize(\App\Models\Tenant::find(1))

# Ver config
>>> config('tenancy.central_domains')

# Limpar tudo
php artisan optimize:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Ver rotas tenant
php artisan route:list --path=tenant
```

---

## Recursos de Suporte

- **archtechx/tenancy docs:** https://tenancyforlaravel.com/docs/v4
- **Laravel docs:** https://laravel.com/docs/12.x
- **Cashier docs:** https://laravel.com/docs/12.x/cashier
- **Spatie MediaLibrary:** https://spatie.be/docs/laravel-medialibrary

**Comunidades:**
- Laravel Discord
- Stack Overflow (tag: laravel-tenancy)
- GitHub Issues dos pacotes

---

**Versão:** 1.0
**Última atualização:** 2025-11-19
