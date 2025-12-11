import { Head, router } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { type ReactElement, useState, useMemo } from 'react';
import AdminLayout from '@/layouts/tenant/admin-layout';
import admin from '@/routes/tenant/admin';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import {
    Page,
    PageHeader,
    PageHeaderContent,
    PageTitle,
    PageDescription,
    PageContent,
    PageHeaderActions,
} from '@/components/shared/layout/page';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
    Puzzle,
    Package,
    CheckCircle2,
    AlertCircle,
    CreditCard,
} from 'lucide-react';
import {
    AddonCard,
    ActiveAddonCard,
    BundleCard,
    PricingToggle,
    CheckoutCartButton,
    CheckoutSheet,
} from '@/components/shared/billing';
import { BillingPeriodProvider, useBillingPeriod, useCheckout, createCheckoutItem } from '@/hooks/billing';
import type { BreadcrumbItem, AddonResource, BundleResource, BillingPeriod } from '@/types';

interface ActiveAddon {
    id: string;
    slug: string;
    name: string;
    description?: string;
    type: string;
    quantity: number;
    price: number;
    formattedPrice: string;
    totalPrice: number;
    formattedTotalPrice: string;
    billingPeriod: string;
    status: string;
    startedAt: string | null;
    expiresAt: string | null;
}

interface ActiveBundle {
    purchase_id: string;
    bundle_slug: string | null;
    bundle_name: string | null;
    addons: Array<{ name: string; slug: string }>;
    addon_count: number;
    total_monthly: number;
    started_at: string | null;
}

interface AddonsPageProps {
    availableAddons: AddonResource[];
    activeAddons: ActiveAddon[];
    availableBundles: BundleResource[];
    activeBundles: ActiveBundle[];
    monthlyCost: number;
    formattedMonthlyCost: string;
    activeAddonSlugs: string[];
    [key: string]: unknown;
}

function AddonsPageContent({
    availableAddons,
    activeAddons,
    availableBundles,
    activeBundles,
    formattedMonthlyCost,
    activeAddonSlugs,
}: AddonsPageProps) {
    const { t } = useLaravelReactI18n();
    const { period, setPeriod } = useBillingPeriod();
    const { items, addItem, removeItem, updateQuantity, clearCart, hasProduct, total } = useCheckout();
    const [purchasingAddon, setPurchasingAddon] = useState<string | null>(null);
    const [cancelingAddon, setCancelingAddon] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [isCartOpen, setIsCartOpen] = useState(false);

    // Check if cart has recurring items (for payment method filtering)
    const hasRecurringItems = items.some((item) => item.isRecurring);

    // Cart item count
    const cartItemCount = items.reduce((sum, item) => sum + item.quantity, 0);
    const cartTotal = total;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('dashboard.page.title'), href: admin.dashboard.url() },
        { title: t('billing.page.title'), href: admin.billing.index.url() },
        { title: t('addons.title', { default: 'Add-ons' }), href: admin.addons.index.url() },
    ];

    useSetBreadcrumbs(breadcrumbs);

    // Add addon to cart
    const handleAddAddonToCart = (slug: string, quantity: number) => {
        const addon = availableAddons.find((a) => a.slug === slug);
        if (!addon) return;

        const isRecurring = addon.price_one_time === null;

        // Handle one-time purchases separately
        if (!isRecurring) {
            const oneTimePricing = {
                price: addon.price_one_time ?? 0,
                formattedPrice: addon.formatted_price_one_time || `$${((addon.price_one_time ?? 0) / 100).toFixed(2)}`,
            };

            addItem(
                createCheckoutItem(
                    {
                        id: addon.id,
                        type: 'addon',
                        slug: addon.slug,
                        name: addon.name,
                        description: addon.description ?? undefined,
                    },
                    oneTimePricing,
                    quantity,
                    'one_time' as BillingPeriod,
                    false // not recurring
                    // No pricingByPeriod for one-time purchases
                )
            );
            return;
        }

        // Recurring add-ons have monthly/yearly pricing
        const monthlyPricing = {
            price: addon.price_monthly ?? 0,
            formattedPrice: addon.formatted_price_monthly || `$${((addon.price_monthly ?? 0) / 100).toFixed(2)}`,
        };
        const yearlyPricing = {
            price: addon.price_yearly ?? 0,
            formattedPrice: addon.formatted_price_yearly || `$${((addon.price_yearly ?? 0) / 100).toFixed(2)}`,
        };

        const pricing = period === 'yearly' && addon.price_yearly ? yearlyPricing : monthlyPricing;

        addItem(
            createCheckoutItem(
                {
                    id: addon.id,
                    type: 'addon',
                    slug: addon.slug,
                    name: addon.name,
                    description: addon.description ?? undefined,
                },
                pricing,
                quantity,
                period,
                true, // recurring
                { monthly: monthlyPricing, yearly: yearlyPricing }
            )
        );
    };

    // Add bundle to cart
    const handleAddBundleToCart = (slug: string) => {
        const bundle = availableBundles.find((b) => b.slug === slug);
        if (!bundle) return;

        const monthlyPricing = {
            price: bundle.price_monthly_effective ?? 0,
            formattedPrice: `$${((bundle.price_monthly_effective ?? 0) / 100).toFixed(2)}`,
        };
        const yearlyPricing = {
            price: bundle.price_yearly_effective ?? 0,
            formattedPrice: `$${((bundle.price_yearly_effective ?? 0) / 100).toFixed(2)}`,
        };

        const pricing = period === 'yearly' ? yearlyPricing : monthlyPricing;

        addItem(
            createCheckoutItem(
                {
                    id: bundle.id,
                    type: 'bundle',
                    slug: bundle.slug,
                    name: typeof bundle.name === 'string' ? bundle.name : bundle.name_display || '',
                    description:
                        typeof bundle.description === 'string'
                            ? bundle.description
                            : '',
                },
                pricing,
                1,
                period,
                true,
                { monthly: monthlyPricing, yearly: yearlyPricing }
            )
        );
    };

    // Direct purchase addon (existing flow)
    const handlePurchaseAddon = (slug: string, quantity: number) => {
        setPurchasingAddon(slug);
        setError(null);

        router.post(admin.addons.purchase.url(), {
            addon_slug: slug,
            quantity,
            billing_period: period,
        }, {
            onFinish: () => setPurchasingAddon(null),
            onError: (errors) => {
                setError(errors.addon || t('common.error', { default: 'An error occurred' }));
            },
        });
    };

    // Direct purchase bundle (existing flow)
    const handlePurchaseBundle = (slug: string) => {
        setPurchasingAddon(slug);
        setError(null);

        router.post(admin.addons.purchase.url(), {
            type: 'bundle',
            slug,
            billing_period: period,
        }, {
            onFinish: () => setPurchasingAddon(null),
            onError: (errors) => {
                setError(errors.addon || t('common.error', { default: 'An error occurred' }));
            },
        });
    };

    const handleCancelAddon = (addonId: string) => {
        setCancelingAddon(addonId);
        setError(null);

        router.post(admin.addons.cancel.url({ addon: addonId }), {}, {
            onFinish: () => setCancelingAddon(null),
            onError: (errors) => {
                setError(errors.addon || t('common.error', { default: 'An error occurred' }));
            },
        });
    };

    // Check which addons are already owned
    const ownedAddonSlugs = useMemo(() => new Set(activeAddonSlugs), [activeAddonSlugs]);

    // Get quantity for each owned addon
    const addonQuantities = useMemo(() => {
        const quantities: Record<string, number> = {};
        activeAddons.forEach((addon) => {
            quantities[addon.slug] = (quantities[addon.slug] || 0) + addon.quantity;
        });
        return quantities;
    }, [activeAddons]);

    // Check which bundles have conflicts
    const bundleConflicts = useMemo(() => {
        const conflicts: Record<string, string[]> = {};
        availableBundles.forEach((bundle) => {
            const conflictingAddons = bundle.addons
                ?.filter((addon) => ownedAddonSlugs.has(addon.slug))
                .map((addon) => addon.name) ?? [];
            if (conflictingAddons.length > 0) {
                conflicts[bundle.slug] = conflictingAddons;
            }
        });
        return conflicts;
    }, [availableBundles, ownedAddonSlugs]);

    // Active bundle slugs
    const activeBundleSlugs = useMemo(() => {
        return new Set(activeBundles.map((b) => b.bundle_slug).filter(Boolean));
    }, [activeBundles]);

    const hasActiveSubscriptions = activeAddons.length > 0 || activeBundles.length > 0;

    return (
        <>
            <Head title={t('addons.title', { default: 'Add-ons' })} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle>
                            <Puzzle className="mr-2 h-6 w-6" />
                            {t('addons.title', { default: 'Add-ons' })}
                        </PageTitle>
                        <PageDescription>
                            {t('addons.description', {
                                default: 'Extend your capabilities with additional features and quotas',
                            })}
                        </PageDescription>
                    </PageHeaderContent>
                    <PageHeaderActions>
                        <CheckoutCartButton
                            itemCount={cartItemCount}
                            total={cartTotal}
                            currency="BRL"
                            onClick={() => setIsCartOpen(true)}
                            variant={cartItemCount > 0 ? 'default' : 'outline'}
                        />
                        <Button variant="outline" onClick={() => router.visit(admin.billing.index.url())}>
                            <CreditCard className="mr-2 h-4 w-4" />
                            {t('billing.title', { default: 'Billing' })}
                        </Button>
                    </PageHeaderActions>
                </PageHeader>

                <PageContent className="space-y-8">
                    {/* Error Alert */}
                    {error && (
                        <Alert variant="destructive">
                            <AlertCircle className="h-4 w-4" />
                            <AlertDescription>{error}</AlertDescription>
                        </Alert>
                    )}

                    {/* Active Subscriptions Summary */}
                    {hasActiveSubscriptions && (
                        <Card>
                            <CardHeader className="pb-2">
                                <div className="flex items-center justify-between">
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <CheckCircle2 className="h-4 w-4 text-green-500" />
                                        {t('addons.active_subscriptions', { default: 'Active Subscriptions' })}
                                    </CardTitle>
                                    <Badge variant="secondary" className="text-sm">
                                        {formattedMonthlyCost}
                                        <span className="text-muted-foreground ml-1">
                                            /{t('billing.month', { default: 'mo' })}
                                        </span>
                                    </Badge>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                    {/* Active Bundles */}
                                    {activeBundles.map((bundle) => (
                                        <div
                                            key={bundle.purchase_id}
                                            className="flex items-center justify-between rounded-lg border p-3"
                                        >
                                            <div className="flex items-center gap-3">
                                                <Package className="text-muted-foreground h-5 w-5" />
                                                <div>
                                                    <p className="font-medium">{bundle.bundle_name}</p>
                                                    <p className="text-muted-foreground text-xs">
                                                        {bundle.addon_count} {t('addons.addons_included', { default: 'add-ons' })}
                                                    </p>
                                                </div>
                                            </div>
                                            <Badge variant="outline">
                                                ${(bundle.total_monthly / 100).toFixed(2)}
                                            </Badge>
                                        </div>
                                    ))}

                                    {/* Active Individual Addons */}
                                    {activeAddons.map((addon) => (
                                        <ActiveAddonCard
                                            key={addon.id}
                                            addon={addon}
                                            onCancel={handleCancelAddon}
                                            isLoading={cancelingAddon === addon.id}
                                        />
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Billing Period Toggle */}
                    <div className="flex justify-center">
                        <PricingToggle
                            value={period}
                            onChange={setPeriod}
                            savings={t('billing.price.yearly_savings', { default: 'Save 20%' })}
                        />
                    </div>

                    {/* Tabs for Addons and Bundles */}
                    <Tabs defaultValue="addons" className="w-full">
                        <TabsList className="grid w-full max-w-md grid-cols-2">
                            <TabsTrigger value="addons" className="gap-2">
                                <Puzzle className="h-4 w-4" />
                                {t('addons.individual', { default: 'Individual Add-ons' })}
                            </TabsTrigger>
                            <TabsTrigger value="bundles" className="gap-2">
                                <Package className="h-4 w-4" />
                                {t('addons.bundles', { default: 'Bundles' })}
                                {availableBundles.length > 0 && (
                                    <Badge variant="secondary" className="ml-1">
                                        {t('billing.price.save', { default: 'Save' })}
                                    </Badge>
                                )}
                            </TabsTrigger>
                        </TabsList>

                        {/* Individual Addons Tab */}
                        <TabsContent value="addons" className="mt-6">
                            {availableAddons.length === 0 ? (
                                <Card>
                                    <CardContent className="py-12 text-center">
                                        <Puzzle className="text-muted-foreground mx-auto mb-4 h-12 w-12" />
                                        <h3 className="text-lg font-semibold">
                                            {t('addons.no_addons', { default: 'No add-ons available' })}
                                        </h3>
                                        <p className="text-muted-foreground mt-2">
                                            {t('addons.no_addons_description', {
                                                default: 'There are no add-ons available for your current plan.',
                                            })}
                                        </p>
                                    </CardContent>
                                </Card>
                            ) : (
                                <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                                    {availableAddons.map((addon) => (
                                        <AddonCard
                                            key={addon.slug}
                                            addon={addon}
                                            billingPeriod={period}
                                            isPurchased={ownedAddonSlugs.has(addon.slug)}
                                            isInCart={hasProduct(addon.slug)}
                                            currentQuantity={addonQuantities[addon.slug] || 0}
                                            isLoading={purchasingAddon === addon.slug}
                                            onAddToCart={handleAddAddonToCart}
                                            onPurchase={handlePurchaseAddon}
                                        />
                                    ))}
                                </div>
                            )}
                        </TabsContent>

                        {/* Bundles Tab */}
                        <TabsContent value="bundles" className="mt-6">
                            {availableBundles.length === 0 ? (
                                <Card>
                                    <CardContent className="py-12 text-center">
                                        <Package className="text-muted-foreground mx-auto mb-4 h-12 w-12" />
                                        <h3 className="text-lg font-semibold">
                                            {t('addons.no_bundles', { default: 'No bundles available' })}
                                        </h3>
                                        <p className="text-muted-foreground mt-2">
                                            {t('addons.no_bundles_description', {
                                                default: 'There are no bundles available for your current plan.',
                                            })}
                                        </p>
                                    </CardContent>
                                </Card>
                            ) : (
                                <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                                    {availableBundles.map((bundle) => {
                                        const isPurchased = activeBundleSlugs.has(bundle.slug);
                                        const conflicts = bundleConflicts[bundle.slug] ?? [];
                                        const hasConflicts = conflicts.length > 0;

                                        return (
                                            <BundleCard
                                                key={bundle.slug}
                                                bundle={bundle}
                                                billingPeriod={period}
                                                isPurchased={isPurchased}
                                                isInCart={hasProduct(bundle.slug)}
                                                hasConflicts={hasConflicts}
                                                conflictMessage={hasConflicts
                                                    ? t('addons.bundle_conflict', {
                                                        default: 'You already own: :addons',
                                                        addons: conflicts.join(', '),
                                                    }).replace(':addons', conflicts.join(', '))
                                                    : undefined
                                                }
                                                isLoading={purchasingAddon === bundle.slug}
                                                onAddToCart={() => handleAddBundleToCart(bundle.slug)}
                                                onPurchase={() => handlePurchaseBundle(bundle.slug)}
                                            />
                                        );
                                    })}
                                </div>
                            )}
                        </TabsContent>
                    </Tabs>
                </PageContent>
            </Page>

            {/* Checkout Cart Sheet - Redirects to dedicated checkout page */}
            <CheckoutSheet
                open={isCartOpen}
                onOpenChange={setIsCartOpen}
                items={items}
                billingPeriod={period}
                onBillingPeriodChange={(p) => setPeriod(p as 'monthly' | 'yearly')}
                onRemoveItem={removeItem}
                onUpdateQuantity={updateQuantity}
                onClearCart={clearCart}
                currency="BRL"
                showBillingToggle={true}
                yearlySavings={t('billing.price.yearly_savings', { default: 'Save 20%' })}
            />
        </>
    );
}

function AddonsIndex(props: AddonsPageProps) {
    return (
        <BillingPeriodProvider>
            <AddonsPageContent {...props} />
        </BillingPeriodProvider>
    );
}

AddonsIndex.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default AddonsIndex;
