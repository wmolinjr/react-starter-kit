import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AdminLayout from '@/layouts/central/admin-layout';
import admin from '@/routes/central/admin';
import { Head, Link, router } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { AlertCircle, Eye, Network, Pencil, Plus, RefreshCw, Trash2, Users } from 'lucide-react';
import { Page, PageContent, PageDescription, PageHeader, PageHeaderActions, PageHeaderContent, PageTitle } from '@/components/shared/layout/page';
import { type BreadcrumbItem } from '@/types';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';

interface MasterTenant {
    id: string;
    name: string;
    slug: string;
}

interface FederationGroup {
    id: string;
    name: string;
    description: string | null;
    sync_strategy: 'master_wins' | 'last_write_wins' | 'manual_review';
    is_active: boolean;
    created_at: string;
    master_tenant: MasterTenant | null;
    tenants_count: number;
    federated_users_count: number;
}

interface Props {
    groups: FederationGroup[];
}

function FederationIndex({ groups }: Props) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('admin.federation.title'), href: admin.federation.index.url() },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const handleDelete = (group: FederationGroup) => {
        if (group.federated_users_count > 0) {
            alert(t('admin.federation.delete_has_users_error'));
            return;
        }
        if (confirm(t('admin.federation.delete_confirm', { name: group.name }))) {
            router.delete(admin.federation.destroy.url(group.id));
        }
    };

    const getSyncStrategyLabel = (strategy: string) => {
        const labels: Record<string, string> = {
            master_wins: t('admin.federation.sync_strategy.master_wins'),
            last_write_wins: t('admin.federation.sync_strategy.last_write_wins'),
            manual_review: t('admin.federation.sync_strategy.manual_review'),
        };
        return labels[strategy] || strategy;
    };

    const getSyncStrategyVariant = (strategy: string): 'default' | 'secondary' | 'outline' => {
        const variants: Record<string, 'default' | 'secondary' | 'outline'> = {
            master_wins: 'default',
            last_write_wins: 'secondary',
            manual_review: 'outline',
        };
        return variants[strategy] || 'outline';
    };

    const GroupCard = ({ group }: { group: FederationGroup }) => (
        <Card className={!group.is_active ? 'opacity-60' : ''}>
            <CardHeader className="pb-3">
                <div className="flex items-start justify-between">
                    <div className="flex-1">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Network className="h-4 w-4" />
                            {group.name}
                            {!group.is_active && (
                                <Badge variant="secondary" className="text-xs">
                                    {t('common.inactive')}
                                </Badge>
                            )}
                        </CardTitle>
                        {group.master_tenant && (
                            <p className="text-muted-foreground text-xs">
                                {t('admin.federation.master')}: {group.master_tenant.name}
                            </p>
                        )}
                    </div>
                    <div className="flex gap-1">
                        <Button variant="ghost" size="icon" asChild>
                            <Link href={admin.federation.show.url(group.id)}>
                                <Eye className="h-4 w-4" />
                            </Link>
                        </Button>
                        <Button variant="ghost" size="icon" asChild>
                            <Link href={admin.federation.edit.url(group.id)}>
                                <Pencil className="h-4 w-4" />
                            </Link>
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => handleDelete(group)}
                            disabled={group.federated_users_count > 0}
                        >
                            <Trash2 className="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="space-y-3">
                {group.description && (
                    <CardDescription className="text-sm">{group.description}</CardDescription>
                )}
                <div className="flex flex-wrap gap-2">
                    <Badge variant={getSyncStrategyVariant(group.sync_strategy)}>
                        <RefreshCw className="mr-1 h-3 w-3" />
                        {getSyncStrategyLabel(group.sync_strategy)}
                    </Badge>
                    <Badge variant="outline" className="gap-1">
                        <Network className="h-3 w-3" />
                        {t('admin.federation.tenants_count', { count: group.tenants_count })}
                    </Badge>
                    <Badge variant="outline" className="gap-1">
                        <Users className="h-3 w-3" />
                        {t('admin.federation.users_count', { count: group.federated_users_count })}
                    </Badge>
                </div>
                {group.created_at && (
                    <p className="text-muted-foreground text-xs">
                        {t('common.created')}: {new Date(group.created_at).toLocaleDateString()}
                    </p>
                )}
            </CardContent>
        </Card>
    );

    return (
        <>
            <Head title={t('admin.federation.title')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={Network}>{t('admin.federation.title')}</PageTitle>
                        <PageDescription>{t('admin.federation.description')}</PageDescription>
                    </PageHeaderContent>
                    <PageHeaderActions>
                        <Button asChild>
                            <Link href={admin.federation.create.url()}>
                                <Plus className="mr-2 h-4 w-4" />
                                {t('admin.federation.new_group')}
                            </Link>
                        </Button>
                    </PageHeaderActions>
                </PageHeader>

                <PageContent>
                    {groups.length > 0 ? (
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            {groups.map((group) => (
                                <GroupCard key={group.id} group={group} />
                            ))}
                        </div>
                    ) : (
                        <Card className="border-dashed">
                            <CardContent className="flex flex-col items-center justify-center py-12">
                                <Network className="text-muted-foreground mb-4 h-12 w-12" />
                                <h3 className="mb-2 text-lg font-medium">{t('admin.federation.no_groups')}</h3>
                                <p className="text-muted-foreground mb-4 text-center text-sm">
                                    {t('admin.federation.no_groups_description')}
                                </p>
                                <Button asChild>
                                    <Link href={admin.federation.create.url()}>
                                        <Plus className="mr-2 h-4 w-4" />
                                        {t('admin.federation.create_first')}
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    )}

                    <Card className="mt-6">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <AlertCircle className="h-4 w-4" />
                                {t('admin.federation.about_title')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-muted-foreground space-y-2 text-sm">
                            <p>{t('admin.federation.about_description')}</p>
                            <ul className="list-inside list-disc space-y-1">
                                <li>{t('admin.federation.feature_sync_credentials')}</li>
                                <li>{t('admin.federation.feature_sync_profile')}</li>
                                <li>{t('admin.federation.feature_sync_2fa')}</li>
                                <li>{t('admin.federation.feature_conflict_resolution')}</li>
                            </ul>
                        </CardContent>
                    </Card>
                </PageContent>
            </Page>
        </>
    );
}

FederationIndex.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default FederationIndex;
