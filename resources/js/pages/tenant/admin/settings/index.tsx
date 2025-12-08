import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AdminLayout from '@/layouts/tenant/admin-layout';
import admin from '@/routes/tenant/admin';
import { Head, Link } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    AlertTriangle,
    Globe,
    Key,
    Languages,
    Network,
    Palette,
    Settings,
    Settings2,
    Shield,
} from 'lucide-react';
import { Page, PageHeader, PageHeaderContent, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { type BreadcrumbItem } from '@/types';

import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { usePermissions } from '@/hooks/shared/use-permissions';
import { usePlan } from '@/hooks/tenant/use-plan';
import { type ReactElement } from 'react';
import type { LucideIcon } from 'lucide-react';
import type { Permission } from '@/types/permissions';
import type { PlanFeatures } from '@/types';

interface Domain {
    id: string;
    domain: string;
    is_primary: boolean;
}

interface Tenant {
    id: string;
    name: string;
    slug: string;
}

interface Props {
    tenant: Tenant;
    settings: Record<string, unknown>;
    domains: Domain[];
}

interface SettingsLink {
    title: string;
    description: string;
    href: string;
    icon: LucideIcon;
    badge?: string;
    variant?: 'destructive';
    permission?: Permission;
    feature?: keyof PlanFeatures;
}

function SettingsIndex({ tenant: tenantData, domains }: Props) {
    const { t } = useLaravelReactI18n();
    const { has } = usePermissions();
    const { hasFeature } = usePlan();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('tenant.settings.title'), href: admin.settings.index.url() },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const allSettingsLinks: SettingsLink[] = [
        {
            title: t('tenant.settings.branding'),
            description: t('tenant.settings.branding_description'),
            href: admin.settings.branding.url(),
            icon: Palette,
            permission: 'branding:view',
            feature: 'whiteLabel',
            badge: 'Enterprise',
        },
        {
            title: t('tenant.settings.domains'),
            description: t('tenant.settings.domains_description'),
            href: admin.settings.domains.url(),
            icon: Globe,
            badge: t('tenant.settings.domains_count', { count: domains.length }),
            permission: 'settings:edit',
        },
        {
            title: t('tenant.settings.language'),
            description: t('tenant.settings.language_description'),
            href: admin.settings.language.url(),
            icon: Languages,
            permission: 'locales:view',
            feature: 'multiLanguage',
        },
        {
            title: t('tenant.config.title'),
            description: t('tenant.config.description', { name: tenantData.name }),
            href: admin.settings.config.url(),
            icon: Settings2,
            permission: 'settings:edit',
        },
        {
            title: t('tenant.settings.api_tokens'),
            description: t('tenant.settings.api_tokens_description'),
            href: admin.settings.apiTokens.url(),
            icon: Key,
            permission: 'apiTokens:view',
        },
        {
            title: t('tenant.settings.custom_roles'),
            description: t('tenant.settings.custom_roles_description'),
            href: admin.settings.roles.index.url(),
            icon: Shield,
            badge: 'Pro+',
            permission: 'roles:view',
            feature: 'customRoles',
        },
        {
            title: t('tenant.settings.federation'),
            description: t('tenant.settings.federation_description'),
            href: admin.settings.federation.index.url(),
            icon: Network,
            badge: 'Enterprise',
            permission: 'federation:view',
            feature: 'federation',
        },
        {
            title: t('tenant.settings.danger_zone'),
            description: t('tenant.settings.danger_zone_description'),
            href: admin.settings.danger.url(),
            icon: AlertTriangle,
            variant: 'destructive',
            permission: 'settings:danger',
        },
    ];

    // Filter links based on permissions and features
    const settingsLinks = allSettingsLinks.filter((link) => {
        // Check permission if specified
        if (link.permission && !has(link.permission)) {
            return false;
        }
        // Check feature if specified
        if (link.feature && !hasFeature(link.feature)) {
            return false;
        }
        return true;
    });

    return (
        <>
            <Head title={t('tenant.settings.title')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={Settings}>{t('tenant.settings.title')}</PageTitle>
                        <PageDescription>
                            {t('tenant.settings.description', { name: tenantData.name })}
                        </PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    <Separator />

                {/* Tenant Info */}
                <Card>
                    <CardHeader>
                        <CardTitle>{t('tenant.settings.tenant_info')}</CardTitle>
                        <CardDescription>
                            {t('tenant.settings.tenant_info_description')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    {t('common.name')}
                                </p>
                                <p className="text-lg">{tenantData.name}</p>
                            </div>
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    Slug
                                </p>
                                <p className="text-lg font-mono">
                                    {tenantData.slug}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Settings Links */}
                <div className="grid gap-4 md:grid-cols-2">
                    {settingsLinks.map((link) => (
                        <Link key={link.href} href={link.href}>
                            <Card
                                className={`hover:border-primary transition-colors cursor-pointer h-full ${
                                    link.variant === 'destructive'
                                        ? 'hover:border-red-500'
                                        : ''
                                }`}
                            >
                                <CardHeader className="flex flex-row items-center gap-4">
                                    <div
                                        className={`p-2 rounded-lg ${
                                            link.variant === 'destructive'
                                                ? 'bg-red-100 text-red-600 dark:bg-red-900 dark:text-red-400'
                                                : 'bg-muted'
                                        }`}
                                    >
                                        <link.icon className="h-5 w-5" />
                                    </div>
                                    <div className="flex-1">
                                        <CardTitle className="text-base flex items-center gap-2">
                                            {link.title}
                                            {link.badge && (
                                                <span className="text-xs font-normal bg-muted px-2 py-0.5 rounded">
                                                    {link.badge}
                                                </span>
                                            )}
                                        </CardTitle>
                                        <CardDescription>
                                            {link.description}
                                        </CardDescription>
                                    </div>
                                </CardHeader>
                            </Card>
                        </Link>
                    ))}
                </div>
                </PageContent>
            </Page>
        </>
    );
}

SettingsIndex.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default SettingsIndex;
