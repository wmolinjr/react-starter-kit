import { cn } from '@/lib/utils';
import { Badge } from '@/components/ui/badge';
import { Check, Lock, Sparkles, Crown, Zap } from 'lucide-react';

export type FeatureBadgeVariant =
    | 'included'
    | 'addon'
    | 'premium'
    | 'enterprise'
    | 'coming-soon'
    | 'new';

interface FeatureBadgeProps {
    variant: FeatureBadgeVariant;
    label?: string;
    className?: string;
}

/**
 * FeatureBadge - Badge indicating feature availability status
 *
 * @example
 * <FeatureBadge variant="included" />
 * <FeatureBadge variant="addon" label="Add-on" />
 * <FeatureBadge variant="premium" />
 * <FeatureBadge variant="enterprise" />
 */
export function FeatureBadge({ variant, label, className }: FeatureBadgeProps) {
    const config: Record<
        FeatureBadgeVariant,
        {
            icon: typeof Check;
            defaultLabel: string;
            className: string;
        }
    > = {
        included: {
            icon: Check,
            defaultLabel: 'Included',
            className: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        },
        addon: {
            icon: Zap,
            defaultLabel: 'Add-on',
            className: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        },
        premium: {
            icon: Sparkles,
            defaultLabel: 'Premium',
            className: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
        },
        enterprise: {
            icon: Crown,
            defaultLabel: 'Enterprise',
            className: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        },
        'coming-soon': {
            icon: Lock,
            defaultLabel: 'Coming Soon',
            className: 'bg-muted text-muted-foreground',
        },
        new: {
            icon: Sparkles,
            defaultLabel: 'New',
            className: 'bg-primary text-primary-foreground',
        },
    };

    const { icon: Icon, defaultLabel, className: variantClassName } = config[variant];

    return (
        <Badge
            variant="secondary"
            className={cn(
                'gap-1 text-xs font-medium',
                variantClassName,
                className
            )}
        >
            <Icon className="h-3 w-3" />
            {label || defaultLabel}
        </Badge>
    );
}
