import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ChevronDown } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { ActivityProperties } from '@/types';

import type { AuditDetailDialogProps } from './types';

export function AuditDetailDialog({
    activity,
    open,
    onOpenChange,
    config,
}: AuditDetailDialogProps) {
    const { t } = useLaravelReactI18n();

    const getEventBadge = (event: string) => {
        const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
            created: 'default',
            updated: 'secondary',
            deleted: 'destructive',
            login: 'outline',
            logout: 'outline',
        };

        const translationKey = `${config.translationPrefix}.event_${event}`;
        const translatedEvent = t(translationKey);

        return (
            <Badge variant={variants[event] || 'outline'}>
                {translatedEvent !== translationKey ? translatedEvent : event}
            </Badge>
        );
    };

    const renderChanges = (properties: ActivityProperties) => {
        const { old: oldValues, new: newValues } = properties;

        if (!oldValues && !newValues) {
            return <span className="text-muted-foreground">{t(`${config.translationPrefix}.no_changes`)}</span>;
        }

        const allKeys = new Set([
            ...Object.keys(oldValues || {}),
            ...Object.keys(newValues || {}),
        ]);

        return (
            <div className="space-y-2">
                {Array.from(allKeys).map((key) => {
                    const oldVal = oldValues?.[key];
                    const newVal = newValues?.[key];
                    const hasChanged = JSON.stringify(oldVal) !== JSON.stringify(newVal);

                    if (!hasChanged && oldValues && newValues) return null;

                    return (
                        <div key={key} className="text-sm">
                            <span className="font-medium">{key}:</span>
                            <div className="ml-4 grid grid-cols-2 gap-4">
                                {oldValues && (
                                    <div>
                                        <span className="text-xs text-muted-foreground">{t(`${config.translationPrefix}.old_value`)}</span>
                                        <pre className="mt-1 rounded bg-red-50 p-2 text-xs text-red-700 dark:bg-red-950 dark:text-red-300">
                                            {JSON.stringify(oldVal, null, 2) || '-'}
                                        </pre>
                                    </div>
                                )}
                                {newValues && (
                                    <div>
                                        <span className="text-xs text-muted-foreground">{t(`${config.translationPrefix}.new_value`)}</span>
                                        <pre className="mt-1 rounded bg-green-50 p-2 text-xs text-green-700 dark:bg-green-950 dark:text-green-300">
                                            {JSON.stringify(newVal, null, 2) || '-'}
                                        </pre>
                                    </div>
                                )}
                            </div>
                        </div>
                    );
                })}
            </div>
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[80vh] max-w-2xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{t(`${config.translationPrefix}.activity_details`)}</DialogTitle>
                    <DialogDescription>
                        {activity?.created_at_formatted}
                    </DialogDescription>
                </DialogHeader>
                {activity && (
                    <div className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <p className="text-sm text-muted-foreground">{t(`${config.translationPrefix}.column_user`)}</p>
                                <p className="font-medium">
                                    {activity.causer?.name || t(`${config.translationPrefix}.system`)}
                                </p>
                                {activity.causer?.email && (
                                    <p className="text-sm text-muted-foreground">{activity.causer.email}</p>
                                )}
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">{t(`${config.translationPrefix}.column_action`)}</p>
                                <div className="mt-1">{getEventBadge(activity.event)}</div>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <p className="text-sm text-muted-foreground">{t(`${config.translationPrefix}.column_subject`)}</p>
                                <p className="font-medium">
                                    {activity.subject_type || '-'}
                                </p>
                                {activity.subject_name && (
                                    <p className="text-sm text-muted-foreground">{activity.subject_name}</p>
                                )}
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">{t(`${config.translationPrefix}.filter_log_name`)}</p>
                                <p className="font-medium">{activity.log_name || 'default'}</p>
                            </div>
                        </div>

                        <div>
                            <p className="text-sm text-muted-foreground">{t(`${config.translationPrefix}.column_description`)}</p>
                            <p className="font-medium">{activity.description}</p>
                        </div>

                        {/* Changes */}
                        <Collapsible defaultOpen>
                            <CollapsibleTrigger asChild>
                                <Button variant="ghost" className="w-full justify-between">
                                    <span>{t(`${config.translationPrefix}.changes`)}</span>
                                    <ChevronDown className="h-4 w-4" />
                                </Button>
                            </CollapsibleTrigger>
                            <CollapsibleContent className="mt-2 rounded-md border p-4">
                                {renderChanges(activity.properties)}
                            </CollapsibleContent>
                        </Collapsible>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
