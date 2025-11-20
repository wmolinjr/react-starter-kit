# MediaLibrary Queue Integration (Spatie + Stancl Tenancy)

Este projeto usa **Spatie MediaLibrary** para gerenciar uploads e conversões de imagens, integrado com **Stancl Tenancy** para isolamento multi-tenant completo.

## Arquitetura de Integração

### Como Funciona a Integração

**1. QueueTenancyBootstrapper** (config/tenancy.php:34)

O `QueueTenancyBootstrapper` garante que jobs de conversão de imagem executam no contexto do tenant correto:

```php
// Job serialization (quando imagem é enviada)
Upload → MediaLibrary cria Media record com tenant_id
      → Queue PerformConversionsJob($media)
      → SerializesModels serializa Media model
      → QueueTenancyBootstrapper adiciona tenant_id ao payload do job

// Job deserialization (quando worker processa)
Worker pega job da queue
      → QueueTenancyBootstrapper inicializa tenant context (tenant_id)
      → SerializesModels deserializa Media → busca com tenant_id scope
      → Job executa conversão → salva em tenants/{tenant_id}/media/{id}/
```

**2. Media Model com BelongsToTenant** (app/Models/Media.php:8)

```php
class Media extends BaseMedia
{
    use BelongsToTenant;  // ✅ Automatic tenant scoping

    // Media records sempre têm tenant_id
    // Queries automáticas filtram por tenant atual
}
```

**3. TenantPathGenerator** (app/Support/TenantPathGenerator.php:17)

Garante que arquivos e conversões são salvos em paths isolados por tenant:

```php
public function getPath(Media $media): string
{
    $tenantId = $media->tenant_id ?? 'global';
    return "tenants/{$tenantId}/media/{$media->id}/";
}

public function getPathForConversions(Media $media): string
{
    return $this->getPath($media) . 'conversions/';
}
```

**Exemplo de Paths**:
```
storage/app/tenants/
├── 1/
│   └── media/
│       └── 123/
│           ├── photo.jpg (original)
│           └── conversions/
│               └── thumb-photo.jpg (300x300)
├── 2/
│   └── media/
│       └── 456/
│           ├── document.pdf
│           └── conversions/
│               └── preview-document.jpg
```

## Componentes da Integração

| Componente | Arquivo | Função |
|------------|---------|---------|
| **QueueTenancyBootstrapper** | config/tenancy.php:34 | Injeta tenant_id em jobs, inicializa contexto |
| **Media Model** | app/Models/Media.php | Model customizado com BelongsToTenant trait |
| **TenantPathGenerator** | app/Support/TenantPathGenerator.php | Gera paths isolados por tenant |
| **Project Model** | app/Models/Project.php:52-64 | Define media collections e conversions |
| **MediaLibrary Config** | config/media-library.php | Configuração global (queue, path generator) |

## Configuração MediaLibrary

**config/media-library.php** (principais configurações):

```php
return [
    // Queue conversions para performance (async)
    'queue_connection_name' => env('QUEUE_CONNECTION', 'sync'),
    'queue_conversions_by_default' => env('QUEUE_CONVERSIONS_BY_DEFAULT', true),

    // Path generator customizado (tenant-isolated)
    'path_generator' => App\Support\TenantPathGenerator::class,

    // Media model customizado (com BelongsToTenant)
    'media_model' => App\Models\Media::class,
];
```

## Exemplo de Uso: Project com Imagens

**app/Models/Project.php:52-64**:

```php
public function registerMediaCollections(): void
{
    $this->addMediaCollection('attachments')
        ->useDisk('tenant_uploads');

    $this->addMediaCollection('images')
        ->useDisk('tenant_uploads')
        ->registerMediaConversions(function () {
            $this->addMediaConversion('thumb')
                ->width(300)
                ->height(300);  // ← Conversion queued automatically
        });
}
```

**Upload e Conversão**:

```php
// Controller (app/Http/Controllers/Tenant/ProjectController.php:169)
$project->addMediaFromRequest('file')
    ->toMediaCollection('images');

// Fluxo interno:
// 1. MediaLibrary salva original em: tenants/1/media/123/photo.jpg
// 2. Queue PerformConversionsJob com tenant_id=1
// 3. Worker processa no contexto de Tenant 1
// 4. Salva thumb em: tenants/1/media/123/conversions/thumb-photo.jpg
```

## Segurança Multi-Tenant

**✅ Isolamento Garantido Por**:

1. **BelongsToTenant Trait**: Media queries automáticas filtram por tenant_id
2. **TenantPathGenerator**: Arquivos físicos em paths separados por tenant
3. **QueueTenancyBootstrapper**: Jobs executam no contexto correto
4. **Validações no Controller** (ProjectController.php:187-189):
   ```php
   // Verificar se media pertence ao tenant atual
   if ($project->tenant_id !== current_tenant_id()) {
       abort(404);
   }
   ```

## Queue Configuration

**Importante**: Queue connection deve usar conexão separada (não prefixada):

```php
// config/queue.php:69
'redis' => [
    'driver' => 'redis',
    'connection' => 'queue',  // ✅ Separate connection, NOT 'default'
],

// config/database.php:179
'queue' => [
    'database' => env('REDIS_QUEUE_DB', '2'),  // ✅ DB 2 (isolated)
],

// config/tenancy.php:176-179
'redis' => [
    'prefixed_connections' => [
        'default',  // ✅ Sessions + direct Redis
        // 'queue' is intentionally NOT here
    ],
],
```

**Por que queue não pode ser prefixada**:

```
❌ COM prefixo:
Queue job key: tenant_1:queues:default:job123
Worker procura: queues:default:job123
Resultado: Job NUNCA processado (key mismatch)

✅ SEM prefixo:
Queue job key: queues:default:job123
Worker procura: queues:default:job123
Resultado: Job processado, tenant context inicializado via QueueTenancyBootstrapper
```

## Testes

**tests/Feature/MediaLibraryQueueTenancyTest.php** (10 tests):

1. ✅ Media model tem tenant_id e usa BelongsToTenant
2. ✅ Arquivos salvos em paths tenant-isolated
3. ✅ Conversion jobs queued com tenant context
4. ✅ Conversions salvas no path correto do tenant
5. ✅ Media isolada entre tenants (Tenant 2 não vê Tenant 1)
6. ✅ QueueTenancyBootstrapper está habilitado
7. ✅ MediaLibrary configurado para queue conversions
8. ✅ TenantPathGenerator configurado
9. ✅ Custom Media model configurado
10. ✅ Project media collections configuradas

**Rodar Testes**:

```bash
# Com Sail (recomendado - PostgreSQL)
sail artisan test --filter=MediaLibraryQueueTenancyTest
```

**⚠️ Limitação Conhecida**: PHP 8.4 + SQLite :memory: + RefreshDatabase tem problema com nested transactions. Use Sail (PostgreSQL) para rodar os testes.

## Best Practices

1. **Sempre use BelongsToTenant** em models que armazenam media
2. **Sempre configure TenantPathGenerator** para isolamento de arquivos
3. **Queue connection separada** (nunca prefixar com tenant_id)
4. **Valide tenant_id** no controller antes de retornar media
5. **Use Telescope MCP** para debug (verificar jobs queued, paths, exceptions)

## Verificação com Telescope MCP

Após upload de media, sempre verificar:

```bash
# 1. Jobs queued (verificar PerformConversionsJob)
Telescope → Jobs → Ver payload com tenant_id

# 2. Queries (verificar se Media foi salva com tenant_id)
Telescope → Queries → INSERT into media (tenant_id, ...)

# 3. Exceptions (garantir sem erros)
Telescope → Exceptions → Vazio (sem erros)
```
