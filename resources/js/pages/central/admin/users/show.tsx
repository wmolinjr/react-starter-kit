import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AdminLayout from '@/layouts/central/admin-layout';
import admin from '@/routes/central/admin';
import { Head } from '@inertiajs/react';
import { Calendar, Mail, Shield } from 'lucide-react';
import { Page, PageHeader, PageHeaderContent, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { type BreadcrumbItem, type CentralUserDetailResource } from '@/types';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';

interface Props {
    user: CentralUserDetailResource;
}

function UserShow({ user }: Props) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: admin.dashboard.url() },
        { title: 'Users', href: admin.users.index.url() },
        { title: user.name, href: admin.users.show.url(user.id) },
    ];

    useSetBreadcrumbs(breadcrumbs);

    return (
        <>
            <Head title={`User: ${user.name}`} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle>{user.name}</PageTitle>
                        <PageDescription>{user.email}</PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    <div className="grid gap-6 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('users.page.user_details')}</CardTitle>
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
                                    <Badge variant="default">{t('users.page.email_verified')}</Badge>
                                ) : (
                                    <Badge variant="secondary">{t('users.page.pending_verification')}</Badge>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Shield className="h-5 w-5" />
                                {t('common.role')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                <div className="flex items-center justify-between rounded-lg border p-3">
                                    <div>
                                        <p className="font-medium">
                                            {user.role_display_name || user.role || t('common.no_role')}
                                        </p>
                                        <p className="text-muted-foreground text-xs">
                                            {user.is_super_admin
                                                ? t('users.page.super_admin_description')
                                                : t('users.page.admin_description')}
                                        </p>
                                    </div>
                                    <Badge variant={user.is_super_admin ? 'default' : 'secondary'}>
                                        {user.role_display_name || user.role || t('common.no_role')}
                                    </Badge>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
                </PageContent>
            </Page>
        </>
    );
}

UserShow.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default UserShow;
