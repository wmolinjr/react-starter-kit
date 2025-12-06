# MediaLibrary Queue Integration (Spatie + Stancl Tenancy)

Este projeto usa **Spatie MediaLibrary** para gerenciar uploads e conversões de imagens, integrado com **Stancl Tenancy** para isolamento multi-tenant completo.

## Arquitetura de Integração

### Multi-Database Tenancy

**IMPORTANTE**: Este projeto usa **multi-database tenancy** - cada tenant tem seu próprio banco de dados. A tabela `media` existe em cada banco de tenant, não há coluna `tenant_id`.

### Como Funciona a Integração

**1. QueueTenancyBootstrapper** (config/tenancy.php:34)

O `QueueTenancyBootstrapper` garante que jobs de conversão de imagem executam no contexto do tenant correto:

```php
// Job serialization (quando imagem é enviada)
Upload → MediaLibrary cria Media record no banco do tenant
      → Queue PerformConversionsJob($media)
      → SerializesModels serializa Media model
      → QueueTenancyBootstrapper adiciona tenant_id ao payload do job

// Job deserialization (quando worker processa)
Worker pega job da queue
      → QueueTenancyBootstrapper inicializa tenant context
      → DatabaseTenancyBootstrapper conecta ao banco do tenant
      → SerializesModels deserializa Media do banco correto
      → Job executa conversão → salva em tenants/{tenant_id}/media/{id}/
```

**2. Media Model com HasUuids** (app/Models/Media.php)

```php
class Media extends BaseMedia
{
    use HasUuids;  // ✅ UUID primary key

    // Media records vivem no banco do tenant
    // Isolamento garantido pelo multi-database
    // tenant_id armazenado em custom_properties para path generation
}
```

**3. TenantPathGenerator** (app/Support/TenantPathGenerator.php)

Garante que arquivos e conversões são salvos em paths isolados por tenant:

```php
public function getPath(Media $media): string
{
    // Usa tenant_id de custom_properties ou contexto atual
    $tenantId = $media->getTenantIdForPath();
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
├── abc-uuid-123/
│   └── media/
│       └── def-uuid-456/
│           ├── photo.jpg (original)
│           └── conversions/
│               └── thumb-photo.jpg (300x300)
├── ghi-uuid-789/
│   └── media/
│       └── jkl-uuid-012/
│           ├── document.pdf
│           └── conversions/
│               └── preview-document.jpg
```

## Componentes da Integração

| Componente | Arquivo | Função |
|------------|---------|---------|
| **QueueTenancyBootstrapper** | config/tenancy.php | Injeta tenant_id em jobs, inicializa contexto |
| **DatabaseTenancyBootstrapper** | config/tenancy.php | Conecta ao banco do tenant correto |
| **Media Model** | app/Models/Media.php | Model customizado com HasUuids trait |
| **TenantPathGenerator** | app/Support/TenantPathGenerator.php | Gera paths isolados por tenant |
| **Project Model** | app/Models/Project.php | Define media collections e conversions |
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

    // Media model customizado (isolado por banco de dados)
    'media_model' => App\Models\Tenant\Media::class,
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

1. **Multi-Database Tenancy**: Cada tenant tem banco de dados separado - isolamento físico completo
2. **DatabaseTenancyBootstrapper**: Conecta automaticamente ao banco do tenant correto
3. **TenantPathGenerator**: Arquivos físicos em paths separados por tenant (usando UUID)
4. **QueueTenancyBootstrapper**: Jobs executam no contexto correto
5. **UUID Primary Keys**: IDs não-sequenciais, seguros para expor em URLs

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

1. ✅ Media model usa HasUuids trait (UUID primary key)
2. ✅ Arquivos salvos em paths tenant-isolated
3. ✅ Conversion jobs queued com tenant context
4. ✅ Conversions salvas no path correto do tenant
5. ✅ Media isolada entre tenants (bancos separados)
6. ✅ QueueTenancyBootstrapper está habilitado
7. ✅ DatabaseTenancyBootstrapper está habilitado
8. ✅ MediaLibrary configurado para queue conversions
9. ✅ TenantPathGenerator configurado com UUID
10. ✅ Custom Media model configurado
11. ✅ Project media collections configuradas

**Rodar Testes**:

```bash
# Com Sail (recomendado - PostgreSQL)
sail artisan test --filter=MediaLibraryQueueTenancyTest
```

**⚠️ Limitação Conhecida**: PHP 8.4 + SQLite :memory: + RefreshDatabase tem problema com nested transactions. Use Sail (PostgreSQL) para rodar os testes.

## Best Practices

1. **Use HasUuids trait** em todos os models para consistência
2. **Configure TenantPathGenerator** para isolamento de arquivos físicos
3. **Queue connection separada** (nunca prefixar com tenant_id)
4. **Multi-database isolation** garante segurança automática
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
