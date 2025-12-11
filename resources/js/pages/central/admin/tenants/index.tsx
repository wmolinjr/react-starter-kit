import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/layouts/central/admin-layout';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Trash2, Eye, Pencil, Search, Users, Globe, LogIn } from 'lucide-react';
import { useState, type ReactElement } from 'react';
import { Page, PageHeader, PageHeaderContent, PageHeaderActions, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { useImpersonation } from '@/hooks/central/use-impersonation';
import admin from '@/routes/central/admin';
import {
    type BreadcrumbItem,
    type TenantResource,
    type UserSummaryResource,
    type DomainResource,
    type PlanSummaryResource,
    type InertiaPaginatedResponse,
} from '@/types';
import { useLaravelReactI18n } from 'laravel-react-i18n';

/**
 * Extended TenantResource with users for impersonation feature.
 * The index page receives users for quick impersonation buttons.
 * TODO: Create TenantWithUsersResource in backend for auto-generation
 */
interface TenantWithUsers extends Omit<TenantResource, 'domains' | 'plan'> {
    domains: DomainResource[];
    plan: PlanSummaryResource | null;
    users: UserSummaryResource[];
}

interface Props {
    tenants: InertiaPaginatedResponse<TenantWithUsers>;
    filters: { search?: string };
    isImpersonating?: boolean;
}

function TenantsIndex({ tenants, filters, isImpersonating }: Props) {
    const { t } = useLaravelReactI18n();
    const [search, setSearch] = useState(filters.search || '');
    const { impersonatingId, impersonateTenant, impersonateAsUser } = useImpersonation();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('dashboard.page.title'), href: admin.dashboard.url() },
        { title: t('tenants.page.title'), href: admin.tenants.index.url() },
    ];
    useSetBreadcrumbs(breadcrumbs);

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(admin.tenants.index.url(), { search }, { preserveState: true });
    };

    const handleDelete = (tenantId: string) => {
        if (confirm(t('common.confirm_delete'))) {
            router.delete(admin.tenants.destroy.url(tenantId));
        }
    };

    return (
        <>
            <Head title={t('tenants.page.title')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle>{t('tenants.page.title')}</PageTitle>
                        <PageDescription>{t('tenants.page.description')}</PageDescription>
                    </PageHeaderContent>
                    <PageHeaderActions>
                        {isImpersonating && (
                            <Badge variant="destructive">{t('tenants.page.currently_impersonating')}</Badge>
                        )}
                    </PageHeaderActions>
                </PageHeader>

                <PageContent>
                    <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <CardTitle>{t('tenants.page.all_tenants')}</CardTitle>
                            <form onSubmit={handleSearch} className="flex gap-2">
                                <Input
                                    placeholder={t('tenants.page.search_placeholder')}
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="w-64"
                                />
                                <Button type="submit" variant="outline" size="icon">
                                    <Search className="h-4 w-4" />
                                </Button>
                            </form>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('common.name')}</TableHead>
                                    <TableHead>Domain</TableHead>
                                    <TableHead>Plan</TableHead>
                                    <TableHead>Users</TableHead>
                                    <TableHead>{t('common.created')}</TableHead>
                                    <TableHead>{t('common.actions')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {tenants.data.map((tenant) => (
                                    <TableRow key={tenant.id}>
                                        <TableCell className="font-medium">{tenant.name}</TableCell>
                                        <TableCell>
                                            {tenant.domains?.[0] && (
                                                <div className="flex items-center gap-1">
                                                    <Globe className="text-muted-foreground h-3 w-3" />
                                                    <span className="text-sm">{tenant.domains[0].domain}</span>
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {tenant.plan ? (
                                                <Badge variant="outline">{tenant.plan.name}</Badge>
                                            ) : (
                                                <Badge variant="secondary">{t('tenants.page.no_plan')}</Badge>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-1">
                                                <Users className="text-muted-foreground h-3 w-3" />
                                                <span>{tenant.users_count}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {new Date(tenant.created_at).toLocaleDateString()}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-1">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => impersonateTenant(tenant.id)}
                                                >
                                                    <LogIn className="mr-1 h-3 w-3" />
                                                    {t('tenants.page.impersonate')}
                                                </Button>
                                                {tenant.users?.slice(0, 2).map((user) => (
                                                    <Button
                                                        key={user.id}
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => impersonateAsUser(tenant.id, user.id)}
                                                        disabled={impersonatingId === user.id}
                                                        title={t('tenants.impersonate_as', { name: user.name })}
                                                    >
                                                        <LogIn className="mr-1 h-3 w-3" />
                                                        {impersonatingId === user.id ? '...' : user.name.split(' ')[0]}
                                                    </Button>
                                                ))}
                                                <Button variant="ghost" size="icon" asChild>
                                                    <Link href={admin.tenants.show.url(tenant.id)}>
                                                        <Eye className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                                <Button variant="ghost" size="icon" asChild>
                                                    <Link href={admin.tenants.edit.url(tenant.id)}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => handleDelete(tenant.id)}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        {tenants.data.length === 0 && (
                            <div className="py-12 text-center">
                                <p className="text-muted-foreground">{t('tenants.page.no_tenants')}</p>
                            </div>
                        )}

                        {tenants.last_page > 1 && (
                            <div className="mt-4 flex justify-center gap-2">
                                {tenants.links.map((link, i) => (
                                    <Button
                                        key={i}
                                        variant={link.active ? 'default' : 'outline'}
                                        size="sm"
                                        disabled={!link.url}
                                        onClick={() => link.url && router.get(link.url)}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
                </PageContent>
            </Page>
        </>
    );
}

TenantsIndex.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default TenantsIndex;
