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
import { Textarea } from '@/components/ui/textarea';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import AdminLayout from '@/layouts/central/admin-layout';
import admin from '@/routes/central/admin';
import {
    type BreadcrumbItem,
    type FederationGroupResource,
    type FederationConflictResource,
} from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    AlertTriangle,
    ArrowLeft,
    Building2,
    Calendar,
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

interface ConflictWithDetails extends FederationConflictResource {
    source_value?: string;
    target_value?: string;
    source_tenant?: { id: string; name: string };
}

interface Props {
    group: FederationGroupResource;
    conflict: ConflictWithDetails;
}

function FederationConflict({ group, conflict }: Props) {
    const { t } = useLaravelReactI18n();
    const [showResolveDialog, setShowResolveDialog] = useState(false);
    const [resolution, setResolution] = useState<'source' | 'target' | 'custom'>('source');
    const [customValue, setCustomValue] = useState('');
    const [notes, setNotes] = useState('');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('dashboard.page.title'), href: admin.dashboard.url() },
        { title: t('federation.page.title'), href: admin.federation.index.url() },
        { title: group.name, href: admin.federation.show.url(group.id) },
        { title: t('federation.page.conflicts'), href: admin.federation.conflicts.index.url(group.id) },
        { title: conflict.field, href: admin.federation.conflicts.show.url({ group: group.id, conflict: conflict.id }) },
    ];

    useSetBreadcrumbs(breadcrumbs);

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

    const handleResolve = () => {
        const resolvedValue =
            resolution === 'source'
                ? conflict.source_value
                : resolution === 'target'
                  ? conflict.target_value
                  : customValue;

        router.post(
            admin.federation.conflicts.resolve.url({ group: group.id, conflict: conflict.id }),
            {
                resolution: resolution === 'source' ? 'master_value' : 'manual',
                resolved_value: resolvedValue,
                notes,
            },
            {
                onSuccess: () => setShowResolveDialog(false),
            },
        );
    };

    const handleDismiss = () => {
        if (confirm(t('federation.page.dismiss_confirm'))) {
            router.post(admin.federation.conflicts.dismiss.url({ group: group.id, conflict: conflict.id }));
        }
    };

    return (
        <>
            <Head title={`${t('federation.page.conflict_detail')} - ${getFieldLabel(conflict.field)}`} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <div className="flex items-center gap-4">
                            <Button variant="ghost" size="icon" asChild>
                                <Link href={admin.federation.conflicts.index.url(group.id)}>
                                    <ArrowLeft className="h-4 w-4" />
                                </Link>
                            </Button>
                            <div>
                                <PageTitle icon={AlertTriangle}>{t('federation.page.conflict_detail')}</PageTitle>
                                <PageDescription>
                                    {t('federation.conflict_for_field', { field: getFieldLabel(conflict.field) })}
                                </PageDescription>
                            </div>
                        </div>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    <div className="grid gap-6 lg:grid-cols-2">
                        {/* Conflict Info */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <AlertTriangle className="h-5 w-5 text-yellow-600" />
                                    {t('federation.page.conflict_info')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">{t('common.status')}</span>
                                    <FederationConflictStatusBadge status={conflict.status} />
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">{t('federation.page.field')}</span>
                                    <Badge variant="outline">{getFieldLabel(conflict.field)}</Badge>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">{t('common.created')}</span>
                                    <span className="flex items-center gap-1 text-sm">
                                        <Calendar className="h-3 w-3" />
                                        {new Date(conflict.created_at).toLocaleString()}
                                    </span>
                                </div>
                                {conflict.resolved_at && (
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">{t('federation.page.resolved_at')}</span>
                                        <span className="text-sm">{new Date(conflict.resolved_at).toLocaleString()}</span>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* User Info */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <User className="h-5 w-5" />
                                    {t('federation.page.affected_user')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center gap-3">
                                    <div className="bg-muted flex h-10 w-10 items-center justify-center rounded-full">
                                        <User className="h-5 w-5" />
                                    </div>
                                    <div>
                                        <p className="font-medium">{conflict.federated_user?.name || '-'}</p>
                                        <p className="text-muted-foreground flex items-center gap-1 text-sm">
                                            <Mail className="h-3 w-3" />
                                            {conflict.federated_user?.global_email || '-'}
                                        </p>
                                    </div>
                                </div>
                                {conflict.source_tenant && (
                                    <div className="flex items-center justify-between pt-2">
                                        <span className="text-muted-foreground">{t('federation.page.source_tenant')}</span>
                                        <span className="flex items-center gap-1 text-sm">
                                            <Building2 className="h-3 w-3" />
                                            {conflict.source_tenant.name}
                                        </span>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Conflicting Values */}
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('federation.page.conflicting_values')}</CardTitle>
                            <CardDescription>{t('federation.page.conflicting_values_description')}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-4 md:grid-cols-2">
                                {conflict.source_value !== undefined && (
                                    <div className="rounded-lg border p-4">
                                        <p className="text-muted-foreground mb-2 text-sm font-medium">
                                            {t('federation.page.source_value')}
                                        </p>
                                        <code className="bg-muted block rounded p-2 text-sm">
                                            {conflict.source_value || '(empty)'}
                                        </code>
                                    </div>
                                )}
                                {conflict.target_value !== undefined && (
                                    <div className="rounded-lg border p-4">
                                        <p className="text-muted-foreground mb-2 text-sm font-medium">
                                            {t('federation.page.target_value')}
                                        </p>
                                        <code className="bg-muted block rounded p-2 text-sm">
                                            {conflict.target_value || '(empty)'}
                                        </code>
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Actions */}
                    {conflict.status === 'pending' && (
                        <div className="flex gap-2">
                            <Button onClick={() => setShowResolveDialog(true)}>
                                {t('federation.page.resolve')}
                            </Button>
                            <Button variant="outline" onClick={handleDismiss}>
                                {t('federation.page.dismiss')}
                            </Button>
                        </div>
                    )}
                </PageContent>
            </Page>

            {/* Resolve Dialog */}
            <Dialog open={showResolveDialog} onOpenChange={setShowResolveDialog}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{t('federation.page.resolve_conflict')}</DialogTitle>
                        <DialogDescription>
                            {t('federation.resolve_conflict_description', {
                                field: getFieldLabel(conflict.field),
                                user: conflict.federated_user?.global_email ?? '-',
                            })}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        <RadioGroup
                            value={resolution}
                            onValueChange={(v: string) => setResolution(v as 'source' | 'target' | 'custom')}
                        >
                            {conflict.source_value !== undefined && (
                                <div className="flex items-start space-x-3 rounded-lg border p-4">
                                    <RadioGroupItem value="source" id="source" className="mt-1" />
                                    <div className="flex-1">
                                        <Label htmlFor="source" className="cursor-pointer font-medium">
                                            {t('federation.page.use_source_value')}
                                        </Label>
                                        <code className="bg-muted mt-1 block rounded px-2 py-1 text-xs">
                                            {conflict.source_value || '(empty)'}
                                        </code>
                                    </div>
                                </div>
                            )}

                            {conflict.target_value !== undefined && (
                                <div className="flex items-start space-x-3 rounded-lg border p-4">
                                    <RadioGroupItem value="target" id="target" className="mt-1" />
                                    <div className="flex-1">
                                        <Label htmlFor="target" className="cursor-pointer font-medium">
                                            {t('federation.page.use_target_value')}
                                        </Label>
                                        <code className="bg-muted mt-1 block rounded px-2 py-1 text-xs">
                                            {conflict.target_value || '(empty)'}
                                        </code>
                                    </div>
                                </div>
                            )}

                            {conflict.field !== 'password_hash' && conflict.field !== 'two_factor_secret' && (
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

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowResolveDialog(false)}>
                            {t('common.cancel')}
                        </Button>
                        <Button onClick={handleResolve}>{t('federation.page.apply_resolution')}</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

FederationConflict.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default FederationConflict;
