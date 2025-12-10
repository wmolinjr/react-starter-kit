import type { ActivityResource, UserSummaryResource, InertiaPaginatedResponse } from '@/types';

export interface SubjectType {
    value: string;
    label: string;
}

export interface AuditFilters {
    user_id: string | null;
    event: string | null;
    subject_type: string | null;
    log_name: string | null;
    date_from: string | null;
    date_to: string | null;
    search: string | null;
}

export interface AuditPageConfig {
    /** Translation prefix: 'tenant.audit' or 'admin.audit' */
    translationPrefix: string;
    /** Base URL for navigation: '/audit' or '/admin/audit' */
    baseUrl: string;
    /** Export URL: '/audit/export' or '/admin/audit/export' */
    exportUrl: string;
    /** User label translation key: 'tenant.audit.filter_user' or 'admin.audit.filter_user' */
    userLabelKey: string;
}

export interface AuditFiltersProps {
    filters: AuditFilters;
    users: UserSummaryResource[];
    eventTypes: string[];
    subjectTypes: SubjectType[];
    logNames: string[];
    config: AuditPageConfig;
    onFiltersChange: (filters: AuditFilters) => void;
    onApplyFilters: () => void;
    onClearFilters: () => void;
}

export interface AuditTableProps {
    activities: InertiaPaginatedResponse<ActivityResource>;
    config: AuditPageConfig;
    onViewDetails: (activity: ActivityResource) => void;
}

export interface AuditDetailDialogProps {
    activity: ActivityResource | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    config: AuditPageConfig;
}

export const EMPTY_FILTERS: AuditFilters = {
    user_id: null,
    event: null,
    subject_type: null,
    log_name: null,
    date_from: null,
    date_to: null,
    search: null,
};
