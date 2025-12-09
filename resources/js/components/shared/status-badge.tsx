/**
 * StatusBadge Component - Renders status badges using enum metadata
 *
 * Uses metadata generated from PHP enums as single source of truth.
 * Icons, colors, and badge variants come from the enum definitions.
 * Labels use i18n translations for proper internationalization.
 *
 * Usage:
 * <FederatedUserStatusBadge status="active" />
 * <FederatedUserLinkSyncStatusBadge status="synced" />
 * <FederationConflictStatusBadge status="pending" />
 */

import { Badge } from '@/components/ui/badge';
import {
    FEDERATED_USER_LINK_SYNC_STATUS,
    FEDERATED_USER_STATUS,
    FEDERATION_CONFLICT_STATUS,
} from '@/lib/enum-metadata';
import type {
    FederatedUserLinkSyncStatus,
    FederatedUserStatus,
    FederationConflictStatus,
} from '@/types/enums';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    AlertOctagon,
    AlertTriangle,
    CheckCircle,
    Clock,
    XCircle,
} from 'lucide-react';

/**
 * Icon map - maps icon names from enum to actual Lucide components.
 */
const IconMap = {
    CheckCircle,
    XCircle,
    AlertTriangle,
    Clock,
    AlertOctagon,
} as const;

type IconName = keyof typeof IconMap;

interface StatusBadgeProps {
    icon: IconName;
    label: string;
    variant: 'default' | 'destructive' | 'secondary' | 'outline';
    className?: string;
}

/**
 * Base StatusBadge component.
 */
function StatusBadge({ icon, label, variant, className }: StatusBadgeProps) {
    const Icon = IconMap[icon];

    return (
        <Badge variant={variant} className={className}>
            {Icon && <Icon className="mr-1 h-3 w-3" />}
            {label}
        </Badge>
    );
}

/**
 * FederatedUserStatusBadge - Renders badge for federated user status.
 */
export function FederatedUserStatusBadge({
    status,
    className,
}: {
    status: FederatedUserStatus;
    className?: string;
}) {
    const { t } = useLaravelReactI18n();
    const meta = FEDERATED_USER_STATUS[status];

    return (
        <StatusBadge
            icon={meta.icon as IconName}
            label={t(`admin.federation.user_status.${status}`)}
            variant={meta.badge_variant}
            className={className}
        />
    );
}

/**
 * FederatedUserLinkSyncStatusBadge - Renders badge for tenant link sync status.
 */
export function FederatedUserLinkSyncStatusBadge({
    status,
    className,
}: {
    status: FederatedUserLinkSyncStatus;
    className?: string;
}) {
    const { t } = useLaravelReactI18n();
    const meta = FEDERATED_USER_LINK_SYNC_STATUS[status];

    return (
        <StatusBadge
            icon={meta.icon as IconName}
            label={t(`admin.federation.link_status.${status}`)}
            variant={meta.badge_variant}
            className={className}
        />
    );
}

/**
 * FederationConflictStatusBadge - Renders badge for conflict status.
 */
export function FederationConflictStatusBadge({
    status,
    className,
}: {
    status: FederationConflictStatus;
    className?: string;
}) {
    const { t } = useLaravelReactI18n();
    const meta = FEDERATION_CONFLICT_STATUS[status];

    return (
        <StatusBadge
            icon={meta.icon as IconName}
            label={t(`admin.federation.conflict.${status}`)}
            variant={meta.badge_variant}
            className={className}
        />
    );
}
