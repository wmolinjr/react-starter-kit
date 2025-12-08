import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AdminLayout from '@/layouts/central/admin-layout';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import admin from '@/routes/central/admin';
import { Head, Link } from '@inertiajs/react';
import { CreditCard, Globe, Package, Users } from 'lucide-react';
import { Page, PageHeader, PageHeaderContent, PageHeaderActions, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { type BreadcrumbItem } from '@/types';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { type ReactElement } from 'react';

interface Props {
    tenant: {
        id: string;
        name: string;
        slug: string;
        created_at: string;
        plan: { id: string; name: string } | null;
        domains: { id: string; domain: string; is_primary: boolean }[];
        users: { id: string; name: string; email: string }[];
        addons: { id: string; name: string; status: string }[];
    };
}

function TenantShow({ tenant }: Props) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: admin.dashboard.url() },
        { title: 'Tenants', href: admin.tenants.index.url() },
        { title: tenant.name, href: admin.tenants.show.url(tenant.id) },
    ];
    useSetBreadcrumbs(breadcrumbs);

    return (
        <>
            <Head title={`Tenant: ${tenant.name}`} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle>{tenant.name}</PageTitle>
                        <PageDescription>ID: {tenant.id}</PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    <div className="grid gap-6 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Globe className="h-5 w-5" />
                                Domains
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {tenant.domains.length > 0 ? (
                                <div className="space-y-2">
                                    {tenant.domains.map((domain) => (
                                        <div
                                            key={domain.id}
                                            className="flex items-center justify-between rounded-lg border p-3"
                                        >
                                            <span>{domain.domain}</span>
                                            {domain.is_primary && (
                                                <Badge variant="default">{t('admin.tenants.primary')}</Badge>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-muted-foreground text-sm">{t('admin.tenants.no_domains')}</p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <CreditCard className="h-5 w-5" />
                                Plan
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {tenant.plan ? (
                                <div className="flex items-center justify-between">
                                    <span className="font-medium">{tenant.plan.name}</span>
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={`/admin/tenants/${tenant.id}/edit`}>{t('common.change')}</Link>
                                    </Button>
                                </div>
                            ) : (
                                <p className="text-muted-foreground text-sm">{t('admin.tenants.no_plan_assigned')}</p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Users className="h-5 w-5" />
                                Users ({tenant.users.length})
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {tenant.users.length > 0 ? (
                                <div className="space-y-2">
                                    {tenant.users.slice(0, 5).map((user) => (
                                        <div
                                            key={user.id}
                                            className="flex items-center justify-between rounded-lg border p-3"
                                        >
                                            <div>
                                                <p className="font-medium">{user.name}</p>
                                                <p className="text-muted-foreground text-xs">{user.email}</p>
                                            </div>
                                            <Button variant="outline" size="sm" asChild>
                                                <Link href={`/admin/users/${user.id}`}>{t('common.view')}</Link>
                                            </Button>
                                        </div>
                                    ))}
                                    {tenant.users.length > 5 && (
                                        <p className="text-muted-foreground text-center text-sm">
                                            +{tenant.users.length - 5} {t('admin.tenants.more_users')}
                                        </p>
                                    )}
                                </div>
                            ) : (
                                <p className="text-muted-foreground text-sm">{t('admin.tenants.no_users')}</p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Package className="h-5 w-5" />
                                Add-ons ({tenant.addons.length})
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {tenant.addons.length > 0 ? (
                                <div className="space-y-2">
                                    {tenant.addons.map((addon: { id: string; name: string; status: string }) => (
                                        <div
                                            key={addon.id}
                                            className="flex items-center justify-between rounded-lg border p-3"
                                        >
                                            <span>{addon.name}</span>
                                            <Badge
                                                variant={addon.status === 'active' ? 'default' : 'secondary'}
                                            >
                                                {addon.status}
                                            </Badge>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-muted-foreground text-sm">{t('admin.tenants.no_addons')}</p>
                            )}
                        </CardContent>
                    </Card>
                </div>
                </PageContent>
            </Page>
        </>
    );
}

TenantShow.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default TenantShow;
