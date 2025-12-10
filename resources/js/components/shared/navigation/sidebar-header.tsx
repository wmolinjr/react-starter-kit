import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Breadcrumbs } from '@/components/shared/navigation/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { CheckoutCartButton, CheckoutSheet } from '@/components/shared/billing';
import { useCheckoutSafe, useBillingPeriodSafe } from '@/hooks/billing';
import admin from '@/routes/tenant/admin';
import { type BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const checkout = useCheckoutSafe();
    const { period, setPeriod } = useBillingPeriodSafe();
    const [isCheckingOut, setIsCheckingOut] = useState(false);

    const handleCheckout = () => {
        if (!checkout.hasItems) return;

        setIsCheckingOut(true);
        router.post(
            admin.billing.cartCheckout.url(),
            {
                items: checkout.items.map((item) => ({
                    type: item.product.type,
                    slug: item.product.slug,
                    quantity: item.quantity,
                    billing_period: item.billingPeriod,
                })),
            },
            {
                onFinish: () => setIsCheckingOut(false),
                onSuccess: () => checkout.clearCart(),
                onError: () => setIsCheckingOut(false),
            }
        );
    };

    const handleBillingPeriodChange = (newPeriod: string) => {
        if (newPeriod === 'monthly' || newPeriod === 'yearly') {
            setPeriod(newPeriod);
            checkout.setDefaultBillingPeriod(newPeriod);
        }
    };

    return (
        <header className="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>

            {/* Cart Button - right side */}
            <div className="ml-auto flex items-center gap-2">
                <CheckoutCartButton
                    itemCount={checkout.itemCount}
                    total={checkout.total}
                    onClick={checkout.open}
                    size="icon"
                    variant="ghost"
                />
            </div>

            {/* Checkout Sheet */}
            <CheckoutSheet
                open={checkout.isOpen}
                onOpenChange={(open) => !open && checkout.close()}
                items={checkout.items}
                billingPeriod={period}
                onBillingPeriodChange={handleBillingPeriodChange}
                onRemoveItem={checkout.removeItem}
                onUpdateQuantity={checkout.updateQuantity}
                onClearCart={checkout.clearCart}
                onCheckout={handleCheckout}
                isCheckingOut={isCheckingOut}
            />
        </header>
    );
}
