import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import CentralAdminLayout from '@/layouts/central-admin-layout';
import admin from '@/routes/central/admin';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Building2, Calendar, Mail } from 'lucide-react';
import { Page, PageHeader, PageHeaderContent, PageHeaderActions, PageTitle, PageDescription, PageContent } from '@/components/page';
import { type BreadcrumbItem } from '@/types';
import { useLaravelReactI18n } from 'laravel-react-i18n';

interface Tenant {
    id: string;
    name: string;
    domains: { domain: string }[];
}

interface User {
    id: string;
    name: string;
    email: string;
    email_verified_at: string | null;
    created_at: string;
    tenants: Tenant[];
}

interface Props {
    user: User;
}

export default function UserShow({ user }: Props) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: admin.dashboard.url() },
        { title: 'Users', href: admin.users.index.url() },
        { title: user.name, href: admin.users.show.url(user.id) },
    ];

    return (
        <CentralAdminLayout breadcrumbs={breadcrumbs}>
            <Head title={`User: ${user.name}`} />

            <Page>
                <PageHeader>
                    <PageHeaderActions>
                        <Button variant="outline" size="icon" asChild>
                            <Link href={admin.users.index.url()}>
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                    </PageHeaderActions>
                    <PageHeaderContent>
                        <PageTitle>{user.name}</PageTitle>
                        <PageDescription>{user.email}</PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    <div className="grid gap-6 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('admin.users.user_details')}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center gap-3">
                                <Mail className="text-muted-foreground h-5 w-5" />
                                <div>
                                    <p className="text-sm font-medium">{t('common.email')}</p>
                                    <p className="text-muted-foreground text-sm">{user.email}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <Calendar className="text-muted-foreground h-5 w-5" />
                                <div>
                                    <p className="text-sm font-medium">{t('common.created')}</p>
                                    <p className="text-muted-foreground text-sm">
                                        {new Date(user.created_at).toLocaleDateString()}
                                    </p>
                                </div>
                            </div>
                            <div>
                                <p className="text-sm font-medium">{t('common.status')}</p>
                                {user.email_verified_at ? (
                                    <Badge variant="default">{t('admin.users.email_verified')}</Badge>
                                ) : (
                                    <Badge variant="secondary">{t('admin.users.pending_verification')}</Badge>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Building2 className="h-5 w-5" />
                                Tenants ({user.tenants.length})
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {user.tenants.length > 0 ? (
                                <div className="space-y-3">
                                    {user.tenants.map((tenant) => (
                                        <div
                                            key={tenant.id}
                                            className="flex items-center justify-between rounded-lg border p-3"
                                        >
                                            <div>
                                                <p className="font-medium">{tenant.name}</p>
                                                {tenant.domains[0] && (
                                                    <p className="text-muted-foreground text-xs">
                                                        {tenant.domains[0].domain}
                                                    </p>
                                                )}
                                            </div>
                                            <Button variant="outline" size="sm" asChild>
                                                <Link href={`/admin/tenants/${tenant.id}`}>{t('common.view')}</Link>
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-muted-foreground text-sm">
                                    {t('admin.users.no_tenant_membership')}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </div>
                </PageContent>
            </Page>
        </CentralAdminLayout>
    );
}
