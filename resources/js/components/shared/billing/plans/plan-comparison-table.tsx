import { Fragment } from 'react';
import { cn } from '@/lib/utils';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Check, X, Minus, Crown, ArrowUp } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { PriceDisplay } from '../primitives/price-display';
import type { PlanResource } from '@/types/resources';
import type { BillingPeriod } from '@/types/enums';
import type { PlanFeatures, PlanLimits } from '@/types/plan';

export interface FeatureCategory {
    /** Category name for display */
    name: string;
    /** Features in this category */
    features: FeatureRow[];
}

export interface FeatureRow {
    /** Unique key for the feature */
    key: string;
    /** Display label */
    label: string;
    /** Optional description/tooltip */
    description?: string;
    /** Values for each plan (planSlug -> value) */
    values: Record<string, boolean | string | number | null>;
}

export interface PlanComparisonTableProps {
    /** Plans to compare */
    plans: PlanResource[];
    /** Current user's plan slug */
    currentPlanSlug?: string;
    /** Callback when selecting a plan */
    onSelect?: (planSlug: string) => void;
    /** Current billing period */
    billingPeriod: BillingPeriod;
    /** Optional custom feature categories (overrides auto-generated) */
    featureCategories?: FeatureCategory[];
    /** Whether to show limits section */
    showLimits?: boolean;
    /** Whether to show features section */
    showFeatures?: boolean;
    /** Loading state */
    isLoading?: boolean;
    /** Additional className */
    className?: string;
}

/**
 * PlanComparisonTable - Side-by-side plan comparison
 *
 * Displays plans in a table format with:
 * - Price row
 * - Limits (users, projects, storage, etc.)
 * - Features (boolean capabilities)
 * - Action buttons
 *
 * @example
 * <PlanComparisonTable
 *     plans={plans}
 *     currentPlanSlug="starter"
 *     billingPeriod="monthly"
 *     onSelect={(slug) => handlePlanChange(slug)}
 * />
 */
export function PlanComparisonTable({
    plans,
    currentPlanSlug,
    onSelect,
    billingPeriod,
    featureCategories,
    showLimits = true,
    showFeatures = true,
    isLoading = false,
    className,
}: PlanComparisonTableProps) {
    const { t } = useLaravelReactI18n();

    // Sort plans by price (ascending)
    const sortedPlans = [...plans].sort((a, b) => a.price - b.price);

    // Translation helper with correct typing
    const translate = (key: string, options?: { default?: string }): string => {
        return t(key, options as Record<string, string | number>);
    };

    // Generate feature categories from plans if not provided
    const categories =
        featureCategories || generateCategories(sortedPlans, showLimits, showFeatures, translate);

    // Get badge for plan
    const getBadge = (plan: PlanResource): { text: string; variant: string } | null => {
        if (!plan.badge) return null;

        const badgeMap: Record<string, { text: string; variant: string }> = {
            most_popular: {
                text: t('enums.badge.preset.most_popular', { default: 'Most Popular' }),
                variant: 'popular',
            },
            best_value: {
                text: t('enums.badge.preset.best_value', { default: 'Best Value' }),
                variant: 'best-value',
            },
            recommended: {
                text: t('enums.badge.preset.recommended', { default: 'Recommended' }),
                variant: 'recommended',
            },
            new: { text: t('enums.badge.preset.new', { default: 'New' }), variant: 'new' },
        };

        return badgeMap[plan.badge] || { text: plan.badge, variant: 'default' };
    };

    // Render cell value
    const renderValue = (value: boolean | string | number | null | undefined) => {
        if (value === true) {
            return <Check className="text-primary h-5 w-5" />;
        }
        if (value === false) {
            return <X className="text-muted-foreground h-5 w-5" />;
        }
        if (value === null || value === undefined) {
            return <Minus className="text-muted-foreground h-5 w-5" />;
        }
        if (value === -1) {
            return (
                <span className="text-primary font-medium">
                    {t('billing.usage.unlimited', { default: 'Unlimited' })}
                </span>
            );
        }
        return <span className="font-medium">{value}</span>;
    };

    const badgeVariantClasses: Record<string, string> = {
        default: '',
        popular: 'bg-primary text-primary-foreground',
        new: 'bg-blue-500 text-white',
        recommended: 'bg-green-500 text-white',
        'best-value': 'bg-amber-500 text-white',
    };

    return (
        <TooltipProvider>
            <div className={cn('overflow-x-auto', className)}>
                <Table>
                    <TableHeader>
                        {/* Plan names row */}
                        <TableRow>
                            <TableHead className="w-[200px]" />
                            {sortedPlans.map((plan) => {
                                const badge = getBadge(plan);
                                const isCurrent = currentPlanSlug === plan.slug;

                                return (
                                    <TableHead
                                        key={plan.slug}
                                        className={cn(
                                            'text-center',
                                            plan.is_featured && 'bg-primary/5',
                                            isCurrent && 'bg-primary/10'
                                        )}
                                    >
                                        <div className="space-y-2 py-2">
                                            {badge && (
                                                <Badge
                                                    className={cn(
                                                        'text-xs',
                                                        badgeVariantClasses[badge.variant]
                                                    )}
                                                >
                                                    {badge.text}
                                                </Badge>
                                            )}
                                            <div className="text-lg font-semibold">{plan.name}</div>
                                            {plan.description && (
                                                <div className="text-muted-foreground text-xs font-normal">
                                                    {plan.description}
                                                </div>
                                            )}
                                        </div>
                                    </TableHead>
                                );
                            })}
                        </TableRow>

                        {/* Price row */}
                        <TableRow>
                            <TableHead className="font-medium">
                                {t('billing.price.price', { default: 'Price' })}
                            </TableHead>
                            {sortedPlans.map((plan) => {
                                const isCurrent = currentPlanSlug === plan.slug;

                                return (
                                    <TableHead
                                        key={plan.slug}
                                        className={cn(
                                            'text-center',
                                            plan.is_featured && 'bg-primary/5',
                                            isCurrent && 'bg-primary/10'
                                        )}
                                    >
                                        <PriceDisplay
                                            amount={plan.price}
                                            currency={plan.currency}
                                            period={
                                                billingPeriod === 'yearly' ? 'yearly' : 'monthly'
                                            }
                                            size="md"
                                            className="justify-center"
                                        />
                                    </TableHead>
                                );
                            })}
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        {/* Feature categories */}
                        {categories.map((category) => (
                            <Fragment key={category.name}>
                                {/* Category header */}
                                <TableRow className="bg-muted/50">
                                    <TableCell
                                        colSpan={sortedPlans.length + 1}
                                        className="font-semibold"
                                    >
                                        {category.name}
                                    </TableCell>
                                </TableRow>

                                {/* Feature rows */}
                                {category.features.map((feature) => (
                                    <TableRow key={feature.key}>
                                        <TableCell>
                                            {feature.description ? (
                                                <Tooltip>
                                                    <TooltipTrigger className="cursor-help underline decoration-dotted">
                                                        {feature.label}
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        <p className="max-w-xs">
                                                            {feature.description}
                                                        </p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            ) : (
                                                feature.label
                                            )}
                                        </TableCell>
                                        {sortedPlans.map((plan) => {
                                            const isCurrent = currentPlanSlug === plan.slug;

                                            return (
                                                <TableCell
                                                    key={plan.slug}
                                                    className={cn(
                                                        'text-center',
                                                        plan.is_featured && 'bg-primary/5',
                                                        isCurrent && 'bg-primary/10'
                                                    )}
                                                >
                                                    <div className="flex justify-center">
                                                        {renderValue(feature.values[plan.slug])}
                                                    </div>
                                                </TableCell>
                                            );
                                        })}
                                    </TableRow>
                                ))}
                            </Fragment>
                        ))}

                        {/* Action row */}
                        <TableRow>
                            <TableCell />
                            {sortedPlans.map((plan) => {
                                const isCurrent = currentPlanSlug === plan.slug;

                                return (
                                    <TableCell
                                        key={plan.slug}
                                        className={cn(
                                            'text-center',
                                            plan.is_featured && 'bg-primary/5',
                                            isCurrent && 'bg-primary/10'
                                        )}
                                    >
                                        <div className="py-2">
                                            {isCurrent ? (
                                                <Button variant="outline" disabled className="w-full">
                                                    <Crown className="mr-2 h-4 w-4" />
                                                    {t('billing.plan.current', {
                                                        default: 'Current Plan',
                                                    })}
                                                </Button>
                                            ) : (
                                                <Button
                                                    variant={
                                                        plan.is_featured ? 'default' : 'outline'
                                                    }
                                                    onClick={() => onSelect?.(plan.slug)}
                                                    disabled={isLoading}
                                                    className="w-full"
                                                >
                                                    <ArrowUp className="mr-2 h-4 w-4" />
                                                    {t('common.select', { default: 'Select' })}
                                                </Button>
                                            )}
                                        </div>
                                    </TableCell>
                                );
                            })}
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </TooltipProvider>
    );
}

/**
 * Generate feature categories from plan data
 */
function generateCategories(
    plans: PlanResource[],
    showLimits: boolean,
    showFeatures: boolean,
    t: (key: string, options?: { default?: string }) => string
): FeatureCategory[] {
    const categories: FeatureCategory[] = [];

    // Limits category
    if (showLimits && plans.length > 0 && plans[0].limits) {
        const limitLabels: Record<
            string,
            { label: string; description?: string; suffix?: string }
        > = {
            users: {
                label: t('enums.plan.limit.users', { default: 'User Seats' }),
                description: t('enums.plan.limit.users.desc', {
                    default: 'Maximum number of team members',
                }),
            },
            projects: {
                label: t('enums.plan.limit.projects', { default: 'Projects' }),
                description: t('enums.plan.limit.projects.desc', {
                    default: 'Maximum number of active projects',
                }),
            },
            storage: {
                label: t('enums.plan.limit.storage', { default: 'Storage' }),
                suffix: 'GB',
                description: t('enums.plan.limit.storage.desc', {
                    default: 'Total storage space available',
                }),
            },
            apiCalls: {
                label: t('enums.plan.limit.apiCalls', { default: 'API Calls' }),
                description: t('enums.plan.limit.apiCalls.desc', {
                    default: 'Monthly API request limit',
                }),
            },
            customRoles: {
                label: t('enums.plan.limit.customRoles', { default: 'Custom Roles' }),
                description: t('enums.plan.limit.customRoles.desc', {
                    default: 'Maximum number of custom roles that can be created',
                }),
            },
            locales: {
                label: t('enums.plan.limit.locales', { default: 'Languages' }),
                description: t('enums.plan.limit.locales.desc', {
                    default: 'Maximum number of languages that can be enabled',
                }),
            },
        };

        const displayOrder = [
            'users',
            'projects',
            'storage',
            'apiCalls',
            'customRoles',
            'locales',
        ];

        const limitFeatures: FeatureRow[] = [];

        for (const key of displayOrder) {
            const config = limitLabels[key];
            if (!config) continue;

            const values: Record<string, number | null> = {};
            let hasValue = false;

            for (const plan of plans) {
                const value = plan.limits[key as keyof PlanLimits];
                if (value !== undefined && value !== null) {
                    hasValue = true;
                    // Add suffix for storage
                    if (config.suffix && value !== -1) {
                        values[plan.slug] = value;
                    } else {
                        values[plan.slug] = value;
                    }
                }
            }

            if (hasValue) {
                limitFeatures.push({
                    key,
                    label: config.label,
                    description: config.description,
                    values,
                });
            }
        }

        if (limitFeatures.length > 0) {
            categories.push({
                name: t('billing.category.limits', { default: 'Limits' }),
                features: limitFeatures,
            });
        }
    }

    // Features category
    if (showFeatures && plans.length > 0 && plans[0].features) {
        const featureLabels: Record<string, { label: string; description?: string }> = {
            customRoles: {
                label: t('enums.plan.feature.customRoles', { default: 'Custom Roles' }),
                description: t('enums.plan.feature.customRoles.desc', {
                    default: 'Create and manage custom roles with granular permissions',
                }),
            },
            apiAccess: {
                label: t('enums.plan.feature.apiAccess', { default: 'API Access' }),
                description: t('enums.plan.feature.apiAccess.desc', {
                    default: 'Generate API tokens for external integrations',
                }),
            },
            advancedReports: {
                label: t('enums.plan.feature.advancedReports', { default: 'Advanced Reports' }),
                description: t('enums.plan.feature.advancedReports.desc', {
                    default: 'Access advanced analytics and custom report builder',
                }),
            },
            sso: {
                label: t('enums.plan.feature.sso', { default: 'Single Sign-On (SSO)' }),
                description: t('enums.plan.feature.sso.desc', {
                    default: 'Enable SAML/OIDC authentication for enterprise security',
                }),
            },
            whiteLabel: {
                label: t('enums.plan.feature.whiteLabel', { default: 'White Label' }),
                description: t('enums.plan.feature.whiteLabel.desc', {
                    default: 'Customize branding, colors, and remove platform branding',
                }),
            },
            auditLog: {
                label: t('enums.plan.feature.auditLog', { default: 'Audit Log' }),
                description: t('enums.plan.feature.auditLog.desc', {
                    default: 'Track all user actions and system events',
                }),
            },
            prioritySupport: {
                label: t('enums.plan.feature.prioritySupport', { default: 'Priority Support' }),
                description: t('enums.plan.feature.prioritySupport.desc', {
                    default: '24/7 priority support with dedicated account manager',
                }),
            },
            multiLanguage: {
                label: t('enums.plan.feature.multiLanguage', { default: 'Multi-Language' }),
                description: t('enums.plan.feature.multiLanguage.desc', {
                    default: 'Enable multiple language support for your users',
                }),
            },
            federation: {
                label: t('enums.plan.feature.federation', { default: 'User Federation' }),
                description: t('enums.plan.feature.federation.desc', {
                    default: 'Sync users across multiple tenants in a federation group',
                }),
            },
        };

        const featureOrder = [
            'customRoles',
            'apiAccess',
            'advancedReports',
            'auditLog',
            'multiLanguage',
            'prioritySupport',
            'sso',
            'whiteLabel',
            'federation',
        ];

        const booleanFeatures: FeatureRow[] = [];

        for (const key of featureOrder) {
            const config = featureLabels[key];
            if (!config) continue;

            const values: Record<string, boolean> = {};
            let hasValue = false;

            for (const plan of plans) {
                const value = plan.features[key as keyof PlanFeatures];
                if (value !== undefined) {
                    hasValue = true;
                    values[plan.slug] = value;
                }
            }

            if (hasValue) {
                booleanFeatures.push({
                    key,
                    label: config.label,
                    description: config.description,
                    values,
                });
            }
        }

        if (booleanFeatures.length > 0) {
            categories.push({
                name: t('billing.category.features', { default: 'Features' }),
                features: booleanFeatures,
            });
        }
    }

    return categories;
}

/**
 * PlanComparisonTableSkeleton - Loading skeleton
 */
export function PlanComparisonTableSkeleton({
    columns = 3,
    className,
}: {
    columns?: number;
    className?: string;
}) {
    return (
        <div className={cn('overflow-x-auto', className)}>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead className="w-[200px]" />
                        {Array.from({ length: columns }).map((_, i) => (
                            <TableHead key={i} className="text-center">
                                <div className="space-y-2 py-2">
                                    <div className="bg-muted mx-auto h-5 w-20 animate-pulse rounded" />
                                    <div className="bg-muted mx-auto h-4 w-24 animate-pulse rounded" />
                                </div>
                            </TableHead>
                        ))}
                    </TableRow>
                    <TableRow>
                        <TableHead>
                            <div className="bg-muted h-4 w-16 animate-pulse rounded" />
                        </TableHead>
                        {Array.from({ length: columns }).map((_, i) => (
                            <TableHead key={i} className="text-center">
                                <div className="bg-muted mx-auto h-8 w-24 animate-pulse rounded" />
                            </TableHead>
                        ))}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {Array.from({ length: 6 }).map((_, row) => (
                        <TableRow key={row}>
                            <TableCell>
                                <div className="bg-muted h-4 w-28 animate-pulse rounded" />
                            </TableCell>
                            {Array.from({ length: columns }).map((_, col) => (
                                <TableCell key={col} className="text-center">
                                    <div className="bg-muted mx-auto h-5 w-5 animate-pulse rounded" />
                                </TableCell>
                            ))}
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}
