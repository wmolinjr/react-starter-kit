import { cn } from '@/lib/utils';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Package, Users, HardDrive, Zap, Globe, Shield, Check } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import type { BundleAddonItem } from '@/types/billing';
import type { BundleAddonResource } from '@/types/resources';
import type { AddonType } from '@/types/enums';

export interface BundleContentsProps {
    /** Addons included in the bundle */
    addons: BundleAddonItem[] | BundleAddonResource[];
    /** Whether to show quantities */
    showQuantities?: boolean;
    /** Whether to show addon values (e.g., "5 users") */
    showValues?: boolean;
    /** Layout variant */
    variant?: 'list' | 'grid' | 'compact';
    /** Maximum items to show before "and X more" */
    maxItems?: number;
    /** Additional className */
    className?: string;
}

/**
 * BundleContents - Displays the addons included in a bundle
 *
 * @example
 * <BundleContents
 *     addons={bundle.addons}
 *     showQuantities
 *     showValues
 * />
 *
 * @example
 * // Compact variant for cards
 * <BundleContents
 *     addons={bundle.addons}
 *     variant="compact"
 *     maxItems={3}
 * />
 */
export function BundleContents({
    addons,
    showQuantities = false,
    showValues = false,
    variant = 'list',
    maxItems,
    className,
}: BundleContentsProps) {
    const { t } = useLaravelReactI18n();

    // Get icon for addon type
    const getAddonIcon = (type: AddonType | string) => {
        const iconMap: Record<string, React.ReactNode> = {
            users: <Users className="h-4 w-4" />,
            storage: <HardDrive className="h-4 w-4" />,
            api_calls: <Zap className="h-4 w-4" />,
            feature: <Shield className="h-4 w-4" />,
            locale: <Globe className="h-4 w-4" />,
        };

        return iconMap[type] || <Package className="h-4 w-4" />;
    };

    // Get addon type from item (handle both types)
    const getAddonType = (addon: BundleAddonItem | BundleAddonResource): AddonType | string => {
        return addon.type;
    };

    // Get addon name
    const getAddonName = (addon: BundleAddonItem | BundleAddonResource): string => {
        return addon.name;
    };

    // Get addon quantity
    const getQuantity = (addon: BundleAddonItem | BundleAddonResource): number => {
        return addon.quantity;
    };

    // Get unit value for display
    const getUnitValue = (addon: BundleAddonItem | BundleAddonResource): string | null => {
        if ('unitValue' in addon && addon.unitValue) {
            const type = getAddonType(addon);
            if (type === 'users') return `+${addon.unitValue} ${t('billing.users', { default: 'users' })}`;
            if (type === 'storage') return `+${addon.unitValue} GB`;
            if (type === 'api_calls') return `+${addon.unitValue.toLocaleString()} ${t('billing.calls', { default: 'calls' })}`;
            return `+${addon.unitValue}`;
        }
        return null;
    };

    const visibleAddons = maxItems ? addons.slice(0, maxItems) : addons;
    const remainingCount = maxItems ? Math.max(0, addons.length - maxItems) : 0;

    if (variant === 'compact') {
        return (
            <div className={cn('flex flex-wrap gap-1.5', className)}>
                {visibleAddons.map((addon, index) => (
                    <Badge
                        key={index}
                        variant="secondary"
                        className="gap-1 text-xs font-normal"
                    >
                        {getAddonIcon(getAddonType(addon))}
                        {getAddonName(addon)}
                        {showQuantities && getQuantity(addon) > 1 && (
                            <span className="text-muted-foreground">x{getQuantity(addon)}</span>
                        )}
                    </Badge>
                ))}
                {remainingCount > 0 && (
                    <Badge variant="outline" className="text-xs font-normal">
                        +{remainingCount} {t('billing.more', { default: 'more' })}
                    </Badge>
                )}
            </div>
        );
    }

    if (variant === 'grid') {
        return (
            <div className={cn('grid grid-cols-2 gap-2', className)}>
                {visibleAddons.map((addon, index) => (
                    <div
                        key={index}
                        className="bg-muted/50 flex items-center gap-2 rounded-md p-2"
                    >
                        <div className="text-muted-foreground">
                            {getAddonIcon(getAddonType(addon))}
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-medium">
                                {getAddonName(addon)}
                            </p>
                            {showValues && getUnitValue(addon) && (
                                <p className="text-muted-foreground text-xs">
                                    {getUnitValue(addon)}
                                </p>
                            )}
                        </div>
                        {showQuantities && getQuantity(addon) > 1 && (
                            <Badge variant="secondary" className="text-xs">
                                x{getQuantity(addon)}
                            </Badge>
                        )}
                    </div>
                ))}
                {remainingCount > 0 && (
                    <div className="text-muted-foreground flex items-center justify-center rounded-md border border-dashed p-2 text-sm">
                        +{remainingCount} {t('billing.more', { default: 'more' })}
                    </div>
                )}
            </div>
        );
    }

    // Default list variant
    return (
        <ul className={cn('space-y-2', className)}>
            {visibleAddons.map((addon, index) => (
                <li key={index} className="flex items-center gap-2">
                    <Check className="text-primary h-4 w-4 shrink-0" />
                    <span className="flex-1 text-sm">
                        {getAddonName(addon)}
                        {showValues && getUnitValue(addon) && (
                            <span className="text-muted-foreground ml-1">
                                ({getUnitValue(addon)})
                            </span>
                        )}
                    </span>
                    {showQuantities && getQuantity(addon) > 1 && (
                        <Badge variant="secondary" className="text-xs">
                            x{getQuantity(addon)}
                        </Badge>
                    )}
                </li>
            ))}
            {remainingCount > 0 && (
                <li className="text-muted-foreground flex items-center gap-2 text-sm">
                    <span className="h-4 w-4" />
                    +{remainingCount} {t('billing.more_addons', { default: 'more add-ons' })}
                </li>
            )}
        </ul>
    );
}

/**
 * BundleContentsSkeleton - Loading skeleton
 */
export function BundleContentsSkeleton({
    items = 3,
    variant = 'list',
    className,
}: {
    items?: number;
    variant?: 'list' | 'grid' | 'compact';
    className?: string;
}) {
    if (variant === 'compact') {
        return (
            <div className={cn('flex flex-wrap gap-1.5', className)}>
                {Array.from({ length: items }).map((_, i) => (
                    <div
                        key={i}
                        className="bg-muted h-6 w-20 animate-pulse rounded-full"
                    />
                ))}
            </div>
        );
    }

    if (variant === 'grid') {
        return (
            <div className={cn('grid grid-cols-2 gap-2', className)}>
                {Array.from({ length: items }).map((_, i) => (
                    <div
                        key={i}
                        className="bg-muted h-14 animate-pulse rounded-md"
                    />
                ))}
            </div>
        );
    }

    return (
        <div className={cn('space-y-2', className)}>
            {Array.from({ length: items }).map((_, i) => (
                <div key={i} className="flex items-center gap-2">
                    <div className="bg-muted h-4 w-4 animate-pulse rounded" />
                    <div className="bg-muted h-4 flex-1 animate-pulse rounded" />
                </div>
            ))}
        </div>
    );
}
