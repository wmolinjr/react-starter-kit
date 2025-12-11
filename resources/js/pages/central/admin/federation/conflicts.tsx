import { FederationConflictStatusBadge } from '@/components/shared/status-badge';
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
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import AdminLayout from '@/layouts/central/admin-layout';
import admin from '@/routes/central/admin';
import {
    type BreadcrumbItem,
    type FederationGroupResource,
    type FederationConflictResource,
} from '@/types';
import { Head, router } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    AlertTriangle,
    Check,
    Clock,
    GitCompare,
    Mail,
    User,
} from 'lucide-react';
import { useState, type ReactElement } from 'react';
import {
    Page,
    PageContent,
    PageDescription,
    PageHeader,
    PageHeaderContent,
    PageTitle,
} from '@/components/shared/layout/page';

/**
 * Extended conflict interface with additional fields from controller
 * The controller returns extra fields not in the base FederationConflictResource
 */
interface ConflictWithSourceTenant extends FederationConflictResource {
    source_value: string;
    target_value: string;
    source_tenant: { id: string; name: string };
}

interface Props {
    group: FederationGroupResource;
    conflicts: ConflictWithSourceTenant[];
}

function FederationConflicts({ group, conflicts }: Props) {
    const { t } = useLaravelReactI18n();
    const [resolving, setResolving] = useState<ConflictWithSourceTenant | null>(null);
    const [resolution, setResolution] = useState<'source' | 'target' | 'custom'>('source');
    const [customValue, setCustomValue] = useState('');
    const [notes, setNotes] = useState('');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('dashboard.page.title'), href: admin.dashboard.url() },
        { title: t('federation.page.title'), href: admin.federation.index.url() },
        { title: group.name, href: admin.federation.show.url(group.id) },
        { title: t('federation.page.conflicts'), href: admin.federation.conflicts.index.url(group.id) },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const pendingConflicts = conflicts.filter((c) => c.status === 'pending');
    const resolvedConflicts = conflicts.filter((c) => c.status !== 'pending');

    const getFieldLabel = (field: string) => {
        const labels: Record<string, string> = {
            password_hash: t('federation.fields.password'),
            name: t('federation.fields.name'),
            email: t('federation.fields.email'),
            two_factor_secret: t('federation.fields.two_factor'),
            avatar: t('federation.fields.avatar'),
        };
        return labels[field] || field;
    };

    const openResolveDialog = (conflict: ConflictWithSourceTenant) => {
        setResolving(conflict);
        setResolution('source');
        setCustomValue('');
        setNotes('');
    };

    const handleResolve = () => {
        if (!resolving) return;

        const resolvedValue =
            resolution === 'source'
                ? resolving.source_value
                : resolution === 'target'
                  ? resolving.target_value
                  : customValue;

        router.post(
            admin.federation.conflicts.resolve.url({ group: group.id, conflict: resolving.id }),
            {
                resolution,
                resolved_value: resolvedValue,
                notes,
            },
            {
                onSuccess: () => setResolving(null),
            },
        );
    };

    const handleDismiss = (conflictId: string) => {
        if (confirm(t('federation.page.dismiss_confirm'))) {
            router.post(admin.federation.conflicts.dismiss.url({ group: group.id, conflict: conflictId }));
        }
    };

    return (
        <>
            <Head title={`${t('federation.page.conflicts')} - ${group.name}`} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={AlertTriangle}>{t('federation.page.conflicts')}</PageTitle>
                        <PageDescription>
                            {t('federation.conflicts_for', { name: group.name })}
                        </PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    {/* Stats */}
                    <div className="grid gap-4 md:grid-cols-3">
                        <Card>
                            <CardContent className="pt-6">
                                <div className="flex items-center gap-4">
                                    <div className="rounded-full bg-yellow-100 p-3 dark:bg-yellow-900">
                                        <Clock className="h-5 w-5 text-yellow-600 dark:text-yellow-400" />
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground text-sm">
                                            {t('federation.page.pending_conflicts')}
                                        </p>
                                        <p className="text-2xl font-bold">{pendingConflicts.length}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="pt-6">
                                <div className="flex items-center gap-4">
                                    <div className="bg-primary/10 rounded-full p-3">
                                        <Check className="text-primary h-5 w-5" />
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground text-sm">
                                            {t('federation.page.resolved_conflicts')}
                                        </p>
                                        <p className="text-2xl font-bold">{resolvedConflicts.length}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="pt-6">
                                <div className="flex items-center gap-4">
                                    <div className="bg-muted rounded-full p-3">
                                        <GitCompare className="text-muted-foreground h-5 w-5" />
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground text-sm">
                                            {t('federation.page.total_conflicts')}
                                        </p>
                                        <p className="text-2xl font-bold">{conflicts.length}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Pending Conflicts */}
                    {pendingConflicts.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-yellow-600">
                                    <AlertTriangle className="h-5 w-5" />
                                    {t('federation.page.pending_conflicts')}
                                </CardTitle>
                                <CardDescription>{t('federation.page.pending_conflicts_description')}</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>{t('common.user')}</TableHead>
                                            <TableHead>{t('federation.page.field')}</TableHead>
                                            <TableHead>{t('federation.page.source')}</TableHead>
                                            <TableHead>{t('federation.page.values')}</TableHead>
                                            <TableHead>{t('common.created')}</TableHead>
                                            <TableHead>{t('common.actions')}</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {pendingConflicts.map((conflict) => (
                                            <TableRow key={conflict.id}>
                                                <TableCell>
                                                    <div className="flex items-center gap-2">
                                                        <div className="bg-muted flex h-8 w-8 items-center justify-center rounded-full">
                                                            <User className="h-4 w-4" />
                                                        </div>
                                                        <div>
                                                            <p className="font-medium">
                                                                {conflict.federated_user?.name || '-'}
                                                            </p>
                                                            <p className="text-muted-foreground flex items-center gap-1 text-xs">
                                                                <Mail className="h-3 w-3" />
                                                                {conflict.federated_user?.global_email || '-'}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="outline">{getFieldLabel(conflict.field)}</Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <span className="text-sm">{conflict.source_tenant?.name || '-'}</span>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-2">
                                                        <code className="bg-muted max-w-24 truncate rounded px-1 text-xs">
                                                            {conflict.source_value?.substring(0, 20) || '(empty)'}
                                                            {(conflict.source_value?.length || 0) > 20 && '...'}
                                                        </code>
                                                        <span className="text-muted-foreground">→</span>
                                                        <code className="bg-muted max-w-24 truncate rounded px-1 text-xs">
                                                            {conflict.target_value?.substring(0, 20) || '(empty)'}
                                                            {(conflict.target_value?.length || 0) > 20 && '...'}
                                                        </code>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    {new Date(conflict.created_at).toLocaleDateString()}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex gap-1">
                                                        <Button
                                                            variant="default"
                                                            size="sm"
                                                            onClick={() => openResolveDialog(conflict)}
                                                        >
                                                            {t('federation.page.resolve')}
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => handleDismiss(conflict.id)}
                                                        >
                                                            {t('federation.page.dismiss')}
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    )}

                    {/* Empty State */}
                    {pendingConflicts.length === 0 && (
                        <Card className="border-dashed">
                            <CardContent className="flex flex-col items-center justify-center py-12">
                                <Check className="mb-4 h-12 w-12 text-green-500" />
                                <h3 className="mb-2 text-lg font-medium">{t('federation.page.no_pending_conflicts')}</h3>
                                <p className="text-muted-foreground text-center text-sm">
                                    {t('federation.page.no_pending_conflicts_description')}
                                </p>
                            </CardContent>
                        </Card>
                    )}

                    {/* Resolved Conflicts History */}
                    {resolvedConflicts.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('federation.page.resolved_conflicts')}</CardTitle>
                                <CardDescription>{t('federation.page.resolved_conflicts_description')}</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>{t('common.user')}</TableHead>
                                            <TableHead>{t('federation.page.field')}</TableHead>
                                            <TableHead>{t('common.status')}</TableHead>
                                            <TableHead>{t('federation.page.resolved_at')}</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {resolvedConflicts.map((conflict) => (
                                            <TableRow key={conflict.id}>
                                                <TableCell>
                                                    <span className="font-medium">
                                                        {conflict.federated_user?.global_email || '-'}
                                                    </span>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="outline">{getFieldLabel(conflict.field)}</Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <FederationConflictStatusBadge status={conflict.status} />
                                                </TableCell>
                                                <TableCell>
                                                    {conflict.resolved_at
                                                        ? new Date(conflict.resolved_at).toLocaleDateString()
                                                        : '-'}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    )}
                </PageContent>
            </Page>

            {/* Resolve Dialog */}
            <Dialog open={!!resolving} onOpenChange={() => setResolving(null)}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{t('federation.page.resolve_conflict')}</DialogTitle>
                        <DialogDescription>
                            {resolving && (
                                <>
                                    {t('federation.resolve_conflict_description', {
                                        field: getFieldLabel(resolving.field),
                                        user: resolving.federated_user?.global_email ?? '-',
                                    })}
                                </>
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    {resolving && (
                        <div className="space-y-4">
                            <RadioGroup
                                value={resolution}
                                onValueChange={(v: string) => setResolution(v as 'source' | 'target' | 'custom')}
                            >
                                <div className="flex items-start space-x-3 rounded-lg border p-4">
                                    <RadioGroupItem value="source" id="source" className="mt-1" />
                                    <div className="flex-1">
                                        <Label htmlFor="source" className="cursor-pointer font-medium">
                                            {t('federation.page.use_source_value')}
                                        </Label>
                                        <p className="text-muted-foreground text-sm">
                                            {t('federation.from_tenant', { name: resolving.source_tenant?.name })}
                                        </p>
                                        <code className="bg-muted mt-1 block rounded px-2 py-1 text-xs">
                                            {resolving.source_value || '(empty)'}
                                        </code>
                                    </div>
                                </div>

                                <div className="flex items-start space-x-3 rounded-lg border p-4">
                                    <RadioGroupItem value="target" id="target" className="mt-1" />
                                    <div className="flex-1">
                                        <Label htmlFor="target" className="cursor-pointer font-medium">
                                            {t('federation.page.use_target_value')}
                                        </Label>
                                        <p className="text-muted-foreground text-sm">
                                            {t('federation.page.keep_current')}
                                        </p>
                                        <code className="bg-muted mt-1 block rounded px-2 py-1 text-xs">
                                            {resolving.target_value || '(empty)'}
                                        </code>
                                    </div>
                                </div>

                                {resolving.field !== 'password_hash' && resolving.field !== 'two_factor_secret' && (
                                    <div className="flex items-start space-x-3 rounded-lg border p-4">
                                        <RadioGroupItem value="custom" id="custom" className="mt-1" />
                                        <div className="flex-1">
                                            <Label htmlFor="custom" className="cursor-pointer font-medium">
                                                {t('federation.page.use_custom_value')}
                                            </Label>
                                            {resolution === 'custom' && (
                                                <Textarea
                                                    value={customValue}
                                                    onChange={(e) => setCustomValue(e.target.value)}
                                                    placeholder={t('federation.page.enter_custom_value')}
                                                    className="mt-2"
                                                    rows={2}
                                                />
                                            )}
                                        </div>
                                    </div>
                                )}
                            </RadioGroup>

                            <div className="space-y-2">
                                <Label>{t('federation.page.notes')}</Label>
                                <Textarea
                                    value={notes}
                                    onChange={(e) => setNotes(e.target.value)}
                                    placeholder={t('federation.page.notes_placeholder')}
                                    rows={2}
                                />
                            </div>
                        </div>
                    )}

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setResolving(null)}>
                            {t('common.cancel')}
                        </Button>
                        <Button onClick={handleResolve}>{t('federation.page.apply_resolution')}</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

FederationConflicts.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default FederationConflicts;
