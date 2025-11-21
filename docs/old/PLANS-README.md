# Plans & Features System - Documentation Index

## 📚 Documentação Completa

Sistema completo de gerenciamento de planos SaaS com integração ao Spatie Permission, Stancl/Tenancy e Laravel Cashier.

---

## 🗂️ Documentos Disponíveis

### 1. **PLANS-ARCHITECTURE.md** (Database-Driven)
**Tipo**: Arquitetura completa
**Tamanho**: ~70 páginas
**Conteúdo**:
- ✅ Schema de banco de dados completo
- ✅ Models com toda lógica implementada
- ✅ Middleware de proteção (limits + features)
- ✅ Helpers globais
- ✅ Seeders com 3 planos prontos
- ✅ Integração Laravel Cashier (Stripe-ready)
- ✅ 5 exemplos práticos (controllers + frontend)
- ✅ Testes completos
- ✅ Observers para auto-tracking
- ✅ Roadmap de 5 fases

**Quando Usar**:
- Time pequeno ou júnior
- MVP rápido (2-3 semanas)
- Simplicidade > Elegância
- Zero dependências extras

**Leia se**: Quer implementação direta, sem complexidade adicional

---

### 2. **PLANS-HYBRID-ARCHITECTURE.md** (Database + Pennant) ⭐ RECOMENDADO
**Tipo**: Arquitetura híbrida avançada
**Tamanho**: ~80 páginas
**Conteúdo**:
- ✅ Tudo do Database-Driven MAIS:
- ✅ Laravel Pennant integration
- ✅ Class-based features (type-safe)
- ✅ Permission sync automático via observers
- ✅ Gate integration para check de plan permissions
- ✅ Rich values para limits (maxUsers, storageLimit)
- ✅ Feature flags com A/B testing support
- ✅ Código mais elegante e maintainable
- ✅ Mocking facilitado em testes
- ✅ Frontend hooks (`usePlan()`)

**Quando Usar**:
- Time experiente (pleno+)
- Aplicação de longo prazo (3+ anos)
- DX é prioridade
- Planeja A/B testing futuro

**Leia se**: Quer o melhor dos dois mundos com código elegante

---

### 3. **PLANS-SEEDERS.md**
**Tipo**: Implementação de seeders
**Tamanho**: ~15 páginas
**Conteúdo**:
- ✅ `PlanSeeder` completo com 3 planos
- ✅ Mapeamento feature → permissions
- ✅ `EnterprisePermissionsSeeder` (Reports, SSO, Branding)
- ✅ `PlanFactory` com states (starter, professional, enterprise)
- ✅ Testes de seeders
- ✅ Comparison table (features por plano)
- ✅ Permission breakdown detalhado

**Quando Usar**:
- Qualquer arquitetura (funciona para ambas)
- Precisa popular banco com planos

**Leia se**: Vai implementar qualquer uma das arquiteturas

---

### 4. **PLANS-DECISION-GUIDE.md** (Este Documento)
**Tipo**: Guia de decisão
**Tamanho**: ~20 páginas
**Conteúdo**:
- ✅ Comparação lado-a-lado (Database vs Hybrid)
- ✅ Code comparison real
- ✅ Decision tree
- ✅ Cenários de uso
- ✅ Performance comparison
- ✅ Migration path
- ✅ Checklist de decisão

**Quando Usar**:
- Antes de começar implementação
- Precisa decidir qual arquitetura usar

**Leia se**: Não sabe qual arquitetura escolher

---

## 🎯 Recomendação de Leitura

### Para Iniciantes ou MVPs Rápidos
1. **PLANS-DECISION-GUIDE.md** (este doc) - Entender opções
2. **PLANS-ARCHITECTURE.md** - Database-Driven
3. **PLANS-SEEDERS.md** - Seeders
4. ✅ Começar implementação

---

### Para Times Experientes ⭐ RECOMENDADO
1. **PLANS-DECISION-GUIDE.md** - Entender trade-offs
2. **PLANS-HYBRID-ARCHITECTURE.md** - Arquitetura híbrida
3. **PLANS-SEEDERS.md** - Seeders
4. ✅ Começar implementação

---

## 📊 Quick Comparison

| Critério | Database-Driven | Hybrid (Recomendado) |
|----------|----------------|----------------------|
| **Complexidade** | Média | Média-Alta |
| **Tempo Implementação** | 2-3 semanas | 3-4 semanas |
| **Código Elegante** | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **DX** | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **Manutenibilidade** | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **Future-Proof** | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **Learning Curve** | Baixa | Média |
| **Documentação** | 70 páginas | 80 páginas |

---

## 🚀 Quick Start

### 1. Escolha sua arquitetura

```bash
# Opção A: Database-Driven (simples)
cat docs/PLANS-ARCHITECTURE.md

# Opção B: Hybrid (recomendado) ⭐
cat docs/PLANS-HYBRID-ARCHITECTURE.md
```

### 2. Leia os seeders

```bash
cat docs/PLANS-SEEDERS.md
```

### 3. Crie branch

```bash
# Para Database-Driven
git checkout -b feature/plans-database

# Para Hybrid
git checkout -b feature/plans-hybrid
```

### 4. Implemente seguindo a documentação

Ambas documentações incluem:
- ✅ Migrations completas
- ✅ Models implementados
- ✅ Middleware
- ✅ Observers
- ✅ Seeders
- ✅ Exemplos práticos
- ✅ Testes
- ✅ Frontend integration

---

## 📦 O Que Cada Plano Oferece

### Starter ($29/mês)
- 👤 **1 usuário**
- 📁 50 projetos
- 💾 1GB storage
- 📊 30 dias de logs
- ❌ Sem API
- ❌ Sem custom roles

### Professional ($99/mês)
- 👥 **50 usuários**
- 📁 Projetos ilimitados
- 💾 10GB storage
- 📊 90 dias de logs
- ✅ **API Access** (10k calls/mês)
- ✅ **Custom Roles**

### Enterprise (Custom)
- 👥 **Ilimitado**
- 📁 Ilimitado
- 💾 100GB storage
- 📊 365 dias de logs
- ✅ API ilimitada
- ✅ Custom Roles
- ✅ **Advanced Reports**
- ✅ **SSO**
- ✅ **White Label**

---

## 🔗 Integração com Sistema Atual

### Spatie Permission ✅
**Já Integrado**: Sistema atual tem 22 permissions em 5 categorias

**O Que Muda**:
- Features do plano controlam quais permissions estão disponíveis
- Upgrade → novas permissions liberadas automaticamente
- Downgrade → permissions removidas automaticamente

**Exemplo**:
```php
// Tenant com plano Starter: sem custom roles
$user->can('tenant.roles:create'); // false

// Upgrade para Professional
$tenant->changePlan($proPlan);

// Agora tem custom roles!
$user->can('tenant.roles:create'); // true (auto-synced!)
```

### Stancl Tenancy ✅
**Já Integrado**: Tenant model com custom columns support

**O Que Adiciona**:
- Coluna `plan_id` no tenant
- Coluna `current_usage` para tracking
- Observer para sync automático

### MediaLibrary ✅
**Já Integrado**: TenantPathGenerator

**O Que Adiciona**:
- Storage quotas: `tenant()->getLimit('storage')`
- Check antes de upload
- Auto-tracking de usage

---

## 🧪 Testing Strategy

### Unit Tests
```php
// Test plan features
$plan->hasFeature('customRoles'); // true/false

// Test limits
$plan->getLimit('users'); // 50

// Test permission mapping
$plan->getAllEnabledPermissions(); // array
```

### Feature Tests
```php
// Test plan restrictions
$this->actingAs($starterUser);
$response = $this->post('/roles'); // 403 Forbidden

// Test upgrades
$tenant->changePlan($proPlan);
$response = $this->post('/roles'); // 200 OK
```

### Integration Tests
```php
// Test full flow
1. Create tenant
2. Assign Starter plan
3. Create user
4. Try create custom role → Fail
5. Upgrade to Professional
6. Try create custom role → Success
```

---

## 📈 Roadmap

### Phase 1: MVP (2-4 semanas)
- ✅ Database schema
- ✅ Models
- ✅ Seeders
- ✅ Middleware
- ✅ Basic UI

### Phase 2: Billing (1-2 semanas)
- ⏳ Laravel Cashier
- ⏳ Stripe integration
- ⏳ Webhooks
- ⏳ Billing UI

### Phase 3: Polish (1 semana)
- ⏳ Usage charts
- ⏳ Alertas de limite
- ⏳ Analytics

### Phase 4: Advanced (opcional)
- ⏳ Add-ons
- ⏳ Multi-currency
- ⏳ Annual billing

**Total**: 1-2 meses para sistema completo

---

## 💡 Tips & Best Practices

### 1. Start Small
Implemente planos básicos primeiro, adicione features depois.

### 2. Test Thoroughly
Use factories e seeders para testar todos os cenários.

### 3. Cache Wisely
```php
// Cache plan para reduzir queries
Cache::remember("tenant.{$tenantId}.plan", 3600, fn() =>
    Tenant::with('plan')->find($tenantId)->plan
);
```

### 4. Audit Everything
```php
// Log todas mudanças de plano
activity()
    ->performedOn($tenant)
    ->log('Plan changed from Starter to Professional');
```

### 5. Handle Downgrades Carefully
```php
// Validar antes de downgrade
if ($currentUsage > $newLimit) {
    throw new \Exception("Cannot downgrade: usage exceeds new limit");
}
```

---

## 🆘 Troubleshooting

### Problema: Permissions não sincronizam após mudança de plano

**Solução**:
```bash
# Verificar observer
sail artisan tinker
>>> Tenant::observe(\App\Observers\TenantObserver::class);

# Limpar cache
sail artisan permission:cache-reset

# Sync manual
>>> $tenant->regeneratePlanPermissions();
```

### Problema: Pennant features retornam valor errado

**Solução**:
```php
// Clear Pennant cache
Feature::for($tenant)->flushCache();

// Ou limpar feature específica
Feature::forget('customRoles');
```

### Problema: Frontend não mostra features corretas

**Solução**:
```php
// Verificar Inertia sharing
HandleInertiaRequests::share() // deve incluir plan

// Clear browser cache
// Hard refresh (Ctrl+Shift+R)
```

---

## 📞 Support

### Documentação Oficial
- Laravel Pennant: https://laravel.com/docs/12.x/pennant
- Spatie Permission: https://spatie.be/docs/laravel-permission
- Laravel Cashier: https://laravel.com/docs/12.x/billing
- Stancl Tenancy: https://tenancyforlaravel.com/

### Código Existente
- Permissions: `docs/PERMISSIONS.md`
- Tenancy: `docs/STANCL-FEATURES.md`
- Session Security: `docs/SESSION-SECURITY.md`

### MCP Tools
Use Context7 MCP para buscar exemplos:
```bash
# Laravel Pennant examples
Context7: laravel pennant features

# Cashier examples
Context7: laravel cashier subscriptions

# Spatie Permission examples
Context7: spatie permission roles
```

---

## ✅ Checklist de Implementação

### Pre-Implementation
- [ ] Ler documentação completa
- [ ] Decidir arquitetura (Database vs Hybrid)
- [ ] Discutir com time
- [ ] Criar branch

### Implementation
- [ ] Rodar migrations
- [ ] Criar models
- [ ] Implementar seeders
- [ ] Criar middleware
- [ ] Adicionar observers
- [ ] Atualizar frontend
- [ ] Escrever testes

### Post-Implementation
- [ ] Code review
- [ ] Testes de integração
- [ ] Deploy staging
- [ ] Testar manualmente
- [ ] Deploy production

### Cashier Integration (Phase 2)
- [ ] Instalar Cashier
- [ ] Configurar Stripe
- [ ] Criar webhooks
- [ ] UI de billing
- [ ] Testar subscriptions

---

## 🎉 Conclusão

Você agora tem documentação completa para implementar um sistema robusto de planos SaaS com:

✅ 2 arquiteturas bem documentadas
✅ Integração profunda com Spatie Permission
✅ Suporte a Laravel Cashier (Stripe/Paddle)
✅ Seeders prontos com 3 planos
✅ Exemplos práticos de código
✅ Testes completos
✅ Migration path clara

**Escolha sua arquitetura e comece a implementar!**

**Recomendação**: Hybrid Architecture para aplicações de longo prazo 🚀

---

## 📝 Changelog

### v1.0.0 (2025-11-20)
- ✅ Criada documentação completa
- ✅ Database-Driven architecture
- ✅ Hybrid architecture (Database + Pennant)
- ✅ Seeders completos
- ✅ Decision guide
- ✅ Integration com sistema atual

### Next Version (Planejado)
- ⏳ Add-ons system
- ⏳ Usage analytics UI
- ⏳ Advanced reporting
- ⏳ Multi-currency support
