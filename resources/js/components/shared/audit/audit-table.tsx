import { format } from 'date-fns';
import { ptBR } from 'date-fns/locale';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Eye, User } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Pagination,
    PaginationContent,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

import type { AuditTableProps } from './types';

export function AuditTable({ activities, config, onViewDetails }: AuditTableProps) {
    const { t, currentLocale } = useLaravelReactI18n();

    const formatDate = (dateString: string) => {
        const date = new Date(dateString);
        const locale = currentLocale() === 'pt_BR' ? ptBR : undefined;
        return format(date, 'dd/MM/yyyy HH:mm:ss', { locale });
    };

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

    return (
        <>
            {/* Activity Table */}
            <div className="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="w-[180px]">{t(`${config.translationPrefix}.column_date`)}</TableHead>
                            <TableHead>{t(`${config.translationPrefix}.column_user`)}</TableHead>
                            <TableHead>{t(`${config.translationPrefix}.column_action`)}</TableHead>
                            <TableHead>{t(`${config.translationPrefix}.column_subject`)}</TableHead>
                            <TableHead>{t(`${config.translationPrefix}.column_description`)}</TableHead>
                            <TableHead className="w-[100px]">{t('common.actions')}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {activities.data.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={6} className="py-8 text-center text-muted-foreground">
                                    {t(`${config.translationPrefix}.no_activities`)}
                                </TableCell>
                            </TableRow>
                        ) : (
                            activities.data.map((activity) => (
                                <TableRow key={activity.id}>
                                    <TableCell className="text-muted-foreground">
                                        <div className="flex flex-col">
                                            <span className="text-xs">{formatDate(activity.created_at)}</span>
                                            <span className="text-xs text-muted-foreground/70">
                                                {activity.created_at_human}
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {activity.causer ? (
                                            <div className="flex items-center gap-2">
                                                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-muted">
                                                    <User className="h-4 w-4" />
                                                </div>
                                                <div className="flex flex-col">
                                                    <span className="text-sm font-medium">
                                                        {activity.causer.name}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {activity.causer.email}
                                                    </span>
                                                </div>
                                            </div>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                {t(`${config.translationPrefix}.system`)}
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell>{getEventBadge(activity.event)}</TableCell>
                                    <TableCell>
                                        {activity.subject_type && (
                                            <div className="flex flex-col">
                                                <Badge variant="outline" className="w-fit">
                                                    {activity.subject_type}
                                                </Badge>
                                                {activity.subject_name && (
                                                    <span className="mt-1 text-xs text-muted-foreground">
                                                        {activity.subject_name}
                                                    </span>
                                                )}
                                            </div>
                                        )}
                                    </TableCell>
                                    <TableCell className="max-w-[300px] truncate">
                                        {activity.description}
                                    </TableCell>
                                    <TableCell>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => onViewDetails(activity)}
                                        >
                                            <Eye className="h-4 w-4" />
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            </div>

            {/* Pagination */}
            {activities.last_page > 1 && (
                <div className="flex items-center justify-between">
                    <p className="text-sm text-muted-foreground">
                        {t('common.showing_of', {
                            from: (activities.current_page - 1) * activities.per_page + 1,
                            to: Math.min(activities.current_page * activities.per_page, activities.total),
                            total: activities.total,
                        })}
                    </p>
                    <Pagination>
                        <PaginationContent>
                            {activities.current_page > 1 && (
                                <PaginationItem>
                                    <PaginationPrevious
                                        href={`${config.baseUrl}?page=${activities.current_page - 1}`}
                                    />
                                </PaginationItem>
                            )}
                            {activities.links
                                .filter((link) => !link.label.includes('Previous') && !link.label.includes('Next'))
                                .map((link, index) => (
                                    <PaginationItem key={index}>
                                        <PaginationLink
                                            href={link.url || '#'}
                                            isActive={link.active}
                                        >
                                            {link.label}
                                        </PaginationLink>
                                    </PaginationItem>
                                ))}
                            {activities.current_page < activities.last_page && (
                                <PaginationItem>
                                    <PaginationNext
                                        href={`${config.baseUrl}?page=${activities.current_page + 1}`}
                                    />
                                </PaginationItem>
                            )}
                        </PaginationContent>
                    </Pagination>
                </div>
            )}
        </>
    );
}
