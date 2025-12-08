import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Page,
    PageContent,
    PageDescription,
    PageHeader,
    PageHeaderActions,
    PageHeaderContent,
    PageTitle,
} from '@/components/shared/layout/page';
import AdminLayout from '@/layouts/tenant/admin-layout';
import admin from '@/routes/tenant/admin';
import { Head, Link, router } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    ArrowLeft,
    Calendar,
    Crown,
    Link as LinkIcon,
    Network,
    RefreshCw,
    Unlink,
    User,
} from 'lucide-react';
import { type BreadcrumbItem } from '@/types';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';

interface TeamMember {
    id: string;
    name: string;
    email: string;
    is_federated: boolean;
    federation_id: string | null;
    roles: { name: string }[];
    created_at: string;
}

interface FederationInfo {
    is_federated: boolean;
    federation_id: string | null;
    is_master_user: boolean;
    federated_user: {
        id: string;
        email: string;
        synced_data: Record<string, unknown>;
        last_synced_at: string | null;
        created_at: string;
    } | null;
    link: {
        id: string;
        status: string;
        sync_enabled: boolean;
        last_synced_at: string | null;
        linked_at: string;
    } | null;
    group: {
        id: string;
        name: string;
        sync_strategy: string;
    } | null;
}

interface Props {
    user: TeamMember;
    federationInfo: FederationInfo;
    canFederate: boolean;
    canUnfederate: boolean;
}

function FederationInfoPage({ user, federationInfo, canFederate, canUnfederate }: Props) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('tenant.team.title'), href: admin.team.index.url() },
        { title: user.name, href: admin.settings.federation.show.url(user.id) },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const handleFederate = () => {
        router.post(admin.settings.federation.users.federate.url(), { user_id: user.id });
    };

    const handleUnfederate = () => {
        if (confirm(t('tenant.federation.unfederate_confirm', { name: user.name }))) {
            router.delete(admin.settings.federation.users.unfederate.url(user.id));
        }
    };

    const handleSync = () => {
        router.post(admin.settings.federation.users.sync.url(user.id));
    };

    const formatDate = (dateString: string | null) => {
        if (!dateString) return '-';
        return new Date(dateString).toLocaleDateString();
    };

    const formatDateTime = (dateString: string | null) => {
        if (!dateString) return '-';
        return new Date(dateString).toLocaleString();
    };

    return (
        <>
            <Head title={`${t('tenant.federation.title')} - ${user.name}`} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={Network}>
                            {t('tenant.federation.user_info_title', { name: user.name })}
                        </PageTitle>
                        <PageDescription>
                            {t('tenant.federation.user_info_description')}
                        </PageDescription>
                    </PageHeaderContent>
                    <PageHeaderActions>
                        <Button variant="outline" asChild>
                            <Link href={admin.team.index.url()}>
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                {t('common.back')}
                            </Link>
                        </Button>
                    </PageHeaderActions>
                </PageHeader>

                <PageContent>
                    {/* User Info Card */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <User className="h-5 w-5" />
                                {t('tenant.federation.user_details')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-4 md:grid-cols-2">
                                <div>
                                    <p className="text-muted-foreground text-sm">{t('common.name')}</p>
                                    <p className="font-medium">{user.name}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground text-sm">{t('common.email')}</p>
                                    <p className="font-medium">{user.email}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground text-sm">{t('common.role')}</p>
                                    <div className="mt-1">
                                        {user.roles?.map((role) => (
                                            <Badge key={role.name} variant="outline" className="mr-1">
                                                {role.name}
                                            </Badge>
                                        ))}
                                    </div>
                                </div>
                                <div>
                                    <p className="text-muted-foreground text-sm">{t('common.created_at')}</p>
                                    <p className="font-medium">{formatDate(user.created_at)}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Federation Status */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2">
                                        <Network className="h-5 w-5" />
                                        {t('tenant.federation.status')}
                                    </CardTitle>
                                    <CardDescription>
                                        {federationInfo.is_federated
                                            ? t('tenant.federation.user_is_federated')
                                            : t('tenant.federation.user_not_federated')}
                                    </CardDescription>
                                </div>
                                <div>
                                    {federationInfo.is_federated ? (
                                        <Badge variant="default">
                                            <LinkIcon className="mr-1 h-3 w-3" />
                                            {t('tenant.federation.federated')}
                                        </Badge>
                                    ) : (
                                        <Badge variant="secondary">
                                            {t('tenant.federation.local_only')}
                                        </Badge>
                                    )}
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {federationInfo.is_federated ? (
                                <div className="space-y-6">
                                    {/* Master User Alert */}
                                    {federationInfo.is_master_user && (
                                        <Alert>
                                            <Crown className="h-4 w-4" />
                                            <AlertTitle>{t('tenant.federation.master_user')}</AlertTitle>
                                            <AlertDescription>
                                                {t('tenant.federation.master_user_description')}
                                            </AlertDescription>
                                        </Alert>
                                    )}

                                    {/* Federation Details */}
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <div>
                                            <p className="text-muted-foreground text-sm">
                                                {t('tenant.federation.federation_id')}
                                            </p>
                                            <p className="font-mono text-sm">{federationInfo.federation_id}</p>
                                        </div>
                                        {federationInfo.group && (
                                            <div>
                                                <p className="text-muted-foreground text-sm">
                                                    {t('tenant.federation.group')}
                                                </p>
                                                <p className="font-medium">{federationInfo.group.name}</p>
                                            </div>
                                        )}
                                        {federationInfo.link && (
                                            <>
                                                <div>
                                                    <p className="text-muted-foreground text-sm">
                                                        {t('tenant.federation.link_status')}
                                                    </p>
                                                    <Badge
                                                        variant={
                                                            federationInfo.link.status === 'active'
                                                                ? 'default'
                                                                : 'secondary'
                                                        }
                                                    >
                                                        {federationInfo.link.status}
                                                    </Badge>
                                                </div>
                                                <div>
                                                    <p className="text-muted-foreground text-sm">
                                                        {t('tenant.federation.sync_enabled')}
                                                    </p>
                                                    <p className="font-medium">
                                                        {federationInfo.link.sync_enabled
                                                            ? t('common.yes')
                                                            : t('common.no')}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p className="text-muted-foreground text-sm">
                                                        {t('tenant.federation.last_synced')}
                                                    </p>
                                                    <p className="font-medium">
                                                        {formatDateTime(federationInfo.link.last_synced_at)}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p className="text-muted-foreground text-sm">
                                                        {t('tenant.federation.linked_at')}
                                                    </p>
                                                    <p className="font-medium">
                                                        {formatDate(federationInfo.link.linked_at)}
                                                    </p>
                                                </div>
                                            </>
                                        )}
                                    </div>

                                    {/* Actions */}
                                    <div className="flex gap-2">
                                        <Button onClick={handleSync} variant="outline">
                                            <RefreshCw className="mr-2 h-4 w-4" />
                                            {t('tenant.federation.sync_now')}
                                        </Button>
                                        {canUnfederate && (
                                            <Button onClick={handleUnfederate} variant="destructive">
                                                <Unlink className="mr-2 h-4 w-4" />
                                                {t('tenant.federation.unfederate')}
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    <p className="text-muted-foreground">
                                        {t('tenant.federation.user_not_federated_description')}
                                    </p>
                                    {canFederate && (
                                        <Button onClick={handleFederate}>
                                            <LinkIcon className="mr-2 h-4 w-4" />
                                            {t('tenant.federation.federate_user')}
                                        </Button>
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Synced Data (if federated) */}
                    {federationInfo.is_federated && federationInfo.federated_user?.synced_data && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Calendar className="h-5 w-5" />
                                    {t('tenant.federation.synced_data')}
                                </CardTitle>
                                <CardDescription>
                                    {t('tenant.federation.synced_data_description')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <pre className="bg-muted rounded-lg p-4 text-sm overflow-auto">
                                    {JSON.stringify(federationInfo.federated_user.synced_data, null, 2)}
                                </pre>
                            </CardContent>
                        </Card>
                    )}
                </PageContent>
            </Page>
        </>
    );
}

FederationInfoPage.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default FederationInfoPage;
