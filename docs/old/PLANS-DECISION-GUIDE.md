# Plans Architecture - Decision Guide

## Quick Comparison

| Critério | Database-Driven | Hybrid (DB + Pennant) |
|----------|----------------|----------------------|
| **Complexidade de Implementação** | ⭐⭐⭐ Média | ⭐⭐⭐⭐ Média-Alta |
| **Código Elegante** | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **Cashier Integration** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **Developer Experience** | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **Learning Curve** | Baixa | Média |
| **Manutenibilidade** | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **Future-Proof** | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **Tempo de Implementação** | 2-3 semanas | 3-4 semanas |
| **Dependências Externas** | Zero | Laravel Pennant |
| **Permission Sync** | Manual | Automático |
| **A/B Testing Support** | ❌ | ✅ |
| **Type Safety** | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ |

---

## Code Comparison

### 1. Checking Features

#### Database-Driven
```php
// Backend
if ($tenant->hasFeature('customRoles')) {
    // Show UI
}

// Frontend (via Inertia)
{hasFeature('customRoles') && <Button>Create Role</Button>}
```

#### Hybrid
```php
// Backend
use Laravel\Pennant\Feature;

if (Feature::active('customRoles')) {
    // Show UI
}

// Frontend (via Inertia - same)
{hasFeature('customRoles') && <Button>Create Role</Button>}
```

**Winner**: Hybrid (mais limpo no backend)

---

### 2. Checking Limits

#### Database-Driven
```php
$maxUsers = $tenant->getLimit('users');
$currentUsers = $tenant->getCurrentUsage('users');

if ($currentUsers >= $maxUsers && $maxUsers !== -1) {
    abort(403, 'User limit reached');
}
```

#### Hybrid
```php
use Laravel\Pennant\Feature;

$maxUsers = Feature::value('maxUsers');
$currentUsers = $tenant->getCurrentUsage('users');

if ($currentUsers >= $maxUsers && $maxUsers !== -1) {
    abort(403, 'User limit reached');
}
```

**Winner**: Hybrid (rich value nativo)

---

### 3. Permission Sync on Plan Change

#### Database-Driven
```php
// Manual sync via Observer
public function updated(Tenant $tenant): void
{
    if ($tenant->wasChanged('plan_id')) {
        $this->syncPermissionsManually($tenant);
    }
}

protected function syncPermissionsManually(Tenant $tenant): void
{
    // 30+ linhas de código
    // - Get plan permissions
    // - Get role permissions
    // - Compare and sync
    // - Log changes
}
```

#### Hybrid
```php
// Automatic sync via Observer
public function updated(Tenant $tenant): void
{
    if ($tenant->wasChanged('plan_id')) {
        // Regenerate cache
        $tenant->regeneratePlanPermissions();

        // Clear Pennant cache
        Feature::for($tenant)->flushCache();

        // Done! Permissions auto-synced via Gate::before()
    }
}
```

**Winner**: Hybrid (muito mais simples)

---

### 4. Middleware Usage

#### Database-Driven
```php
// Middleware personalizado
new Middleware('plan.feature:customRoles')
new Middleware('plan.limit:users')

// Implementação: ~50 linhas cada middleware
```

#### Hybrid
```php
// Middleware com Pennant
new Middleware('feature:customRoles')
new Middleware('limit:users')

// Implementação: ~30 linhas cada (Pennant simplifica)
```

**Winner**: Hybrid (menos código)

---

### 5. Testing

#### Database-Driven
```php
#[Test]
public function professional_plan_allows_custom_roles()
{
    $proPlan = Plan::factory()->create([
        'features' => ['customRoles' => true],
    ]);

    $this->tenant->update(['plan_id' => $proPlan->id]);

    $this->assertTrue($this->tenant->hasFeature('customRoles'));
}
```

#### Hybrid
```php
use Laravel\Pennant\Feature;

#[Test]
public function professional_plan_allows_custom_roles()
{
    $proPlan = Plan::factory()->create([
        'features' => ['customRoles' => true],
    ]);

    $this->tenant->update(['plan_id' => $proPlan->id]);

    $this->assertTrue(Feature::for($this->tenant)->active('customRoles'));

    // Bonus: Mock features em outros testes
    Feature::define('customRoles', true);
}
```

**Winner**: Hybrid (mais fácil de mockar)

---

## Feature Matrix

### Database-Driven

**Pros**:
✅ Simples de entender
✅ Zero dependências extras
✅ Cashier-ready
✅ Overrides customizados
✅ Auditável
✅ Bom para MVPs rápidos

**Cons**:
❌ Código mais verboso
❌ Sync de permissions manual
❌ Sem suporte nativo a A/B testing
❌ Menos type-safe
❌ Mais código para manter

**Best For**:
- Times pequenos (1-3 devs)
- MVP em 2-3 semanas
- Simplicidade > Elegância
- Time júnior

---

### Hybrid (DB + Pennant)

**Pros**:
✅ Código muito mais limpo
✅ Permission sync automático
✅ Type-safe (class-based features)
✅ A/B testing support
✅ Rich values nativos
✅ Pennant cache built-in
✅ Future-proof
✅ Industry standard (Laravel 1st party)
✅ Easier testing (mocking)
✅ Better DX

**Cons**:
❌ Mais complexo de entender inicialmente
❌ Dependência do Pennant
❌ Learning curve média
❌ Mais arquivos para manter

**Best For**:
- Times experientes (3+ devs)
- Aplicação de longo prazo (5+ anos)
- DX é prioridade
- Planeja features avançadas (A/B testing)
- Valorizsa manutenibilidade

---

## Cenários de Uso

### Cenário 1: Startup MVP (3 meses para lançar)

**Recomendação**: Database-Driven

**Razão**:
- Time pequeno (1-2 devs)
- Precisa lançar rápido
- Simplicidade é prioridade
- Pode migrar para Hybrid depois se crescer

---

### Cenário 2: SaaS de Médio Porte (1-2 anos de operação)

**Recomendação**: Hybrid

**Razão**:
- Time experiente
- Longo prazo
- Vai precisar de A/B testing
- DX impacta produtividade
- Quer código maintainable

---

### Cenário 3: Enterprise B2B (Custom Deals Frequentes)

**Recomendação**: Hybrid

**Razão**:
- Precisa de overrides customizados (ambas suportam)
- Complex permission logic
- Pennant facilita feature toggles por cliente
- Auditoria é crítica (ambas suportam)

---

### Cenário 4: Migração de App Existente

**Recomendação**: Database-Driven (inicialmente)

**Razão**:
- Menos mudanças no código existente
- Migrar depois para Hybrid se necessário
- Risco menor de quebrar features existentes

---

## Decision Tree

```
START: Precisa de sistema de planos?
│
├─ Sim
│  │
│  ├─ Time tem experiência com Laravel?
│  │  │
│  │  ├─ Sim (Pleno+)
│  │  │  │
│  │  │  ├─ Aplicação de longo prazo (3+ anos)?
│  │  │  │  │
│  │  │  │  ├─ Sim → HYBRID ✅
│  │  │  │  └─ Não → DATABASE-DRIVEN
│  │  │  │
│  │  │  └─ Planeja A/B testing ou feature flags?
│  │  │     │
│  │  │     ├─ Sim → HYBRID ✅
│  │  │     └─ Não → DATABASE-DRIVEN
│  │  │
│  │  └─ Não (Júnior)
│  │     │
│  │     └─ MVP rápido (<3 meses)?
│  │        │
│  │        ├─ Sim → DATABASE-DRIVEN ✅
│  │        └─ Não → HYBRID (investir em learning)
│  │
│  └─ Precisa integrar com Stripe/Paddle?
│     │
│     ├─ Sim → Ambas suportam (Laravel Cashier)
│     └─ Não → Ambas funcionam
│
└─ Não → Não precisa deste sistema
```

---

## Migration Path

### Se Escolher Database-Driven Agora

**Pode migrar para Hybrid depois?** ✅ Sim!

**Esforço de Migração**: Médio (1-2 semanas)

**Steps**:
1. Instalar Pennant
2. Criar class-based features
3. Atualizar middleware para usar Pennant
4. Manter Plan model e DB (já compatível)
5. Testar e deploy

**Zero Breaking Changes**: Frontend não muda!

---

### Se Escolher Hybrid Agora

**Pode simplificar para Database-Driven depois?** ✅ Sim!

**Esforço**: Baixo (2-3 dias)

**Steps**:
1. Remover Pennant
2. Usar métodos do Tenant diretamente
3. Atualizar middleware
4. Remover class-based features

**Mas por quê?** Raramente faz sentido simplificar.

---

## Performance Comparison

### Database Queries

#### Database-Driven
```sql
-- Check feature
SELECT * FROM tenants WHERE id = 1
SELECT * FROM plans WHERE id = (tenant.plan_id)
-- 2 queries (com cache: 0 queries)

-- Check limit
-- Mesma coisa: 0-2 queries
```

#### Hybrid
```sql
-- Check feature (Pennant)
SELECT * FROM tenants WHERE id = 1
SELECT * FROM plans WHERE id = (tenant.plan_id)
SELECT * FROM features WHERE ... -- Pennant storage
-- 3 queries (com cache: 0-1 queries)

-- Pennant cache é muito eficiente
```

**Winner**: Empate (ambos cacheiam muito bem)

---

### Memory Usage

#### Database-Driven
- Plan object carregado em memória
- Features/limits como arrays

#### Hybrid
- Plan object carregado
- Features/limits como arrays
- Pennant storage (minimal overhead)

**Winner**: Empate (diferença < 1MB)

---

## Code Maintainability

### Lines of Code (LoC)

#### Database-Driven
- Models: ~300 LoC
- Middleware: ~100 LoC
- Observers: ~150 LoC
- Helpers: ~50 LoC
- **Total**: ~600 LoC

#### Hybrid
- Models: ~350 LoC (HasFeatures trait)
- Features: ~200 LoC (class-based)
- Middleware: ~80 LoC (Pennant simplifica)
- Observers: ~80 LoC (Pennant simplifica)
- Helpers: ~30 LoC
- **Total**: ~740 LoC

**Winner**: Database-Driven (menos código)

**Mas**: Hybrid tem código mais organizado e legível.

---

### Test Complexity

#### Database-Driven
```php
// Setup para cada teste
$plan = Plan::factory()->create([...]);
$tenant->update(['plan_id' => $plan->id]);

// Testar
$this->assertTrue($tenant->hasFeature('customRoles'));
```

#### Hybrid
```php
// Mock features facilmente
Feature::define('customRoles', true);

// Ou testar com plan real
$plan = Plan::factory()->professional();
$tenant->update(['plan_id' => $plan->id]);

$this->assertTrue(Feature::active('customRoles'));
```

**Winner**: Hybrid (mocking é muito mais fácil)

---

## Final Recommendation

### 🏆 Para Este Projeto: HYBRID ✅

**Razões**:

1. **Time Experiente**: Vocês já implementaram sistema complexo de permissions
2. **Longo Prazo**: Aplicação será mantida por anos
3. **DX Matters**: Developer experience impacta produtividade
4. **Laravel-First**: Pennant é package oficial
5. **Future-Proof**: A/B testing pode ser útil no futuro
6. **Code Quality**: Código mais limpo e maintainable
7. **Integration**: Permission sync automático é game changer

**Mas Começar Simples**:
- **Week 1-2**: Implementar Database schema + Models (base para ambas)
- **Week 3**: Adicionar Pennant features
- **Week 4**: Permission sync + Frontend
- **Week 5**: Cashier integration
- **Week 6**: Polish + Testes

**Fallback Plan**: Se Pennant for difícil, sempre pode simplificar para Database-Driven.

---

## Decision Checklist

Marque ✅ ou ❌:

- [ ] Time tem experiência com Laravel (Pleno+)
- [ ] Aplicação de longo prazo (3+ anos)
- [ ] DX é prioridade
- [ ] Planeja A/B testing futuro
- [ ] Time confortável aprendendo Pennant
- [ ] Tempo para implementar (3-4 semanas ok)
- [ ] Valoriza manutenibilidade > simplicidade

**Se 5+ ✅**: Escolha **HYBRID**
**Se 3-4 ✅**: Escolha **HYBRID** (mas pode ser Database-Driven)
**Se 0-2 ✅**: Escolha **DATABASE-DRIVEN**

---

## Next Steps

1. ✅ Ler documentações completas:
   - `docs/PLANS-ARCHITECTURE.md` (Database-Driven)
   - `docs/PLANS-HYBRID-ARCHITECTURE.md` (Hybrid)
   - `docs/PLANS-SEEDERS.md` (Seeders para ambas)

2. ✅ Discutir com time:
   - Complexidade aceitável?
   - Pennant vale a pena?
   - Tempo disponível (2-4 semanas)?

3. ✅ Decidir arquitetura

4. ⏳ Criar branch:
   ```bash
   git checkout -b feature/plans-hybrid
   # ou
   git checkout -b feature/plans-database
   ```

5. ⏳ Implementar conforme documentação

---

## Support & Questions

Se tiver dúvidas durante implementação:

1. Consultar docs oficiais:
   - Laravel Pennant: https://laravel.com/docs/12.x/pennant
   - Spatie Permission: https://spatie.be/docs/laravel-permission
   - Laravel Cashier: https://laravel.com/docs/12.x/billing

2. Usar MCP Context7 para buscar exemplos

3. Iterar e refinar

**Boa sorte com a implementação! 🚀**
