import { cn } from '@/lib/utils';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Shield, RefreshCw, FileText, Lock, CreditCard } from 'lucide-react';

export interface CheckoutPoliciesSectionProps {
    /** Additional className */
    className?: string;
}

/**
 * CheckoutPoliciesSection - Trust badges and policies for dedicated checkout page
 *
 * Displays security indicators, refund policy, and terms agreement.
 *
 * @example
 * <CheckoutPoliciesSection />
 */
export function CheckoutPoliciesSection({ className }: CheckoutPoliciesSectionProps) {
    const { t } = useLaravelReactI18n();

    const policies = [
        {
            icon: Shield,
            text: t('checkout.secure_payment', { default: '100% secure payment' }),
            color: 'text-green-600 dark:text-green-400',
        },
        {
            icon: Lock,
            text: t('checkout.encrypted_data', { default: 'Encrypted data' }),
            color: 'text-blue-600 dark:text-blue-400',
        },
        {
            icon: RefreshCw,
            text: t('checkout.cancel_anytime', { default: 'Cancel anytime' }),
            color: 'text-purple-600 dark:text-purple-400',
        },
    ];

    return (
        <div className={cn('space-y-4', className)}>
            {/* Trust badges */}
            <div className="flex flex-wrap items-center gap-4">
                {policies.map((policy, index) => (
                    <div
                        key={index}
                        className="flex items-center gap-1.5 text-sm text-muted-foreground"
                    >
                        <policy.icon className={cn('h-4 w-4', policy.color)} />
                        <span>{policy.text}</span>
                    </div>
                ))}
            </div>

            {/* Payment processors */}
            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                <CreditCard className="h-3.5 w-3.5" />
                <span>
                    {t('checkout.powered_by', {
                        default: 'Payments powered by Stripe & Asaas',
                    })}
                </span>
            </div>

            {/* Terms agreement */}
            <p className="text-xs text-muted-foreground">
                <FileText className="inline h-3 w-3 mr-1" />
                {t('checkout.terms_agreement', {
                    default:
                        'By completing this purchase, you agree to our Terms of Service and Privacy Policy.',
                })}
            </p>
        </div>
    );
}
