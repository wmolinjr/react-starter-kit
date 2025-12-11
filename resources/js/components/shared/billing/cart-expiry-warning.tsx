import { Clock, AlertTriangle } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useCheckoutSafe } from '@/hooks/billing';
import { useLaravelReactI18n } from 'laravel-react-i18n';

interface CartExpiryWarningProps {
    /** Threshold in minutes to show warning (default: 60) */
    warningThreshold?: number;
    /** Additional className */
    className?: string;
    /** Compact mode for inline display */
    compact?: boolean;
}

/**
 * CartExpiryWarning - Shows warning when cart is about to expire
 *
 * This component displays a warning when the cart will expire soon,
 * encouraging users to complete their purchase.
 *
 * @example
 * // In checkout sheet or billing page
 * <CartExpiryWarning />
 *
 * @example
 * // Compact mode for inline display
 * <CartExpiryWarning compact warningThreshold={30} />
 */
export function CartExpiryWarning({
    warningThreshold = 60,
    className,
    compact = false,
}: CartExpiryWarningProps) {
    const { t } = useLaravelReactI18n();
    const { expiresInMinutes, hasItems } = useCheckoutSafe();

    // Don't show if cart is empty or not expiring soon
    if (!hasItems || expiresInMinutes === null || expiresInMinutes > warningThreshold) {
        return null;
    }

    const isUrgent = expiresInMinutes < 15;

    // Format time remaining
    const formatTimeRemaining = (): string => {
        if (expiresInMinutes < 1) {
            return t('billing.subscription.expires_soon', { default: 'Expires soon' });
        }
        if (expiresInMinutes < 60) {
            return t('billing.subscription.expires_minutes', {
                default: 'Expires in :minutes min',
                minutes: expiresInMinutes,
            });
        }
        const hours = Math.floor(expiresInMinutes / 60);
        const mins = expiresInMinutes % 60;
        return t('billing.subscription.expires_hours', {
            default: 'Expires in :hours h :minutes min',
            hours,
            minutes: mins,
        });
    };

    if (compact) {
        return (
            <div
                className={cn(
                    'flex items-center gap-1.5 text-xs',
                    isUrgent ? 'text-red-600' : 'text-amber-600',
                    className
                )}
            >
                <Clock className="h-3 w-3" />
                <span>{formatTimeRemaining()}</span>
            </div>
        );
    }

    return (
        <Alert
            variant={isUrgent ? 'destructive' : 'default'}
            className={cn(
                isUrgent ? '' : 'border-amber-200 bg-amber-50 text-amber-800',
                className
            )}
        >
            {isUrgent ? (
                <AlertTriangle className="h-4 w-4" />
            ) : (
                <Clock className="h-4 w-4" />
            )}
            <AlertDescription className="ml-2">
                {isUrgent
                    ? t('billing.cart.expiring_urgent', {
                          default:
                              'Your cart will expire soon! Complete your purchase to avoid losing your items.',
                      })
                    : t('billing.cart.expiring', {
                          default:
                              'Your cart will expire in :minutes minutes. Complete your purchase to save your items.',
                          minutes: expiresInMinutes,
                      })}
            </AlertDescription>
        </Alert>
    );
}
