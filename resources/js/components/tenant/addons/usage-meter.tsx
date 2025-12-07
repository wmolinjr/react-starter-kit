import { cn } from '@/lib/utils';

interface UsageMeterProps {
    label: string;
    used: number;
    limit: number;
    unit?: string;
    className?: string;
}

export function UsageMeter({ label, used, limit, unit = '', className }: UsageMeterProps) {
    const percentage = limit > 0 ? Math.min((used / limit) * 100, 100) : 0;
    const isOverLimit = used > limit;
    const isNearLimit = percentage >= 80;

    const formatValue = (value: number) => {
        if (value >= 1000000) {
            return `${(value / 1000000).toFixed(1)}M`;
        }
        if (value >= 1000) {
            return `${(value / 1000).toFixed(1)}K`;
        }
        return value.toLocaleString();
    };

    return (
        <div className={cn('space-y-2', className)}>
            <div className="flex items-center justify-between text-sm">
                <span className="font-medium">{label}</span>
                <span className={cn('text-muted-foreground', isOverLimit && 'text-destructive font-medium')}>
                    {formatValue(used)} / {formatValue(limit)} {unit}
                </span>
            </div>
            <div className="bg-secondary h-2 w-full overflow-hidden rounded-full">
                <div
                    className={cn(
                        'h-full transition-all',
                        isOverLimit ? 'bg-destructive' : isNearLimit ? 'bg-yellow-500' : 'bg-primary',
                    )}
                    style={{ width: `${Math.min(percentage, 100)}%` }}
                />
            </div>
            {isOverLimit && (
                <p className="text-destructive text-xs">
                    Over limit by {formatValue(used - limit)} {unit}
                </p>
            )}
        </div>
    );
}
