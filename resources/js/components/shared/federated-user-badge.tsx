import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Link as LinkIcon, Network } from 'lucide-react';

interface FederatedUserBadgeProps {
    /**
     * Whether the user is federated (has a federation_id)
     */
    isFederated: boolean;
    /**
     * The name of the federation group (optional, shown in tooltip)
     */
    groupName?: string;
    /**
     * Whether this user is the master in the federation
     */
    isMaster?: boolean;
    /**
     * Size variant
     */
    size?: 'sm' | 'default';
    /**
     * Show label text or icon only
     */
    iconOnly?: boolean;
}

/**
 * FederatedUserBadge
 *
 * A visual indicator showing whether a user is federated (synced across tenants).
 * Use this badge in user listings, team member tables, and user profiles to
 * indicate federation status.
 *
 * @example
 * // Basic usage - shows badge only if federated
 * <FederatedUserBadge isFederated={user.is_federated} />
 *
 * @example
 * // With group name in tooltip
 * <FederatedUserBadge
 *   isFederated={user.is_federated}
 *   groupName="ACME Corporation"
 * />
 *
 * @example
 * // Icon only (compact)
 * <FederatedUserBadge isFederated={true} iconOnly />
 */
export function FederatedUserBadge({
    isFederated,
    groupName,
    isMaster = false,
    size = 'default',
    iconOnly = false,
}: FederatedUserBadgeProps) {
    const { t } = useLaravelReactI18n();

    if (!isFederated) {
        return null;
    }

    const iconSize = size === 'sm' ? 'h-3 w-3' : 'h-4 w-4';
    const badgeSize = size === 'sm' ? 'text-xs px-1.5 py-0' : '';

    const tooltipContent = groupName
        ? t('components.federated_badge.tooltip_with_group', { group: groupName })
        : t('components.federated_badge.tooltip');

    const badge = (
        <Badge
            variant={isMaster ? 'default' : 'secondary'}
            className={`gap-1 ${badgeSize}`}
        >
            {isMaster ? (
                <Network className={iconSize} />
            ) : (
                <LinkIcon className={iconSize} />
            )}
            {!iconOnly && (
                <span>
                    {isMaster
                        ? t('components.federated_badge.master')
                        : t('components.federated_badge.federated')}
                </span>
            )}
        </Badge>
    );

    if (iconOnly || groupName) {
        return (
            <TooltipProvider>
                <Tooltip>
                    <TooltipTrigger asChild>{badge}</TooltipTrigger>
                    <TooltipContent>
                        <p>{tooltipContent}</p>
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>
        );
    }

    return badge;
}

/**
 * Hook to determine if federation badge should be shown
 * based on user data from API resources
 */
export function useFederatedStatus(user: {
    is_federated?: boolean;
    federation_id?: string | null;
    is_master_user?: boolean;
}) {
    return {
        isFederated: user.is_federated ?? !!user.federation_id,
        isMaster: user.is_master_user ?? false,
    };
}
