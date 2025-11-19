import { Badge } from '@/components/ui/badge';
import type { DomainVerificationStatus } from '@/types';
import { CheckCircle2, Clock, XCircle } from 'lucide-react';

interface DomainStatusBadgeProps {
    status: DomainVerificationStatus;
    className?: string;
}

export function DomainStatusBadge({ status, className }: DomainStatusBadgeProps) {
    const config = {
        pending: {
            label: 'Pending',
            icon: Clock,
            variant: 'secondary' as const,
            className: 'bg-amber-500/10 text-amber-700 hover:bg-amber-500/20 dark:text-amber-400',
        },
        verified: {
            label: 'Verified',
            icon: CheckCircle2,
            variant: 'secondary' as const,
            className: 'bg-green-500/10 text-green-700 hover:bg-green-500/20 dark:text-green-400',
        },
        failed: {
            label: 'Failed',
            icon: XCircle,
            variant: 'secondary' as const,
            className: 'bg-red-500/10 text-red-700 hover:bg-red-500/20 dark:text-red-400',
        },
    };

    const { label, icon: Icon, variant, className: statusClassName } = config[status];

    return (
        <Badge variant={variant} className={`${statusClassName} ${className || ''}`}>
            <Icon className="mr-1 h-3 w-3" />
            {label}
        </Badge>
    );
}
