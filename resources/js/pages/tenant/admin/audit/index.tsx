import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { format } from 'date-fns';
import { ptBR } from 'date-fns/locale';
import {
    ClipboardList,
    User,
    Filter,
    RefreshCw,
    Download,
    Search,
    Eye,
    ChevronDown,
} from 'lucide-react';

import AdminLayout from '@/layouts/tenant/admin-layout';
import admin from '@/routes/tenant/admin';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    type BreadcrumbItem,
    type ActivityResource,
    type UserSummaryResource,
    type TenantSummaryResource,
    type ActivityProperties,
} from '@/types';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Page,
    PageHeader,
    PageHeaderContent,
    PageHeaderActions,
    PageTitle,
    PageDescription,
    PageContent,
} from '@/components/shared/layout/page';
import {
    Pagination,
    PaginationContent,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';

import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';

interface SubjectType {
    value: string;
    label: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedActivities {
    data: ActivityResource[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: PaginationLink[];
}

interface AuditFilters {
    user_id: string | null;
    event: string | null;
    subject_type: string | null;
    log_name: string | null;
    date_from: string | null;
    date_to: string | null;
    search: string | null;
}

interface Props {
    activities: PaginatedActivities;
    teamMembers: UserSummaryResource[];
    eventTypes: string[];
    subjectTypes: SubjectType[];
    logNames: string[];
    filters: AuditFilters;
    tenant: TenantSummaryResource;
}

function AuditLogIndex({
    activities,
    teamMembers,
    eventTypes,
    subjectTypes,
    logNames,
    filters,
    tenant: tenantData,
}: Props) {
    const { t, currentLocale } = useLaravelReactI18n();
    const [localFilters, setLocalFilters] = useState<AuditFilters>(filters);
    const [selectedActivity, setSelectedActivity] = useState<ActivityResource | null>(null);
    const [isExporting, setIsExporting] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('tenant.audit.title'), href: admin.audit.index().url },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const getEventBadge = (event: string) => {
        const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
            created: 'default',
            updated: 'secondary',
            deleted: 'destructive',
            login: 'outline',
            logout: 'outline',
        };

        const translationKey = `tenant.audit.event_${event}`;
        const translatedEvent = t(translationKey);

        return (
            <Badge variant={variants[event] || 'outline'}>
                {translatedEvent !== translationKey ? translatedEvent : event}
            </Badge>
        );
    };

    const handleFilterChange = (key: keyof AuditFilters, value: string | null) => {
        const newFilters = { ...localFilters, [key]: value === '__all__' ? null : value };
        setLocalFilters(newFilters);
    };

    const applyFilters = () => {
        const queryParams = Object.fromEntries(
            Object.entries(localFilters).filter(([, v]) => v !== null && v !== '')
        ) as Record<string, string>;
        router.get('/audit', queryParams, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const clearFilters = () => {
        const emptyFilters: AuditFilters = {
            user_id: null,
            event: null,
            subject_type: null,
            log_name: null,
            date_from: null,
            date_to: null,
            search: null,
        };
        setLocalFilters(emptyFilters);
        router.get('/audit', {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleExport = () => {
        setIsExporting(true);
        const queryParams = Object.fromEntries(
            Object.entries(localFilters).filter(([, v]) => v !== null && v !== '')
        );
        const params = new URLSearchParams(queryParams as Record<string, string>).toString();
        window.location.href = `/audit/export${params ? '?' + params : ''}`;
        setTimeout(() => setIsExporting(false), 2000);
    };

    const formatDate = (dateString: string) => {
        const date = new Date(dateString);
        const locale = currentLocale() === 'pt_BR' ? ptBR : undefined;
        return format(date, 'dd/MM/yyyy HH:mm:ss', { locale });
    };

    const hasActiveFilters = Object.values(filters).some((v) => v !== null && v !== '');

    const renderChanges = (properties: ActivityProperties) => {
        const { old: oldValues, new: newValues } = properties;

        if (!oldValues && !newValues) {
            return <span className="text-muted-foreground">{t('tenant.audit.no_changes')}</span>;
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
                                        <span className="text-xs text-muted-foreground">{t('tenant.audit.old_value')}</span>
                                        <pre className="mt-1 rounded bg-red-50 p-2 text-xs text-red-700 dark:bg-red-950 dark:text-red-300">
                                            {JSON.stringify(oldVal, null, 2) || '-'}
                                        </pre>
                                    </div>
                                )}
                                {newValues && (
                                    <div>
                                        <span className="text-xs text-muted-foreground">{t('tenant.audit.new_value')}</span>
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
        <>
            <Head title={t('tenant.audit.page_title')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={ClipboardList}>{t('tenant.audit.page_title')}</PageTitle>
                        <PageDescription>
                            {t('tenant.audit.description', { name: tenantData.name })}
                        </PageDescription>
                    </PageHeaderContent>
                    <PageHeaderActions>
                        <Button
                            variant="outline"
                            onClick={handleExport}
                            disabled={isExporting}
                        >
                            <Download className="mr-2 h-4 w-4" />
                            {isExporting ? t('common.loading') : t('tenant.audit.export_csv')}
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => router.reload({ only: ['activities'] })}
                        >
                            <RefreshCw className="mr-2 h-4 w-4" />
                            {t('common.refresh')}
                        </Button>
                    </PageHeaderActions>
                </PageHeader>

                <PageContent>
                    {/* Filters */}
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Filter className="h-4 w-4" />
                                {t('common.filters')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                                {/* Search */}
                                <div className="space-y-2 lg:col-span-2">
                                    <label className="text-sm font-medium">
                                        {t('common.search')}
                                    </label>
                                    <div className="relative">
                                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                        <Input
                                            placeholder={t('tenant.audit.search_placeholder')}
                                            value={localFilters.search || ''}
                                            onChange={(e) => handleFilterChange('search', e.target.value || null)}
                                            className="pl-9"
                                        />
                                    </div>
                                </div>

                                {/* User Filter */}
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">
                                        {t('tenant.audit.filter_user')}
                                    </label>
                                    <Select
                                        value={localFilters.user_id || '__all__'}
                                        onValueChange={(value) => handleFilterChange('user_id', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder={t('common.all')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="__all__">{t('common.all')}</SelectItem>
                                            {teamMembers.map((member) => (
                                                <SelectItem key={member.id} value={String(member.id)}>
                                                    {member.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                {/* Event Type Filter */}
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">
                                        {t('tenant.audit.filter_event')}
                                    </label>
                                    <Select
                                        value={localFilters.event || '__all__'}
                                        onValueChange={(value) => handleFilterChange('event', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder={t('common.all')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="__all__">{t('common.all')}</SelectItem>
                                            {eventTypes.map((event) => {
                                                const translationKey = `tenant.audit.event_${event}`;
                                                const translatedEvent = t(translationKey);
                                                return (
                                                    <SelectItem key={event} value={event}>
                                                        {translatedEvent !== translationKey ? translatedEvent : event}
                                                    </SelectItem>
                                                );
                                            })}
                                        </SelectContent>
                                    </Select>
                                </div>

                                {/* Subject Type Filter */}
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">
                                        {t('tenant.audit.filter_subject')}
                                    </label>
                                    <Select
                                        value={localFilters.subject_type || '__all__'}
                                        onValueChange={(value) => handleFilterChange('subject_type', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder={t('common.all')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="__all__">{t('common.all')}</SelectItem>
                                            {subjectTypes.map((type) => (
                                                <SelectItem key={type.value} value={type.value}>
                                                    {type.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                {/* Log Name Filter */}
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">
                                        {t('tenant.audit.filter_log_name')}
                                    </label>
                                    <Select
                                        value={localFilters.log_name || '__all__'}
                                        onValueChange={(value) => handleFilterChange('log_name', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder={t('common.all')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="__all__">{t('common.all')}</SelectItem>
                                            {logNames.map((name) => (
                                                <SelectItem key={name} value={name}>
                                                    {name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                {/* Date From */}
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">
                                        {t('tenant.audit.filter_date_from')}
                                    </label>
                                    <Input
                                        type="date"
                                        value={localFilters.date_from || ''}
                                        onChange={(e) => handleFilterChange('date_from', e.target.value || null)}
                                    />
                                </div>

                                {/* Date To */}
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">
                                        {t('tenant.audit.filter_date_to')}
                                    </label>
                                    <Input
                                        type="date"
                                        value={localFilters.date_to || ''}
                                        onChange={(e) => handleFilterChange('date_to', e.target.value || null)}
                                    />
                                </div>
                            </div>

                            <div className="mt-4 flex justify-end gap-2">
                                {hasActiveFilters && (
                                    <Button variant="ghost" onClick={clearFilters}>
                                        {t('common.clear_filters')}
                                    </Button>
                                )}
                                <Button onClick={applyFilters}>
                                    {t('common.apply_filters')}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Activity Table */}
                    <div className="rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-[180px]">{t('tenant.audit.column_date')}</TableHead>
                                    <TableHead>{t('tenant.audit.column_user')}</TableHead>
                                    <TableHead>{t('tenant.audit.column_action')}</TableHead>
                                    <TableHead>{t('tenant.audit.column_subject')}</TableHead>
                                    <TableHead>{t('tenant.audit.column_description')}</TableHead>
                                    <TableHead className="w-[100px]">{t('common.actions')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {activities.data.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={6} className="py-8 text-center text-muted-foreground">
                                            {t('tenant.audit.no_activities')}
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
                                                        {t('tenant.audit.system')}
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
                                                    onClick={() => setSelectedActivity(activity)}
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
                                                href={`/audit?page=${activities.current_page - 1}`}
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
                                                href={`/audit?page=${activities.current_page + 1}`}
                                            />
                                        </PaginationItem>
                                    )}
                                </PaginationContent>
                            </Pagination>
                        </div>
                    )}
                </PageContent>
            </Page>

            {/* Activity Details Dialog */}
            <Dialog open={!!selectedActivity} onOpenChange={() => setSelectedActivity(null)}>
                <DialogContent className="max-w-2xl max-h-[80vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{t('tenant.audit.activity_details')}</DialogTitle>
                        <DialogDescription>
                            {selectedActivity?.created_at_formatted}
                        </DialogDescription>
                    </DialogHeader>
                    {selectedActivity && (
                        <div className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <p className="text-sm text-muted-foreground">{t('tenant.audit.column_user')}</p>
                                    <p className="font-medium">
                                        {selectedActivity.causer?.name || t('tenant.audit.system')}
                                    </p>
                                    {selectedActivity.causer?.email && (
                                        <p className="text-sm text-muted-foreground">{selectedActivity.causer.email}</p>
                                    )}
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">{t('tenant.audit.column_action')}</p>
                                    <div className="mt-1">{getEventBadge(selectedActivity.event)}</div>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <p className="text-sm text-muted-foreground">{t('tenant.audit.column_subject')}</p>
                                    <p className="font-medium">
                                        {selectedActivity.subject_type || '-'}
                                    </p>
                                    {selectedActivity.subject_name && (
                                        <p className="text-sm text-muted-foreground">{selectedActivity.subject_name}</p>
                                    )}
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">{t('tenant.audit.filter_log_name')}</p>
                                    <p className="font-medium">{selectedActivity.log_name || 'default'}</p>
                                </div>
                            </div>

                            <div>
                                <p className="text-sm text-muted-foreground">{t('tenant.audit.column_description')}</p>
                                <p className="font-medium">{selectedActivity.description}</p>
                            </div>

                            {/* Changes */}
                            <Collapsible defaultOpen>
                                <CollapsibleTrigger asChild>
                                    <Button variant="ghost" className="w-full justify-between">
                                        <span>{t('tenant.audit.changes')}</span>
                                        <ChevronDown className="h-4 w-4" />
                                    </Button>
                                </CollapsibleTrigger>
                                <CollapsibleContent className="mt-2 rounded-md border p-4">
                                    {renderChanges(selectedActivity.properties)}
                                </CollapsibleContent>
                            </Collapsible>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

AuditLogIndex.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default AuditLogIndex;
