import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ClipboardList, Download, RefreshCw } from 'lucide-react';

import AdminLayout from '@/layouts/central/admin-layout';
import admin from '@/routes/central/admin';
import { Button } from '@/components/ui/button';
import {
    type BreadcrumbItem,
    type ActivityResource,
    type InertiaPaginatedResponse,
} from '@/types';
import {
    Page,
    PageHeader,
    PageHeaderContent,
    PageHeaderActions,
    PageTitle,
    PageDescription,
    PageContent,
} from '@/components/shared/layout/page';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';

import {
    AuditFilters,
    AuditTable,
    AuditDetailDialog,
    EMPTY_FILTERS,
    type AuditFiltersType,
    type AuditPageConfig,
    type SubjectType,
} from '@/components/shared/audit';

// Central UserSummaryResource has the same shape as Tenant's
interface UserSummaryResource {
    id: string;
    name: string;
    email: string;
}

interface Props {
    activities: InertiaPaginatedResponse<ActivityResource>;
    adminUsers: UserSummaryResource[];
    eventTypes: string[];
    subjectTypes: SubjectType[];
    logNames: string[];
    filters: AuditFiltersType;
}

const AUDIT_CONFIG: AuditPageConfig = {
    translationPrefix: 'admin.audit',
    baseUrl: '/admin/audit',
    exportUrl: '/admin/audit/export',
    userLabelKey: 'audit.filter.user',
};

function AuditLogIndex({
    activities,
    adminUsers,
    eventTypes,
    subjectTypes,
    logNames,
    filters,
}: Props) {
    const { t } = useLaravelReactI18n();
    const [localFilters, setLocalFilters] = useState<AuditFiltersType>(filters);
    const [selectedActivity, setSelectedActivity] = useState<ActivityResource | null>(null);
    const [isExporting, setIsExporting] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('dashboard.page.title'), href: admin.dashboard.url() },
        { title: t('audit.page.title'), href: admin.audit.index.url() },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const applyFilters = () => {
        const queryParams = Object.fromEntries(
            Object.entries(localFilters).filter(([, v]) => v !== null && v !== '')
        ) as Record<string, string>;
        router.get(AUDIT_CONFIG.baseUrl, queryParams, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const clearFilters = () => {
        setLocalFilters(EMPTY_FILTERS);
        router.get(AUDIT_CONFIG.baseUrl, {}, {
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
        window.location.href = `${AUDIT_CONFIG.exportUrl}${params ? '?' + params : ''}`;
        setTimeout(() => setIsExporting(false), 2000);
    };

    return (
        <>
            <Head title={t('audit.page.title')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={ClipboardList}>{t('audit.page.title')}</PageTitle>
                        <PageDescription>
                            {t('audit.page.description')}
                        </PageDescription>
                    </PageHeaderContent>
                    <PageHeaderActions>
                        <Button
                            variant="outline"
                            onClick={handleExport}
                            disabled={isExporting}
                        >
                            <Download className="mr-2 h-4 w-4" />
                            {isExporting ? t('common.loading') : t('audit.page.export_csv')}
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
                    <AuditFilters
                        filters={localFilters}
                        users={adminUsers}
                        eventTypes={eventTypes}
                        subjectTypes={subjectTypes}
                        logNames={logNames}
                        config={AUDIT_CONFIG}
                        onFiltersChange={setLocalFilters}
                        onApplyFilters={applyFilters}
                        onClearFilters={clearFilters}
                    />

                    <AuditTable
                        activities={activities}
                        config={AUDIT_CONFIG}
                        onViewDetails={setSelectedActivity}
                    />
                </PageContent>
            </Page>

            <AuditDetailDialog
                activity={selectedActivity}
                open={!!selectedActivity}
                onOpenChange={(open) => !open && setSelectedActivity(null)}
                config={AUDIT_CONFIG}
            />
        </>
    );
}

AuditLogIndex.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default AuditLogIndex;
