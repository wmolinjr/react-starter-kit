import { useState } from 'react';
import { cn } from '@/lib/utils';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Check, X, HelpCircle, ChevronDown, ChevronUp } from 'lucide-react';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Button } from '@/components/ui/button';
import type { FeatureListProps, FeatureItem } from '@/types/billing';

/**
 * FeatureList - Displays a list of features with inclusion status
 *
 * @example
 * // Basic usage
 * <FeatureList features={[
 *     { text: '10 users', included: true },
 *     { text: 'API Access', included: false },
 *     { text: 'Priority Support', included: true, tooltip: '24/7 support' },
 * ]} />
 *
 * @example
 * // Two columns with limited items
 * <FeatureList
 *     features={features}
 *     columns={2}
 *     maxVisible={6}
 *     showMore
 * />
 */
export function FeatureList({
    features,
    columns = 1,
    maxVisible,
    variant: _variant = 'compact',
    showMore = false,
    className,
}: FeatureListProps) {
    // TODO: Implement variant styling
    void _variant;
    const { t } = useLaravelReactI18n();
    const [expanded, setExpanded] = useState(false);

    const visibleFeatures = maxVisible && !expanded
        ? features.slice(0, maxVisible)
        : features;

    const hasMoreFeatures = maxVisible && features.length > maxVisible;

    return (
        <div className={className}>
            <ul
                className={cn(
                    'space-y-2',
                    columns === 2 && 'grid grid-cols-2 gap-x-4 gap-y-2 space-y-0'
                )}
            >
                {visibleFeatures.map((feature, index) => (
                    <FeatureListItem
                        key={index}
                        feature={feature}
                    />
                ))}
            </ul>

            {showMore && hasMoreFeatures && (
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setExpanded(!expanded)}
                    className="mt-2 w-full"
                >
                    {expanded ? (
                        <>
                            <ChevronUp className="mr-1 h-4 w-4" />
                            {t('common.show_less', { default: 'Show less' })}
                        </>
                    ) : (
                        <>
                            <ChevronDown className="mr-1 h-4 w-4" />
                            {t('common.show_more_count', {
                                default: 'Show :count more',
                                count: features.length - maxVisible,
                            })}
                        </>
                    )}
                </Button>
            )}
        </div>
    );
}

interface FeatureListItemProps {
    feature: FeatureItem;
}

function FeatureListItem({ feature }: FeatureListItemProps) {
    const content = (
        <li className="flex items-start gap-2">
            {feature.included ? (
                <Check className="mt-0.5 h-4 w-4 shrink-0 text-green-500" />
            ) : (
                <X className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
            )}

            <span
                className={cn(
                    'text-sm',
                    !feature.included && 'text-muted-foreground'
                )}
            >
                {feature.text}
                {feature.limit && (
                    <span className="text-muted-foreground ml-1">
                        ({typeof feature.limit === 'number'
                            ? feature.limit.toLocaleString()
                            : feature.limit})
                    </span>
                )}
            </span>

            {feature.tooltip && (
                <Tooltip>
                    <TooltipTrigger asChild>
                        <HelpCircle className="h-3.5 w-3.5 shrink-0 text-muted-foreground cursor-help" />
                    </TooltipTrigger>
                    <TooltipContent>
                        <p className="max-w-xs">{feature.tooltip}</p>
                    </TooltipContent>
                </Tooltip>
            )}
        </li>
    );

    return content;
}

/**
 * FeatureComparison - Compare a feature across multiple plans
 */
export interface FeatureComparisonProps {
    label: string;
    description?: string;
    values: {
        planSlug: string;
        value: boolean | string | number | null;
    }[];
}

export function FeatureComparison({ label, description, values }: FeatureComparisonProps) {
    return (
        <div className="grid grid-cols-[1fr_repeat(var(--plan-count),1fr)] items-center gap-4 py-3 border-b last:border-b-0">
            <div>
                <span className="text-sm font-medium">{label}</span>
                {description && (
                    <p className="text-xs text-muted-foreground">{description}</p>
                )}
            </div>

            {values.map(({ planSlug, value }) => (
                <div key={planSlug} className="text-center">
                    {typeof value === 'boolean' ? (
                        value ? (
                            <Check className="mx-auto h-5 w-5 text-green-500" />
                        ) : (
                            <X className="mx-auto h-5 w-5 text-muted-foreground" />
                        )
                    ) : value === null ? (
                        <span className="text-muted-foreground">-</span>
                    ) : (
                        <span className="text-sm font-medium">
                            {typeof value === 'number' ? value.toLocaleString() : value}
                        </span>
                    )}
                </div>
            ))}
        </div>
    );
}
