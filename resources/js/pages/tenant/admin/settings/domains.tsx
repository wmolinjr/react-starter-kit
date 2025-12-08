import admin from '@/routes/tenant/admin';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import AdminLayout from '@/layouts/tenant/admin-layout';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Globe, Plus, Trash2 } from 'lucide-react';
import { FormEvent } from 'react';
import { Page, PageHeader, PageHeaderContent, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { type BreadcrumbItem } from '@/types';

import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';

interface Domain {
    id: string;
    domain: string;
    is_primary: boolean;
    created_at: string;
}

interface Props {
    tenant: { id: string; name: string };
    domains: Domain[];
    hasCustomDomainFeature: boolean;
}

function DomainsSettings({
    tenant: tenantData,
    domains,
    hasCustomDomainFeature,
}: Props) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('tenant.settings.title'), href: admin.settings.index.url() },
        { title: t('tenant.settings.domains'), href: admin.settings.domains.url() },
    ];

    const { data, setData, post, processing, errors, reset } = useForm({
        domain: '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post(admin.settings.domains.url(), {
            onSuccess: () => reset(),
        });
    };

    const handleDelete = (domainId: string) => {
        router.delete(`/tenant-settings/domains/${domainId}`);
    };

    return (
        <>
            <Head title={t('tenant.settings.domains')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={Globe}>{t('tenant.settings.domains')}</PageTitle>
                        <PageDescription>
                            {t('tenant.settings.domains_page_description', { name: tenantData.name })}
                        </PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>

                {/* Current Domains */}
                <Card>
                    <CardHeader>
                        <CardTitle>{t('tenant.settings.configured_domains')}</CardTitle>
                        <CardDescription>
                            {t('tenant.settings.configured_domains_description')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('tenant.settings.domain')}</TableHead>
                                    <TableHead>{t('tenant.settings.type')}</TableHead>
                                    <TableHead className="text-right">
                                        {t('common.actions')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {domains.map((domain) => (
                                    <TableRow key={domain.id}>
                                        <TableCell className="font-mono">
                                            {domain.domain}
                                        </TableCell>
                                        <TableCell>
                                            {domain.is_primary ? (
                                                <Badge>{t('tenant.settings.primary')}</Badge>
                                            ) : (
                                                <Badge variant="secondary">
                                                    {t('tenant.settings.custom')}
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {!domain.is_primary && (
                                                <AlertDialog>
                                                    <AlertDialogTrigger asChild>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </AlertDialogTrigger>
                                                    <AlertDialogContent>
                                                        <AlertDialogHeader>
                                                            <AlertDialogTitle>
                                                                {t('tenant.settings.remove_domain_title')}
                                                            </AlertDialogTitle>
                                                            <AlertDialogDescription>
                                                                {t('tenant.settings.remove_domain_description', { domain: domain.domain })}
                                                            </AlertDialogDescription>
                                                        </AlertDialogHeader>
                                                        <AlertDialogFooter>
                                                            <AlertDialogCancel>
                                                                {t('common.cancel')}
                                                            </AlertDialogCancel>
                                                            <AlertDialogAction
                                                                onClick={() =>
                                                                    handleDelete(
                                                                        domain.id,
                                                                    )
                                                                }
                                                            >
                                                                {t('common.remove')}
                                                            </AlertDialogAction>
                                                        </AlertDialogFooter>
                                                    </AlertDialogContent>
                                                </AlertDialog>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                {/* Add Domain */}
                {hasCustomDomainFeature ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('tenant.settings.add_domain')}</CardTitle>
                            <CardDescription>
                                {t('tenant.settings.add_domain_description')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={handleSubmit}
                                className="flex gap-4"
                            >
                                <div className="flex-1 space-y-2">
                                    <Label htmlFor="domain" className="sr-only">
                                        {t('tenant.settings.domain')}
                                    </Label>
                                    <Input
                                        id="domain"
                                        value={data.domain}
                                        onChange={(e) =>
                                            setData('domain', e.target.value)
                                        }
                                        placeholder="exemplo.com.br"
                                        className={
                                            errors.domain ? 'border-red-500' : ''
                                        }
                                    />
                                    {errors.domain && (
                                        <p className="text-sm text-red-500">
                                            {errors.domain}
                                        </p>
                                    )}
                                </div>
                                <Button type="submit" disabled={processing}>
                                    <Plus className="mr-2 h-4 w-4" />
                                    {t('common.add')}
                                </Button>
                            </form>

                            <div className="mt-4 p-4 bg-muted rounded-lg">
                                <p className="text-sm font-medium mb-2">
                                    {t('tenant.settings.dns_instructions')}:
                                </p>
                                <ol className="text-sm text-muted-foreground space-y-1 list-decimal list-inside">
                                    <li>
                                        {t('tenant.settings.dns_step_1')}
                                    </li>
                                    <li>
                                        {t('tenant.settings.dns_step_2')}{' '}
                                        <code className="bg-background px-1 rounded">
                                            app.setor3.app
                                        </code>
                                    </li>
                                    <li>
                                        {t('tenant.settings.dns_step_3')}
                                    </li>
                                </ol>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardContent className="py-8 text-center">
                            <Globe className="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                            <h3 className="text-lg font-semibold mb-2">
                                {t('tenant.settings.custom_domains')}
                            </h3>
                            <p className="text-muted-foreground mb-4">
                                {t('tenant.settings.upgrade_for_domains')}
                            </p>
                            <Button asChild>
                                <Link href="/billing">{t('tenant.settings.view_plans')}</Link>
                            </Button>
                        </CardContent>
                    </Card>
                )}
                </PageContent>
            </Page>
        </>
    );
}

DomainsSettings.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default DomainsSettings;
