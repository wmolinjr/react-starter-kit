import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AdminLayout from '@/layouts/central/admin-layout';
import admin from '@/routes/central/admin';
import { Head, Link, router } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { AlertCircle, Eye, Network, Pencil, Plus, RefreshCw, Trash2, Users } from 'lucide-react';
import { Page, PageContent, PageDescription, PageHeader, PageHeaderActions, PageHeaderContent, PageTitle } from '@/components/shared/layout/page';
import { type BreadcrumbItem, type FederationGroupResource } from '@/types';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';

interface Props {
    groups: FederationGroupResource[];
}

function FederationIndex({ groups }: Props) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('dashboard.page.title'), href: admin.dashboard.url() },
        { title: t('federation.page.title'), href: admin.federation.index.url() },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const handleDelete = (group: FederationGroupResource) => {
        if (group.federated_users_count > 0) {
            alert(t('federation.page.delete_has_users_error'));
            return;
        }
        if (confirm(t('federation.delete_confirm', { name: group.name }))) {
            router.delete(admin.federation.destroy.url(group.id));
        }
    };

    const getSyncStrategyLabel = (strategy: string) => {
        const labels: Record<string, string> = {
            master_wins: t('federation.sync_strategy.master_wins'),
            last_write_wins: t('federation.sync_strategy.last_write_wins'),
            manual_review: t('federation.sync_strategy.manual_review'),
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

    const GroupCard = ({ group }: { group: FederationGroupResource }) => (
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
                                {t('federation.page.master')}: {group.master_tenant.name}
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
                        {t('federation.tenants_count', { count: group.tenants_count })}
                    </Badge>
                    <Badge variant="outline" className="gap-1">
                        <Users className="h-3 w-3" />
                        {t('federation.users_count', { count: group.federated_users_count })}
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
            <Head title={t('federation.page.title')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={Network}>{t('federation.page.title')}</PageTitle>
                        <PageDescription>{t('federation.page.description')}</PageDescription>
                    </PageHeaderContent>
                    <PageHeaderActions>
                        <Button asChild>
                            <Link href={admin.federation.create.url()}>
                                <Plus className="mr-2 h-4 w-4" />
                                {t('federation.page.new_group')}
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
                                <h3 className="mb-2 text-lg font-medium">{t('federation.page.no_groups')}</h3>
                                <p className="text-muted-foreground mb-4 text-center text-sm">
                                    {t('federation.page.no_groups_description')}
                                </p>
                                <Button asChild>
                                    <Link href={admin.federation.create.url()}>
                                        <Plus className="mr-2 h-4 w-4" />
                                        {t('federation.page.create_first')}
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    )}

                    <Card className="mt-6">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <AlertCircle className="h-4 w-4" />
                                {t('federation.page.about_title')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-muted-foreground space-y-2 text-sm">
                            <p>{t('federation.page.about_description')}</p>
                            <ul className="list-inside list-disc space-y-1">
                                <li>{t('federation.page.feature_sync_credentials')}</li>
                                <li>{t('federation.page.feature_sync_profile')}</li>
                                <li>{t('federation.page.feature_sync_2fa')}</li>
                                <li>{t('federation.page.feature_conflict_resolution')}</li>
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
