import { cn } from '@/lib/utils';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { CheckCircle2, Check, Sparkles } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { CheckoutItem } from '@/types/billing';

export interface CheckoutBenefitsSectionProps {
    /** Items in the checkout */
    items: CheckoutItem[];
    /** Additional className */
    className?: string;
}

/**
 * CheckoutBenefitsSection - Benefits list for dedicated checkout page
 *
 * Aggregates and displays benefits/features from all items in the cart.
 *
 * @example
 * <CheckoutBenefitsSection items={items} />
 */
export function CheckoutBenefitsSection({ items, className }: CheckoutBenefitsSectionProps) {
    const { t } = useLaravelReactI18n();

    // Aggregate benefits from all items
    // Each item's product can have a features array
    const allBenefits = items.flatMap((item) => {
        const product = item.product;
        const benefits: string[] = [];

        // Add product-specific benefits
        if ('features' in product && Array.isArray(product.features)) {
            benefits.push(...product.features);
        }

        // Add quantity-based benefits for addons
        if (product.type === 'addon' && item.quantity > 1) {
            const quantityBenefit = t('checkout.summary.quantity_benefit', {
                default: ':quantity units of :name',
                quantity: item.quantity,
                name: product.name,
            })
                .replace(':quantity', String(item.quantity))
                .replace(':name', product.name);
            benefits.push(quantityBenefit);
        }

        // Add bundle benefits
        if (product.type === 'bundle' && 'addons' in product && Array.isArray(product.addons)) {
            const bundleAddons = product.addons
                .map((addon: { name: string }) => addon.name)
                .join(', ');
            benefits.push(
                t('checkout.summary.includes_addons', {
                    default: 'Includes: :addons',
                    addons: bundleAddons,
                }).replace(':addons', bundleAddons)
            );
        }

        return benefits;
    });

    // Remove duplicates
    const uniqueBenefits = [...new Set(allBenefits)];

    // If no benefits found, show generic benefits
    if (uniqueBenefits.length === 0) {
        const genericBenefits = [
            t('checkout.benefit.instant_access', { default: 'Instant access after purchase' }),
            t('checkout.benefit.cancel_anytime', { default: 'Cancel anytime' }),
            t('checkout.benefit.support', { default: 'Priority support included' }),
        ];
        uniqueBenefits.push(...genericBenefits);
    }

    return (
        <Card className={cn('', className)}>
            <CardHeader className="pb-4">
                <CardTitle className="flex items-center gap-2 text-lg">
                    <CheckCircle2 className="h-5 w-5 text-green-500" />
                    {t('checkout.summary.what_you_get', { default: "What You'll Get" })}
                </CardTitle>
            </CardHeader>

            <CardContent>
                <ul className="space-y-3">
                    {uniqueBenefits.slice(0, 8).map((benefit, index) => (
                        <li key={index} className="flex items-start gap-3">
                            <div className="rounded-full bg-green-100 dark:bg-green-900/30 p-0.5 mt-0.5">
                                <Check className="h-3 w-3 text-green-600 dark:text-green-400" />
                            </div>
                            <span className="text-sm">{benefit}</span>
                        </li>
                    ))}
                </ul>

                {/* Additional premium indicator */}
                {items.some((item) => item.product.type === 'bundle') && (
                    <div className="mt-4 flex items-center gap-2 rounded-lg bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-950/30 dark:to-orange-950/30 p-3 text-sm">
                        <Sparkles className="h-4 w-4 text-amber-500" />
                        <span className="text-amber-700 dark:text-amber-400">
                            {t('checkout.summary.bundle_savings', {
                                default: 'Bundle discount applied automatically',
                            })}
                        </span>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
