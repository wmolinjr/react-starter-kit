# Billing UX Refactor Plan

## Overview

Plano de refatoramento da experiência de compra do tenant, unificando as telas de billing, addons e bundles em uma experiência moderna e componentizada.

**Objetivo**: Criar uma experiência de compra unificada ("Store") que seja reutilizável na store pública futura.

---

## 1. Problemas Atuais

### 1.1 Fragmentação da Experiência
- Billing (`/admin/billing`) e Addons (`/admin/addons`) são seções completamente separadas
- Usuário precisa navegar entre páginas diferentes para entender sua assinatura completa
- Sem visão unificada do "custo total" (plano + addons)

### 1.2 Ausência de Features
- **Bundle Catalog**: Modelo `AddonBundle` existe no backend mas sem UI de compra
- **Comparação de Planos**: Planos exibidos como cards, sem tabela comparativa lado a lado
- **Histórico de Compras**: Sem visualização de histórico de addons/bundles
- **Upgrade Flow**: Sem wizard guiado para upgrade de plano

### 1.3 UX Desatualizada
- Componentes de billing não seguem os padrões mais recentes do shadcn/ui
- Falta de feedback visual durante checkout
- Navegação confusa entre billing/addons/usage

### 1.4 Componentização Limitada
- Componentes atuais são acoplados às páginas específicas
- Difícil reutilizar em store pública ou outros contextos
- Falta de componentes de UI primitivos para pricing

---

## 2. Arquitetura Proposta

### 2.1 Nova Estrutura de Páginas

```
/admin/billing                    → Dashboard unificado (visão geral)
/admin/billing/plans              → Catálogo de planos + comparação
/admin/billing/plans/change       → Wizard de troca de plano
/admin/billing/addons             → Catálogo de addons
/admin/billing/bundles            → Catálogo de bundles [NOVO]
/admin/billing/checkout           → Checkout unificado [NOVO]
/admin/billing/checkout/success   → Sucesso do checkout
/admin/billing/invoices           → Histórico de faturas
/admin/billing/usage              → Dashboard de uso (metered)
```

### 2.2 Hierarquia de Componentes

```
resources/js/components/
├── shared/billing/                    # Componentes de billing (reutilizáveis)
│   ├── primitives/                    # Componentes primitivos de UI
│   │   ├── pricing-card.tsx           # Card genérico de preço
│   │   ├── pricing-toggle.tsx         # Toggle mensal/anual
│   │   ├── feature-list.tsx           # Lista de features com ícones
│   │   ├── feature-badge.tsx          # Badge de feature (incluído, extra, etc)
│   │   ├── price-display.tsx          # Display de preço formatado
│   │   ├── savings-badge.tsx          # Badge de economia (save 20%)
│   │   └── usage-progress.tsx         # Barra de progresso de uso
│   │
│   ├── plans/                         # Componentes de planos
│   │   ├── plan-card.tsx              # Card de plano individual
│   │   ├── plan-comparison-table.tsx  # Tabela comparativa de planos
│   │   ├── current-plan-banner.tsx    # Banner do plano atual
│   │   ├── plan-upgrade-cta.tsx       # CTA de upgrade
│   │   └── plan-change-wizard.tsx     # Wizard de mudança de plano
│   │
│   ├── addons/                        # Componentes de addons (refatorados)
│   │   ├── addon-catalog-card.tsx     # Card de addon no catálogo
│   │   ├── addon-active-card.tsx      # Card de addon ativo
│   │   ├── addon-quantity-input.tsx   # Input de quantidade
│   │   ├── addon-purchase-sheet.tsx   # Sheet de compra (lado direito)
│   │   └── addon-type-filter.tsx      # Filtro por tipo de addon
│   │
│   ├── bundles/                       # Componentes de bundles [NOVO]
│   │   ├── bundle-card.tsx            # Card de bundle
│   │   ├── bundle-contents.tsx        # Lista de addons no bundle
│   │   ├── bundle-savings.tsx         # Display de economia do bundle
│   │   └── bundle-comparison.tsx      # Comparação bundle vs avulso
│   │
│   ├── checkout/                      # Componentes de checkout [NOVO]
│   │   ├── checkout-summary.tsx       # Resumo do pedido
│   │   ├── checkout-line-item.tsx     # Item linha no resumo
│   │   ├── checkout-totals.tsx        # Subtotal, desconto, total
│   │   ├── checkout-billing-period.tsx # Seleção de período
│   │   └── checkout-cta.tsx           # Botão de checkout
│   │
│   ├── dashboard/                     # Componentes do dashboard
│   │   ├── subscription-overview.tsx  # Visão geral da assinatura
│   │   ├── cost-breakdown.tsx         # Breakdown de custos
│   │   ├── usage-summary.tsx          # Resumo de uso
│   │   ├── quick-actions.tsx          # Ações rápidas
│   │   └── invoice-preview.tsx        # Preview da próxima fatura
│   │
│   └── shared/                        # Componentes compartilhados
│       ├── billing-period-context.tsx # Context para billing period
│       ├── checkout-context.tsx       # Context para carrinho
│       ├── stripe-checkout-button.tsx # Botão que redireciona ao Stripe
│       └── billing-portal-link.tsx    # Link para portal Stripe
```

**Nota**: Os componentes em `tenant/addons/` serão removidos após a migração completa.

### 2.3 Novo Sistema de Tipos

```typescript
// resources/js/types/billing.d.ts

// Produto genérico (pode ser plan, addon ou bundle)
interface BillingProduct {
    id: string;
    type: 'plan' | 'addon' | 'bundle';
    slug: string;
    name: string;
    description: string;
    icon?: string;
    badge?: string;
}

// Item no checkout
interface CheckoutItem {
    product: BillingProduct;
    quantity: number;
    billingPeriod: BillingPeriod;
    unitPrice: number;
    totalPrice: number;
    isRecurring: boolean;
}

// Estado do checkout
interface CheckoutState {
    items: CheckoutItem[];
    planChange?: {
        from: PlanResource;
        to: PlanResource;
        prorationAmount: number;
    };
    subtotal: number;
    discount: number;
    total: number;
    billingPeriod: BillingPeriod;
}

// Visão unificada da assinatura
interface SubscriptionOverview {
    plan: PlanResource;
    subscription: SubscriptionResource;
    addons: AddonSubscription[];
    bundles: BundleSubscription[];
    usage: Record<string, UsageMetric>;
    nextInvoice: {
        date: string;
        estimatedAmount: number;
    };
    costs: {
        planCost: number;
        addonsCost: number;
        bundlesCost: number;
        totalMonthlyCost: number;
    };
}
```

---

## 3. Componentes Detalhados

### 3.1 Primitivos de Pricing

#### PricingCard
```tsx
interface PricingCardProps {
    title: string;
    description?: string;
    badge?: { text: string; variant: 'default' | 'popular' | 'new' };
    price: {
        amount: number;
        currency: string;
        period?: 'monthly' | 'yearly' | 'one_time';
        originalAmount?: number; // Para mostrar desconto
    };
    features: FeatureItem[];
    cta: {
        label: string;
        action: () => void;
        variant?: 'default' | 'outline' | 'ghost';
        disabled?: boolean;
    };
    highlighted?: boolean;
    current?: boolean;
}

interface FeatureItem {
    text: string;
    included: boolean;
    tooltip?: string;
    limit?: string | number; // "10 users" ou 10
}
```

#### PricingToggle
```tsx
interface PricingToggleProps {
    value: 'monthly' | 'yearly';
    onChange: (value: 'monthly' | 'yearly') => void;
    savings?: string; // "Save 20%"
    monthlyLabel?: string;
    yearlyLabel?: string;
}
```

#### FeatureList
```tsx
interface FeatureListProps {
    features: FeatureItem[];
    columns?: 1 | 2;
    maxVisible?: number; // Com "show more"
    variant?: 'compact' | 'detailed';
}
```

### 3.2 Plan Components

#### PlanComparisonTable
Tabela comparativa lado a lado de todos os planos.

```tsx
interface PlanComparisonTableProps {
    plans: PlanResource[];
    currentPlanSlug?: string;
    onSelect: (planSlug: string) => void;
    billingPeriod: 'monthly' | 'yearly';
    featureCategories?: FeatureCategory[];
}

interface FeatureCategory {
    name: string;
    features: {
        key: string;
        label: string;
        description?: string;
        values: Record<string, boolean | string | number>; // planSlug -> value
    }[];
}
```

Exemplo visual:
```
┌─────────────────┬──────────┬───────────────┬────────────┐
│                 │ Starter  │ Professional  │ Enterprise │
├─────────────────┼──────────┼───────────────┼────────────┤
│ Price           │ $9/mo    │ $29/mo        │ $99/mo     │
├─────────────────┼──────────┼───────────────┼────────────┤
│ Users           │ 5        │ 25            │ Unlimited  │
│ Projects        │ 10       │ 50            │ Unlimited  │
│ Storage         │ 5 GB     │ 50 GB         │ 500 GB     │
├─────────────────┼──────────┼───────────────┼────────────┤
│ Custom Roles    │ ✗        │ ✓             │ ✓          │
│ API Access      │ ✗        │ ✓             │ ✓          │
│ SSO             │ ✗        │ ✗             │ ✓          │
│ Audit Log       │ ✗        │ ✓             │ ✓          │
├─────────────────┼──────────┼───────────────┼────────────┤
│                 │ Current  │ [Upgrade]     │ [Upgrade]  │
└─────────────────┴──────────┴───────────────┴────────────┘
```

#### CurrentPlanBanner
Banner que mostra o plano atual com status e próxima cobrança.

```tsx
interface CurrentPlanBannerProps {
    plan: PlanResource;
    subscription: SubscriptionResource;
    onManage: () => void;
    onUpgrade: () => void;
}
```

### 3.3 Bundle Components

#### BundleCard
```tsx
interface BundleCardProps {
    bundle: BundleResource;
    billingPeriod: 'monthly' | 'yearly';
    onPurchase: () => void;
    isPurchased?: boolean;
    disabled?: boolean;
}
```

Exibe:
- Nome e descrição do bundle
- Ícone e badge (e.g., "Best Value")
- Lista de addons incluídos
- Preço com desconto vs preço individual
- Badge de economia ("Save $50/mo")

#### BundleContents
```tsx
interface BundleContentsProps {
    addons: BundleAddonItem[];
    showQuantities?: boolean;
    showValues?: boolean; // Mostrar valor de cada addon
}
```

#### BundleSavings
```tsx
interface BundleSavingsProps {
    individualPrice: number;
    bundlePrice: number;
    currency: string;
    period: 'monthly' | 'yearly';
}
```

### 3.4 Checkout Components

#### CheckoutSummary
Sheet/Drawer lateral com resumo do pedido.

```tsx
interface CheckoutSummaryProps {
    items: CheckoutItem[];
    billingPeriod: 'monthly' | 'yearly';
    onBillingPeriodChange: (period: 'monthly' | 'yearly') => void;
    onRemoveItem: (itemId: string) => void;
    onUpdateQuantity: (itemId: string, quantity: number) => void;
    onCheckout: () => void;
    isCheckingOut?: boolean;
}
```

#### CheckoutLineItem
```tsx
interface CheckoutLineItemProps {
    item: CheckoutItem;
    onRemove: () => void;
    onQuantityChange?: (quantity: number) => void;
    readonly?: boolean;
}
```

### 3.5 Dashboard Components

#### SubscriptionOverview
Widget principal do dashboard de billing.

```tsx
interface SubscriptionOverviewProps {
    overview: SubscriptionOverview;
    onUpgrade: () => void;
    onManageAddons: () => void;
    onViewInvoices: () => void;
}
```

Exibe:
- Plano atual com status
- Custo mensal total (plan + addons)
- Próxima fatura
- Uso vs limites (barras de progresso)
- Ações rápidas

#### CostBreakdown
```tsx
interface CostBreakdownProps {
    costs: SubscriptionOverview['costs'];
    currency: string;
    showDetails?: boolean;
}
```

---

## 4. Novos API Resources

### 4.1 BundleCatalogResource

```php
// app/Http/Resources/Central/BundleCatalogResource.php
class BundleCatalogResource extends BaseResource
{
    use HasTypescriptType;

    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->getTranslation('name', app()->getLocale()),
            'description' => $this->getTranslation('description', app()->getLocale()),
            'icon' => $this->icon ?? 'Package',
            'icon_color' => $this->icon_color,
            'badge' => $this->badge,
            'discount_percent' => $this->discount_percent,

            // Pricing
            'billing' => [
                'monthly' => [
                    'price' => $this->getEffectivePriceMonthly(),
                    'formatted_price' => $this->formatted_price_monthly,
                    'base_price' => $this->getBasePriceMonthly(),
                    'savings' => $this->getSavingsMonthly(),
                    'formatted_savings' => $this->formatted_savings_monthly,
                ],
                'yearly' => [
                    'price' => $this->getEffectivePriceYearly(),
                    'formatted_price' => $this->formatted_price_yearly,
                    'base_price' => $this->getBasePriceYearly(),
                    'savings' => $this->getSavingsYearly(),
                    'formatted_savings' => format_stripe_price($this->getSavingsYearly()),
                ],
            ],

            // Contents
            'addons' => BundleAddonResource::collection($this->addons),
            'addon_count' => $this->addon_count,
            'features' => $this->getTranslatedFeatures(),

            // Availability
            'is_available' => $this->isAvailableForPlan(tenant()->plan),
            'is_purchased' => $this->isPurchasedBy(tenant()),
        ];
    }
}
```

### 4.2 SubscriptionOverviewResource

```php
// app/Http/Resources/Tenant/SubscriptionOverviewResource.php
class SubscriptionOverviewResource extends BaseResource
{
    public function toArray($request): array
    {
        $tenant = tenant();

        return [
            'plan' => new PlanResource($tenant->plan),
            'subscription' => $this->formatSubscription($tenant),
            'addons' => AddonSubscriptionResource::collection($tenant->addons),
            'bundles' => BundleSubscriptionResource::collection($tenant->activeBundles()),

            'usage' => $this->formatUsage($tenant),

            'next_invoice' => [
                'date' => $this->getNextInvoiceDate($tenant),
                'estimated_amount' => $this->getEstimatedAmount($tenant),
                'formatted_amount' => format_stripe_price($this->getEstimatedAmount($tenant)),
            ],

            'costs' => [
                'plan_cost' => $tenant->plan->price_monthly ?? 0,
                'addons_cost' => $this->addonService->calculateTotalMonthlyCost($tenant),
                'bundles_cost' => $this->calculateBundlesCost($tenant),
                'total_monthly_cost' => $this->calculateTotalMonthlyCost($tenant),
                'formatted_total' => format_stripe_price($this->calculateTotalMonthlyCost($tenant)),
            ],
        ];
    }
}
```

### 4.3 PlanComparisonResource

```php
// app/Http/Resources/Central/PlanComparisonResource.php
class PlanComparisonResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->getTranslation('name', app()->getLocale()),
            'description' => $this->getTranslation('description', app()->getLocale()),
            'badge' => $this->badge,

            'pricing' => [
                'monthly' => [
                    'price' => $this->price_monthly,
                    'formatted' => format_stripe_price($this->price_monthly),
                ],
                'yearly' => [
                    'price' => $this->price_yearly,
                    'formatted' => format_stripe_price($this->price_yearly),
                    'monthly_equivalent' => round($this->price_yearly / 12),
                    'savings_percent' => $this->getYearlySavingsPercent(),
                ],
            ],

            'limits' => $this->limits,
            'features' => $this->features,

            'feature_matrix' => $this->buildFeatureMatrix(),

            'is_current' => tenant()?->plan_id === $this->id,
            'is_upgrade' => $this->isUpgradeFrom(tenant()?->plan),
            'is_downgrade' => $this->isDowngradeFrom(tenant()?->plan),
        ];
    }

    protected function buildFeatureMatrix(): array
    {
        $matrix = [];

        // Limits
        foreach (PlanLimit::cases() as $limit) {
            $matrix['limits'][$limit->value] = [
                'label' => $limit->label(),
                'value' => $this->limits[$limit->value] ?? null,
                'formatted' => $this->formatLimitValue($limit),
            ];
        }

        // Features
        foreach (PlanFeature::cases() as $feature) {
            if ($feature === PlanFeature::BASE) continue;

            $matrix['features'][$feature->value] = [
                'label' => $feature->label(),
                'description' => $feature->translatedDescription(),
                'included' => in_array($feature->value, $this->features ?? []),
                'category' => $feature->category(),
            ];
        }

        return $matrix;
    }
}
```

---

## 5. Novos Hooks

### 5.1 useBillingPeriod

```typescript
// resources/js/hooks/billing/use-billing-period.ts
import { create } from 'zustand';
import { persist } from 'zustand/middleware';

interface BillingPeriodState {
    period: 'monthly' | 'yearly';
    setPeriod: (period: 'monthly' | 'yearly') => void;
    toggle: () => void;
}

export const useBillingPeriod = create<BillingPeriodState>()(
    persist(
        (set) => ({
            period: 'monthly',
            setPeriod: (period) => set({ period }),
            toggle: () => set((state) => ({
                period: state.period === 'monthly' ? 'yearly' : 'monthly'
            })),
        }),
        { name: 'billing-period' }
    )
);
```

### 5.2 useCheckout

```typescript
// resources/js/hooks/billing/use-checkout.ts
import { create } from 'zustand';

interface CheckoutItem {
    id: string;
    type: 'addon' | 'bundle' | 'plan';
    slug: string;
    name: string;
    quantity: number;
    billingPeriod: BillingPeriod;
    unitPrice: number;
}

interface CheckoutState {
    items: CheckoutItem[];
    isOpen: boolean;

    // Actions
    addItem: (item: Omit<CheckoutItem, 'id'>) => void;
    removeItem: (id: string) => void;
    updateQuantity: (id: string, quantity: number) => void;
    clearCart: () => void;
    open: () => void;
    close: () => void;

    // Computed
    subtotal: () => number;
    itemCount: () => number;
}

export const useCheckout = create<CheckoutState>((set, get) => ({
    items: [],
    isOpen: false,

    addItem: (item) => set((state) => ({
        items: [...state.items, { ...item, id: crypto.randomUUID() }],
        isOpen: true,
    })),

    removeItem: (id) => set((state) => ({
        items: state.items.filter(item => item.id !== id),
    })),

    updateQuantity: (id, quantity) => set((state) => ({
        items: state.items.map(item =>
            item.id === id ? { ...item, quantity } : item
        ),
    })),

    clearCart: () => set({ items: [] }),
    open: () => set({ isOpen: true }),
    close: () => set({ isOpen: false }),

    subtotal: () => get().items.reduce(
        (sum, item) => sum + (item.unitPrice * item.quantity),
        0
    ),

    itemCount: () => get().items.reduce(
        (sum, item) => sum + item.quantity,
        0
    ),
}));
```

### 5.3 useBundles

```typescript
// resources/js/hooks/billing/use-bundles.ts
import { usePage } from '@inertiajs/react';

interface UseBundlesReturn {
    available: BundleCatalogResource[];
    purchased: BundleSubscriptionResource[];

    getBundle: (slug: string) => BundleCatalogResource | undefined;
    hasPurchased: (slug: string) => boolean;
    canPurchase: (slug: string) => boolean;
    getSavings: (slug: string, period: 'monthly' | 'yearly') => number;
}

export function useBundles(): UseBundlesReturn {
    const { bundles } = usePage<SharedData & { bundles: BundlesData }>().props;
    // ... implementation
}
```

### 5.4 useSubscription

```typescript
// resources/js/hooks/billing/use-subscription.ts
import { usePage } from '@inertiajs/react';

interface UseSubscriptionReturn {
    overview: SubscriptionOverview;
    plan: PlanResource;
    subscription: SubscriptionResource | null;

    isTrialing: boolean;
    isCanceled: boolean;
    isActive: boolean;

    totalMonthlyCost: number;
    formattedTotalCost: string;

    usage: Record<string, UsageMetric>;
    getUsage: (key: string) => UsageMetric | undefined;
    isNearLimit: (key: string, threshold?: number) => boolean;
    isOverLimit: (key: string) => boolean;

    nextInvoiceDate: string | null;
    estimatedNextInvoice: number;
}

export function useSubscription(): UseSubscriptionReturn {
    // ... implementation
}
```

---

## 6. Controllers & Routes

### 6.1 Novo BillingController Expandido

```php
// app/Http/Controllers/Tenant/Admin/BillingController.php

class BillingController extends Controller
{
    // Dashboard unificado
    public function index(): Response
    {
        return Inertia::render('tenant/admin/billing/index', [
            'overview' => new SubscriptionOverviewResource(tenant()),
        ]);
    }

    // Catálogo de planos
    public function plans(): Response
    {
        return Inertia::render('tenant/admin/billing/plans', [
            'plans' => PlanComparisonResource::collection(
                Plan::active()->ordered()->get()
            ),
            'currentPlan' => new PlanResource(tenant()->plan),
        ]);
    }

    // Wizard de mudança de plano
    public function changePlan(): Response
    {
        return Inertia::render('tenant/admin/billing/plans/change', [
            'plans' => PlanComparisonResource::collection(
                Plan::active()->ordered()->get()
            ),
            'currentPlan' => new PlanResource(tenant()->plan),
            'subscription' => new SubscriptionResource(tenant()->subscription),
        ]);
    }

    // Catálogo de bundles (NOVO)
    public function bundles(): Response
    {
        return Inertia::render('tenant/admin/billing/bundles', [
            'bundles' => BundleCatalogResource::collection(
                AddonBundle::active()
                    ->forPlan(tenant()->plan)
                    ->with('addons')
                    ->ordered()
                    ->get()
            ),
            'purchased' => BundleSubscriptionResource::collection(
                tenant()->activeBundles()
            ),
        ]);
    }

    // Checkout unificado (NOVO)
    public function checkout(): Response
    {
        return Inertia::render('tenant/admin/billing/checkout', [
            'cart' => session('checkout_cart', []),
        ]);
    }

    // Processar checkout (NOVO)
    public function processCheckout(CheckoutRequest $request): HttpResponse
    {
        $items = $request->validated()['items'];

        // Criar sessão de checkout no Stripe
        $session = $this->checkoutService->createMultiItemCheckout(
            tenant(),
            $items,
            $request->validated()['billing_period']
        );

        return Inertia::location($session->url);
    }

    // ... existing methods
}
```

### 6.2 Novas Rotas

```php
// routes/tenant.php

Route::prefix('billing')->name('billing.')->group(function () {
    // Dashboard
    Route::get('/', [BillingController::class, 'index'])->name('index');

    // Plans
    Route::get('/plans', [BillingController::class, 'plans'])->name('plans');
    Route::get('/plans/change', [BillingController::class, 'changePlan'])->name('plans.change');
    Route::post('/plans/change', [BillingController::class, 'processPlanChange'])->name('plans.change.process');

    // Addons
    Route::get('/addons', [BillingController::class, 'addons'])->name('addons');
    Route::post('/addons/purchase', [BillingController::class, 'purchaseAddon'])->name('addons.purchase');
    Route::post('/addons/{addon}/cancel', [BillingController::class, 'cancelAddon'])->name('addons.cancel');

    // Bundles (NOVO)
    Route::get('/bundles', [BillingController::class, 'bundles'])->name('bundles');
    Route::post('/bundles/purchase', [BillingController::class, 'purchaseBundle'])->name('bundles.purchase');
    Route::post('/bundles/{bundle}/cancel', [BillingController::class, 'cancelBundle'])->name('bundles.cancel');

    // Checkout unificado (NOVO)
    Route::get('/checkout', [BillingController::class, 'checkout'])->name('checkout');
    Route::post('/checkout', [BillingController::class, 'processCheckout'])->name('checkout.process');
    Route::get('/checkout/success', [BillingController::class, 'checkoutSuccess'])->name('checkout.success');

    // Invoices
    Route::get('/invoices', [BillingController::class, 'invoices'])->name('invoices');
    Route::get('/invoices/{invoice}', [BillingController::class, 'downloadInvoice'])->name('invoices.download');

    // Usage
    Route::get('/usage', [BillingController::class, 'usage'])->name('usage');

    // Portal
    Route::get('/portal', [BillingController::class, 'portal'])->name('portal');
});
```

---

## 7. Páginas Novas/Refatoradas

### 7.1 Billing Dashboard (`/admin/billing`)

**Layout**:
```
┌─────────────────────────────────────────────────────────────────┐
│ Billing & Subscription                           [Manage Plan] │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │ Current Plan: Professional                    Status: Active │ │
│ │ Next billing: Dec 15, 2025                    $29/month     │ │
│ └─────────────────────────────────────────────────────────────┘ │
│                                                                 │
│ ┌───────────────────────┐ ┌───────────────────────────────────┐ │
│ │ Monthly Cost          │ │ Usage                             │ │
│ │                       │ │                                   │ │
│ │ Plan:      $29.00     │ │ Users:    ███████░░░  12/25       │ │
│ │ Addons:    $49.00     │ │ Storage:  ██████░░░░  15GB/50GB   │ │
│ │ ─────────────────     │ │ Projects: █████░░░░░  8/50        │ │
│ │ Total:     $78.00     │ │                                   │ │
│ └───────────────────────┘ └───────────────────────────────────┘ │
│                                                                 │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │ Quick Actions                                               │ │
│ │ [View Plans] [Browse Addons] [Browse Bundles] [Invoices]   │ │
│ └─────────────────────────────────────────────────────────────┘ │
│                                                                 │
│ Active Add-ons (2)                               [Manage →]     │
│ ┌─────────────────────┐ ┌─────────────────────┐                │
│ │ Storage 50GB        │ │ Extra Users (10)    │                │
│ │ $49/mo              │ │ $25/mo              │                │
│ └─────────────────────┘ └─────────────────────┘                │
│                                                                 │
│ Recent Invoices                                  [View All →]   │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │ Invoice #1234  │  Nov 15, 2025  │  $78.00  │  Paid  │ [↓]  │ │
│ │ Invoice #1233  │  Oct 15, 2025  │  $78.00  │  Paid  │ [↓]  │ │
│ └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

### 7.2 Plan Comparison (`/admin/billing/plans`)

**Layout**:
```
┌─────────────────────────────────────────────────────────────────┐
│ Choose Your Plan                     [Monthly ○ ● Yearly]       │
│ Save 20% with yearly billing                                    │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐                │
│ │  Starter    │ │Professional │ │ Enterprise  │                │
│ │             │ │  POPULAR    │ │             │                │
│ │   $9/mo     │ │   $29/mo    │ │   $99/mo    │                │
│ │             │ │             │ │             │                │
│ │ 5 users     │ │ 25 users    │ │ Unlimited   │                │
│ │ 10 projects │ │ 50 projects │ │ Unlimited   │                │
│ │ 5 GB        │ │ 50 GB       │ │ 500 GB      │                │
│ │             │ │             │ │             │                │
│ │ [Current]   │ │ [Upgrade]   │ │ [Upgrade]   │                │
│ └─────────────┘ └─────────────┘ └─────────────┘                │
│                                                                 │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │ Compare All Features                            [Expand ▼]  │ │
│ ├─────────────┬─────────┬─────────────┬────────────┤          │ │
│ │             │ Starter │Professional │ Enterprise │          │ │
│ ├─────────────┼─────────┼─────────────┼────────────┤          │ │
│ │ Users       │    5    │     25      │ Unlimited  │          │ │
│ │ Projects    │   10    │     50      │ Unlimited  │          │ │
│ │ Storage     │  5 GB   │    50 GB    │  500 GB    │          │ │
│ ├─────────────┼─────────┼─────────────┼────────────┤          │ │
│ │ Custom Roles│    ✗    │      ✓      │     ✓      │          │ │
│ │ API Access  │    ✗    │      ✓      │     ✓      │          │ │
│ │ Audit Log   │    ✗    │      ✓      │     ✓      │          │ │
│ │ SSO         │    ✗    │      ✗      │     ✓      │          │ │
│ │ White Label │    ✗    │      ✗      │     ✓      │          │ │
│ └─────────────┴─────────┴─────────────┴────────────┘          │ │
└─────────────────────────────────────────────────────────────────┘
```

### 7.3 Bundle Catalog (`/admin/billing/bundles`)

**Layout**:
```
┌─────────────────────────────────────────────────────────────────┐
│ Add-on Bundles                       [Monthly ○ ● Yearly]       │
│ Save more by purchasing add-ons together                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │ 📦 Growth Bundle                              BEST VALUE    │ │
│ │ Everything you need to scale your team                      │ │
│ │                                                             │ │
│ │ Includes:                                                   │ │
│ │ • Storage 50GB (+50GB)                                      │ │
│ │ • Extra Users (10 users)                                    │ │
│ │ • Advanced Reports                                          │ │
│ │                                                             │ │
│ │ ┌─────────────────┐  ┌────────────────────────────────────┐ │ │
│ │ │ $59/mo          │  │ Individual price: $99/mo           │ │ │
│ │ │ Save $40/mo     │  │ You save: 40%                      │ │ │
│ │ └─────────────────┘  └────────────────────────────────────┘ │ │
│ │                                                             │ │
│ │                                         [Add to Cart]       │ │
│ └─────────────────────────────────────────────────────────────┘ │
│                                                                 │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │ 🏢 Enterprise Bundle                                        │ │
│ │ Complete enterprise feature set                             │ │
│ │ ...                                                         │ │
│ └─────────────────────────────────────────────────────────────┘ │
│                                                                 │
│ Or purchase add-ons individually →                              │
└─────────────────────────────────────────────────────────────────┘
```

### 7.4 Checkout Sheet

Sheet lateral que aparece ao adicionar itens:

```
┌──────────────────────────────────────┐
│ Your Cart (2 items)            [×]   │
├──────────────────────────────────────┤
│                                      │
│ Billing Period: [Monthly ▼]          │
│                                      │
│ ┌──────────────────────────────────┐ │
│ │ Growth Bundle           [$59/mo] │ │
│ │ 📦 3 add-ons included            │ │
│ │                             [×]  │ │
│ └──────────────────────────────────┘ │
│                                      │
│ ┌──────────────────────────────────┐ │
│ │ Extra Projects (50)     [$25/mo] │ │
│ │ [-] 2 [+]                        │ │
│ │                             [×]  │ │
│ └──────────────────────────────────┘ │
│                                      │
├──────────────────────────────────────┤
│ Subtotal:                   $109.00  │
│ Bundle Discount:            -$40.00  │
│ ────────────────────────────────     │
│ Total (monthly):             $69.00  │
│                                      │
│ ┌──────────────────────────────────┐ │
│ │     Continue to Checkout →       │ │
│ └──────────────────────────────────┘ │
│                                      │
│ 🔒 Secure checkout powered by Stripe │
└──────────────────────────────────────┘
```

---

## 8. Migração de Componentes

### 8.1 Mapeamento Atual → Novo

| Componente Atual | Novo Componente | Notas |
|-----------------|-----------------|-------|
| `tenant/addons/addon-card.tsx` | `billing/addons/addon-catalog-card.tsx` | Refatorar para usar primitivos |
| `tenant/addons/active-addon-card.tsx` | `billing/addons/addon-active-card.tsx` | Adicionar ações inline |
| `tenant/addons/purchase-modal.tsx` | `billing/checkout/checkout-summary.tsx` | Substituir por sheet |
| `tenant/addons/quantity-selector.tsx` | `billing/addons/addon-quantity-input.tsx` | Manter, melhorar estilos |
| `tenant/addons/billing-period-toggle.tsx` | `billing/primitives/pricing-toggle.tsx` | Generalizar |
| `tenant/addons/usage-meter.tsx` | `billing/primitives/usage-progress.tsx` | Generalizar |
| - | `billing/plans/plan-card.tsx` | Novo |
| - | `billing/plans/plan-comparison-table.tsx` | Novo |
| - | `billing/bundles/bundle-card.tsx` | Novo |

### 8.2 Estratégia de Migração

1. **Fase 1**: Criar novos componentes em `billing/` sem remover antigos
2. **Fase 2**: Migrar páginas uma a uma para usar novos componentes
3. **Fase 3**: Criar testes de regressão visual
4. **Fase 4**: Remover componentes antigos em `tenant/addons/`
5. **Fase 5**: Adicionar alias para importações antigas (backwards compat)

---

## 9. Testes

### 9.1 Testes de Componentes

```bash
# Rodar testes de componentes com Vitest
npm run test:components

# Testes específicos
npm run test:components -- --filter=PricingCard
npm run test:components -- --filter=PlanComparison
```

### 9.2 Testes E2E

```bash
# Testes de fluxo de checkout
npm run test:e2e -- tests/e2e/billing/checkout.spec.ts

# Testes de bundle purchase
npm run test:e2e -- tests/e2e/billing/bundles.spec.ts
```

### 9.3 Cenários de Teste

1. **Plan Upgrade Flow**
   - Usuário visualiza comparação de planos
   - Seleciona plano superior
   - Vê preview de proration
   - Confirma mudança
   - Redirecionado ao Stripe
   - Retorna com sucesso

2. **Bundle Purchase Flow**
   - Usuário navega para bundles
   - Adiciona bundle ao carrinho
   - Visualiza checkout summary
   - Altera billing period
   - Processa checkout
   - Bundle aparece em "Active"

3. **Multi-Item Checkout**
   - Usuário adiciona addon
   - Adiciona bundle
   - Remove item do carrinho
   - Ajusta quantidade
   - Checkout completo

---

## 10. Cronograma Sugerido

### Fase 1: Foundation (Primitivos + Types)
- [ ] Criar estrutura de pastas `billing/`
- [ ] Implementar primitivos: `pricing-card`, `pricing-toggle`, `feature-list`, `price-display`
- [ ] Criar novos types em `billing.d.ts`
- [ ] Implementar hooks: `useBillingPeriod`, `useCheckout`

### Fase 2: Plans
- [ ] Implementar `plan-card.tsx`
- [ ] Implementar `plan-comparison-table.tsx`
- [ ] Criar página `/billing/plans`
- [ ] Implementar `PlanComparisonResource`

### Fase 3: Bundles
- [ ] Implementar `bundle-card.tsx`
- [ ] Implementar `bundle-contents.tsx`, `bundle-savings.tsx`
- [ ] Criar página `/billing/bundles`
- [ ] Implementar `BundleCatalogResource`
- [ ] Implementar `useBundles` hook

### Fase 4: Checkout
- [ ] Implementar `checkout-summary.tsx` (sheet)
- [ ] Implementar `checkout-line-item.tsx`, `checkout-totals.tsx`
- [ ] Criar `CheckoutContext` com Zustand
- [ ] Backend: `processCheckout` multi-item

### Fase 5: Dashboard
- [ ] Implementar `subscription-overview.tsx`
- [ ] Implementar `cost-breakdown.tsx`, `usage-summary.tsx`
- [ ] Refatorar página `/billing` principal
- [ ] Implementar `SubscriptionOverviewResource`

### Fase 6: Migration & Cleanup
- [ ] Migrar addons para usar novos componentes
- [ ] Remover traduções antigas
- [ ] Remover controller antigos
- [ ] Remover componentes antigos
- [ ] Testes E2E completos
- [ ] Documentação atualizada

---

## 11. Considerações Técnicas

### 11.1 Performance

- Usar `React.memo` em cards de catálogo
- Lazy load páginas de billing
- Skeleton loaders durante fetch
- Otimistic updates no carrinho

### 11.2 Acessibilidade

- Todos os componentes devem ter ARIA labels
- Navegação por teclado no catálogo
- Focus trap no checkout sheet
- Anúncios de screen reader para ações

### 11.3 Internacionalização

- Todos os textos via `useLaravelReactI18n()`
- Formatação de preços com `Intl.NumberFormat`
- RTL support nos componentes de pricing

### 11.4 State Management

- Zustand para carrinho (persistido)
- React Query para dados do servidor
- Context para billing period (local)

---

## 12. Decisões de Design

### 12.1 Checkout: Stripe Redirect ✅
**Decisão**: Manter redirect para Stripe Checkout.
- Mais simples de implementar e manter
- Stripe cuida de toda a UI de pagamento
- Não requer certificação PCI adicional
- Experiência já familiar para usuários

### 12.2 Escopo: Implementação Completa (6 Fases) ✅
**Decisão**: Implementar todas as 6 fases do plano.
- Fase 1: Foundation (Primitivos + Types)
- Fase 2: Plans (Cards + Comparison)
- Fase 3: Bundles (Catalog + UI)
- Fase 4: Checkout (Unified Cart)
- Fase 5: Dashboard (Overview)
- Fase 6: Migration & Cleanup

### 12.3 Conflitos Bundle + Addon: Avisar e Substituir ✅
**Decisão**: Mostrar aviso e oferecer substituir addon avulso pelo bundle.

**Implementação**:
```tsx
// Quando usuário tenta adicionar bundle que contém addon ativo
const conflictingAddons = bundle.addons.filter(
    addon => activeAddons.some(a => a.slug === addon.slug)
);

if (conflictingAddons.length > 0) {
    showConflictDialog({
        title: "You already have some of these add-ons",
        message: "The bundle includes add-ons you already have. Would you like to replace them?",
        conflicting: conflictingAddons,
        savings: calculateReplacementSavings(conflictingAddons, bundle),
        actions: {
            replace: () => replaceWithBundle(bundle, conflictingAddons),
            cancel: () => closeDialog(),
        }
    });
}
```

### 12.4 Perguntas Restantes

1. **Carrinho Persistido**: Salvar carrinho no localStorage ou no banco de dados?
   - Recomendação: localStorage com fallback para sessão

2. **Proration UI**: Mostrar preview de proration antes do checkout ou deixar o Stripe calcular?
   - Recomendação: Mostrar estimativa com disclaimer "valor final calculado no checkout"

3. **Downgrade Flow**: Wizard especial para downgrade com warnings sobre features que serão perdidas?
   - Recomendação: Sim, mostrar claramente o que será perdido

4. **Free Trial**: UI específica para período de trial e conversão?
   - Recomendação: Banner no dashboard + modal de conversão

---

## 13. Referências

- [shadcn/ui Pricing Examples](https://ui.shadcn.com/blocks)
- [Stripe Checkout Best Practices](https://stripe.com/docs/payments/checkout)
- [Laravel Cashier Documentation](https://laravel.com/docs/billing)
- [Inertia.js Forms](https://inertiajs.com/forms)
