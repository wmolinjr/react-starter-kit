import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/tenant/admin-layout';
import admin from '@/routes/tenant/admin';
import { Head, router } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    AlertCircle,
    Check,
    Crown,
    Link as LinkIcon,
    Network,
    RefreshCw,
    Settings,
    Unlink,
    User,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import {
    Page,
    PageContent,
    PageDescription,
    PageHeader,
    PageHeaderContent,
    PageTitle,
} from '@/components/shared/layout/page';
import { type BreadcrumbItem } from '@/types';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';

interface FederationGroup {
    id: string;
    name: string;
    description: string | null;
    sync_strategy: string;
    is_master: boolean;
    settings: Record<string, unknown>;
}

interface Membership {
    sync_enabled: boolean;
    joined_at: string | null;
    settings: Record<string, unknown>;
    default_role: string | null;
}

interface Stats {
    federated_users: number;
    local_only_users: number;
    total_users: number;
    pending_syncs: number;
}

interface TeamMember {
    id: string;
    name: string;
    email: string;
    is_federated: boolean;
    federation_id: string | null;
    roles: { name: string }[];
}

interface Props {
    stats: Stats;
    group: FederationGroup | null;
    membership: Membership | null;
    federatedUsers: TeamMember[];
    localOnlyUsers: TeamMember[];
}

function FederationSettings({ stats, group, membership, federatedUsers, localOnlyUsers }: Props) {
    const { t } = useLaravelReactI18n();
    const [federatingUser, setFederatingUser] = useState<string | null>(null);
    const [selectedUserId, setSelectedUserId] = useState<string>('');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('tenant.settings.title'), href: admin.settings.index.url() },
        { title: t('tenant.federation.title'), href: admin.settings.federation.index.url() },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const handleFederateUser = () => {
        if (!selectedUserId) return;
        router.post(
            admin.settings.federation.users.federate.url(),
            { user_id: selectedUserId },
            {
                onSuccess: () => {
                    setFederatingUser(null);
                    setSelectedUserId('');
                },
            },
        );
    };

    const handleUnfederateUser = (userId: string, userName: string) => {
        if (confirm(t('tenant.federation.unfederate_confirm', { name: userName }))) {
            router.delete(admin.settings.federation.users.unfederate.url(userId));
        }
    };

    const handleSyncUser = (userId: string) => {
        router.post(admin.settings.federation.users.sync.url(userId));
    };

    const getSyncStrategyLabel = (strategy: string) => {
        const labels: Record<string, string> = {
            master_wins: t('tenant.federation.sync_strategy.master_wins'),
            last_write_wins: t('tenant.federation.sync_strategy.last_write_wins'),
            manual_review: t('tenant.federation.sync_strategy.manual_review'),
        };
        return labels[strategy] || strategy;
    };

    return (
        <>
            <Head title={t('tenant.federation.title')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={Network}>{t('tenant.federation.title')}</PageTitle>
                        <PageDescription>{t('tenant.federation.description')}</PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    {!group ? (
                        /* Not Federated State */
                        <Card className="border-dashed">
                            <CardContent className="flex flex-col items-center justify-center py-12">
                                <Network className="text-muted-foreground mb-4 h-12 w-12" />
                                <h3 className="mb-2 text-lg font-medium">{t('tenant.federation.not_federated')}</h3>
                                <p className="text-muted-foreground mb-4 text-center text-sm">
                                    {t('tenant.federation.not_federated_description')}
                                </p>
                            </CardContent>
                        </Card>
                    ) : (
                        <>
                            {/* Federation Status */}
                            <Card>
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <CardTitle className="flex items-center gap-2">
                                                <Network className="h-5 w-5" />
                                                {group.name}
                                                {group.is_master && (
                                                    <Badge variant="default" className="ml-2">
                                                        <Crown className="mr-1 h-3 w-3" />
                                                        {t('tenant.federation.master')}
                                                    </Badge>
                                                )}
                                            </CardTitle>
                                            <CardDescription>
                                                {group.description || t('tenant.federation.group_member')}
                                            </CardDescription>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {membership?.sync_enabled ? (
                                                <Badge variant="default">
                                                    <RefreshCw className="mr-1 h-3 w-3" />
                                                    {t('tenant.federation.sync_active')}
                                                </Badge>
                                            ) : (
                                                <Badge variant="secondary">
                                                    {t('tenant.federation.sync_paused')}
                                                </Badge>
                                            )}
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div className="grid gap-4 md:grid-cols-3">
                                        <div>
                                            <p className="text-muted-foreground text-sm">
                                                {t('tenant.federation.sync_strategy.title')}
                                            </p>
                                            <p className="font-medium">{getSyncStrategyLabel(group.sync_strategy)}</p>
                                        </div>
                                        <div>
                                            <p className="text-muted-foreground text-sm">
                                                {t('tenant.federation.joined_at')}
                                            </p>
                                            <p className="font-medium">
                                                {membership?.joined_at
                                                    ? new Date(membership.joined_at).toLocaleDateString()
                                                    : '-'}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-muted-foreground text-sm">
                                                {t('tenant.federation.default_role')}
                                            </p>
                                            <p className="font-medium">{membership?.default_role || 'member'}</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Stats */}
                            <div className="grid gap-4 md:grid-cols-4">
                                <Card>
                                    <CardContent className="pt-6">
                                        <div className="flex items-center gap-4">
                                            <div className="bg-primary/10 rounded-full p-3">
                                                <Users className="text-primary h-5 w-5" />
                                            </div>
                                            <div>
                                                <p className="text-muted-foreground text-sm">
                                                    {t('tenant.federation.total_users')}
                                                </p>
                                                <p className="text-2xl font-bold">{stats.total_users}</p>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardContent className="pt-6">
                                        <div className="flex items-center gap-4">
                                            <div className="rounded-full bg-green-100 p-3 dark:bg-green-900">
                                                <LinkIcon className="h-5 w-5 text-green-600 dark:text-green-400" />
                                            </div>
                                            <div>
                                                <p className="text-muted-foreground text-sm">
                                                    {t('tenant.federation.federated_users')}
                                                </p>
                                                <p className="text-2xl font-bold">{stats.federated_users}</p>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardContent className="pt-6">
                                        <div className="flex items-center gap-4">
                                            <div className="bg-muted rounded-full p-3">
                                                <User className="text-muted-foreground h-5 w-5" />
                                            </div>
                                            <div>
                                                <p className="text-muted-foreground text-sm">
                                                    {t('tenant.federation.local_only')}
                                                </p>
                                                <p className="text-2xl font-bold">{stats.local_only_users}</p>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                                {stats.pending_syncs > 0 && (
                                    <Card className="border-yellow-500">
                                        <CardContent className="pt-6">
                                            <div className="flex items-center gap-4">
                                                <div className="rounded-full bg-yellow-100 p-3 dark:bg-yellow-900">
                                                    <RefreshCw className="h-5 w-5 text-yellow-600 dark:text-yellow-400" />
                                                </div>
                                                <div>
                                                    <p className="text-muted-foreground text-sm">
                                                        {t('tenant.federation.pending_syncs')}
                                                    </p>
                                                    <p className="text-2xl font-bold">{stats.pending_syncs}</p>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                )}
                            </div>

                            {/* Master Tenant Info */}
                            {group.is_master && (
                                <Alert>
                                    <Crown className="h-4 w-4" />
                                    <AlertTitle>{t('tenant.federation.master_tenant')}</AlertTitle>
                                    <AlertDescription>
                                        {t('tenant.federation.master_tenant_description')}
                                    </AlertDescription>
                                </Alert>
                            )}

                            {/* Federated Users */}
                            <Card>
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <CardTitle>{t('tenant.federation.federated_users')}</CardTitle>
                                            <CardDescription>
                                                {t('tenant.federation.federated_users_description')}
                                            </CardDescription>
                                        </div>
                                        {localOnlyUsers.length > 0 && (
                                            <Dialog open={federatingUser !== null} onOpenChange={(open) => !open && setFederatingUser(null)}>
                                                <DialogTrigger asChild>
                                                    <Button onClick={() => setFederatingUser('dialog')}>
                                                        <LinkIcon className="mr-2 h-4 w-4" />
                                                        {t('tenant.federation.federate_user')}
                                                    </Button>
                                                </DialogTrigger>
                                                <DialogContent>
                                                    <DialogHeader>
                                                        <DialogTitle>{t('tenant.federation.federate_user')}</DialogTitle>
                                                        <DialogDescription>
                                                            {t('tenant.federation.federate_user_description')}
                                                        </DialogDescription>
                                                    </DialogHeader>
                                                    <div className="space-y-4 py-4">
                                                        <div className="space-y-2">
                                                            <Label>{t('tenant.federation.select_user')}</Label>
                                                            <Select
                                                                value={selectedUserId}
                                                                onValueChange={setSelectedUserId}
                                                            >
                                                                <SelectTrigger>
                                                                    <SelectValue
                                                                        placeholder={t('tenant.federation.select_user_placeholder')}
                                                                    />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {localOnlyUsers.map((user) => (
                                                                        <SelectItem key={user.id} value={user.id}>
                                                                            {user.name} ({user.email})
                                                                        </SelectItem>
                                                                    ))}
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                        <Alert>
                                                            <AlertCircle className="h-4 w-4" />
                                                            <AlertDescription>
                                                                {t('tenant.federation.federate_warning')}
                                                            </AlertDescription>
                                                        </Alert>
                                                    </div>
                                                    <DialogFooter>
                                                        <Button variant="outline" onClick={() => setFederatingUser(null)}>
                                                            {t('common.cancel')}
                                                        </Button>
                                                        <Button onClick={handleFederateUser} disabled={!selectedUserId}>
                                                            {t('tenant.federation.federate')}
                                                        </Button>
                                                    </DialogFooter>
                                                </DialogContent>
                                            </Dialog>
                                        )}
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    {federatedUsers.length > 0 ? (
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>{t('common.user')}</TableHead>
                                                    <TableHead>{t('common.role')}</TableHead>
                                                    <TableHead>{t('common.status')}</TableHead>
                                                    <TableHead>{t('common.actions')}</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {federatedUsers.map((user) => (
                                                    <TableRow key={user.id}>
                                                        <TableCell>
                                                            <div className="flex items-center gap-3">
                                                                <div className="bg-muted flex h-8 w-8 items-center justify-center rounded-full">
                                                                    <User className="h-4 w-4" />
                                                                </div>
                                                                <div>
                                                                    <p className="font-medium">{user.name}</p>
                                                                    <p className="text-muted-foreground text-xs">
                                                                        {user.email}
                                                                    </p>
                                                                </div>
                                                            </div>
                                                        </TableCell>
                                                        <TableCell>
                                                            {user.roles?.map((role) => (
                                                                <Badge key={role.name} variant="outline" className="mr-1">
                                                                    {role.name}
                                                                </Badge>
                                                            ))}
                                                        </TableCell>
                                                        <TableCell>
                                                            <Badge variant="default">
                                                                <LinkIcon className="mr-1 h-3 w-3" />
                                                                {t('tenant.federation.federated')}
                                                            </Badge>
                                                        </TableCell>
                                                        <TableCell>
                                                            <div className="flex gap-1">
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() => handleSyncUser(user.id)}
                                                                    title={t('tenant.federation.sync')}
                                                                >
                                                                    <RefreshCw className="h-4 w-4" />
                                                                </Button>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        handleUnfederateUser(user.id, user.name)
                                                                    }
                                                                    title={t('tenant.federation.unfederate')}
                                                                >
                                                                    <Unlink className="h-4 w-4" />
                                                                </Button>
                                                            </div>
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    ) : (
                                        <div className="py-8 text-center">
                                            <Users className="text-muted-foreground mx-auto mb-4 h-12 w-12" />
                                            <p className="text-muted-foreground">
                                                {t('tenant.federation.no_federated_users')}
                                            </p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Local Only Users */}
                            {localOnlyUsers.length > 0 && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle>{t('tenant.federation.local_only_users')}</CardTitle>
                                        <CardDescription>
                                            {t('tenant.federation.local_only_users_description')}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>{t('common.user')}</TableHead>
                                                    <TableHead>{t('common.role')}</TableHead>
                                                    <TableHead>{t('common.status')}</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {localOnlyUsers.map((user) => (
                                                    <TableRow key={user.id}>
                                                        <TableCell>
                                                            <div className="flex items-center gap-3">
                                                                <div className="bg-muted flex h-8 w-8 items-center justify-center rounded-full">
                                                                    <User className="h-4 w-4" />
                                                                </div>
                                                                <div>
                                                                    <p className="font-medium">{user.name}</p>
                                                                    <p className="text-muted-foreground text-xs">
                                                                        {user.email}
                                                                    </p>
                                                                </div>
                                                            </div>
                                                        </TableCell>
                                                        <TableCell>
                                                            {user.roles?.map((role) => (
                                                                <Badge key={role.name} variant="outline" className="mr-1">
                                                                    {role.name}
                                                                </Badge>
                                                            ))}
                                                        </TableCell>
                                                        <TableCell>
                                                            <Badge variant="secondary">
                                                                {t('tenant.federation.local_only')}
                                                            </Badge>
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </CardContent>
                                </Card>
                            )}
                        </>
                    )}
                </PageContent>
            </Page>
        </>
    );
}

FederationSettings.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default FederationSettings;
