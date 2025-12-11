import { useState, type ReactElement } from 'react';
import { Head, router } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Activity, User, Filter, RefreshCw } from 'lucide-react';
import { format } from 'date-fns';
import { ptBR } from 'date-fns/locale';

import AdminLayout from '@/layouts/tenant/admin-layout';
import admin from '@/routes/tenant/admin';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    type BreadcrumbItem,
    type ActivityResource,
    type UserSummaryResource,
    type TenantSummaryResource,
    type InertiaPaginatedResponse,
} from '@/types';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
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

interface SubjectType {
    value: string;
    label: string;
}

interface ActivityFilters {
    user_id: string | null;
    event: string | null;
    subject_type: string | null;
    date_from: string | null;
    date_to: string | null;
}

interface Props {
    activities: InertiaPaginatedResponse<ActivityResource>;
    teamMembers: UserSummaryResource[];
    eventTypes: string[];
    subjectTypes: SubjectType[];
    filters: ActivityFilters;
    tenant: TenantSummaryResource;
}

function TeamActivity({
    activities,
    teamMembers,
    eventTypes,
    subjectTypes,
    filters,
    tenant: tenantData,
}: Props) {
    const { t, currentLocale } = useLaravelReactI18n();
    const [localFilters, setLocalFilters] = useState<ActivityFilters>(filters);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('dashboard.page.title'), href: admin.dashboard.url() },
        { title: t('team.page.title'), href: admin.team.index.url() },
        { title: t('activity.page.title'), href: admin.team.activity.url() },
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

        const translationKey = `tenant.activity.event_${event}`;
        const translatedEvent = t(translationKey);

        return (
            <Badge variant={variants[event] || 'outline'}>
                {translatedEvent !== translationKey ? translatedEvent : event}
            </Badge>
        );
    };

    const handleFilterChange = (key: keyof ActivityFilters, value: string | null) => {
        const newFilters = { ...localFilters, [key]: value === '__all__' ? null : value };
        setLocalFilters(newFilters);
    };

    const applyFilters = () => {
        const queryParams = Object.fromEntries(
            Object.entries(localFilters).filter(([, v]) => v !== null)
        ) as Record<string, string>;
        router.get(admin.team.activity.url(), queryParams, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const clearFilters = () => {
        const emptyFilters: ActivityFilters = {
            user_id: null,
            event: null,
            subject_type: null,
            date_from: null,
            date_to: null,
        };
        setLocalFilters(emptyFilters);
        router.get(admin.team.activity.url(), {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const formatDate = (dateString: string) => {
        const date = new Date(dateString);
        const locale = currentLocale() === 'pt_BR' ? ptBR : undefined;
        return format(date, 'dd/MM/yyyy HH:mm', { locale });
    };

    const hasActiveFilters = Object.values(filters).some((v) => v !== null);

    return (
        <>
            <Head title={t('activity.page.page_title')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={Activity}>{t('activity.page.page_title')}</PageTitle>
                        <PageDescription>
                            {t('activity.description', { name: tenantData.name })}
                        </PageDescription>
                    </PageHeaderContent>
                    <PageHeaderActions>
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
                            <CardTitle className="text-base flex items-center gap-2">
                                <Filter className="h-4 w-4" />
                                {t('common.filters')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                                {/* User Filter */}
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">
                                        {t('activity.page.filter_user')}
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
                                        {t('activity.page.filter_event')}
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
                                                const translationKey = `tenant.activity.event_${event}`;
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
                                        {t('activity.page.filter_subject')}
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

                                {/* Date From */}
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">
                                        {t('activity.page.filter_date_from')}
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
                                        {t('activity.page.filter_date_to')}
                                    </label>
                                    <Input
                                        type="date"
                                        value={localFilters.date_to || ''}
                                        onChange={(e) => handleFilterChange('date_to', e.target.value || null)}
                                    />
                                </div>
                            </div>

                            <div className="flex justify-end gap-2 mt-4">
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
                                    <TableHead className="w-[180px]">{t('activity.page.column_date')}</TableHead>
                                    <TableHead>{t('activity.page.column_user')}</TableHead>
                                    <TableHead>{t('activity.page.column_action')}</TableHead>
                                    <TableHead>{t('activity.page.column_subject')}</TableHead>
                                    <TableHead>{t('activity.page.column_description')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {activities.data.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={5} className="text-center py-8 text-muted-foreground">
                                            {t('activity.page.no_activities')}
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
                                                        <div className="h-8 w-8 rounded-full bg-muted flex items-center justify-center">
                                                            <User className="h-4 w-4" />
                                                        </div>
                                                        <div className="flex flex-col">
                                                            <span className="font-medium text-sm">
                                                                {activity.causer.name}
                                                            </span>
                                                            <span className="text-xs text-muted-foreground">
                                                                {activity.causer.email}
                                                            </span>
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        {t('activity.page.system')}
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
                                                            <span className="text-xs text-muted-foreground mt-1">
                                                                {activity.subject_name}
                                                            </span>
                                                        )}
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell className="max-w-[300px] truncate">
                                                {activity.description}
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
                                                href={admin.team.activity.url({ query: { page: activities.current_page - 1 } })}
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
                                                href={admin.team.activity.url({ query: { page: activities.current_page + 1 } })}
                                            />
                                        </PaginationItem>
                                    )}
                                </PaginationContent>
                            </Pagination>
                        </div>
                    )}
                </PageContent>
            </Page>
        </>
    );
}

TeamActivity.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default TeamActivity;
