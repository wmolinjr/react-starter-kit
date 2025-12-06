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
import TenantAdminLayout from '@/layouts/tenant-admin-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { AlertTriangle, ArrowLeft, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Page, PageHeader, PageHeaderContent, PageTitle, PageDescription, PageContent } from '@/components/page';
import { type BreadcrumbItem } from '@/types';

interface Props {
    tenant: { id: string; name: string; slug: string };
}

export default function DangerSettings({ tenant: tenantData }: Props) {
    const { t } = useLaravelReactI18n();
    const [confirmText, setConfirmText] = useState('');
    const isConfirmed = confirmText === tenantData.slug;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('tenant.settings.title'), href: admin.settings.index.url() },
        { title: t('tenant.settings.danger_zone'), href: admin.settings.danger.url() },
    ];

    const { delete: destroy, processing } = useForm();

    const handleDelete = () => {
        destroy('/tenant-settings/delete', {
            onSuccess: () => {
                window.location.href = '/';
            },
        });
    };

    return (
        <TenantAdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('tenant.settings.danger_zone')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <div className="flex items-center gap-4">
                            <Button variant="ghost" size="icon" asChild>
                                <Link href={admin.settings.index.url()}>
                                    <ArrowLeft className="h-4 w-4" />
                                </Link>
                            </Button>
                            <div>
                                <PageTitle icon={AlertTriangle} className="text-red-600 dark:text-red-400">
                                    {t('tenant.settings.danger_zone')}
                                </PageTitle>
                                <PageDescription>
                                    {t('tenant.settings.danger_zone_page_description', { name: tenantData.name })}
                                </PageDescription>
                            </div>
                        </div>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>

                {/* Warning */}
                <Card className="border-red-500 bg-red-50 dark:bg-red-950">
                    <CardContent>
                        <div className="flex gap-4 content-center items-center">
                            <AlertTriangle className="h-6 w-6 text-red-600 dark:text-red-400 flex-shrink-0" />
                            <div>
                                <p className="font-medium text-red-600 dark:text-red-400">
                                    {t('tenant.settings.attention')}
                                </p>
                                <p className="text-sm text-red-600/80 dark:text-red-400/80">
                                    {t('tenant.settings.danger_warning')}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Delete Tenant */}
                <Card className="border-red-200 dark:border-red-900">
                    <CardHeader>
                        <CardTitle className="text-red-600 dark:text-red-400">
                            {t('tenant.settings.delete_organization')}
                        </CardTitle>
                        <CardDescription>
                            {t('tenant.settings.delete_description', { name: tenantData.name })}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <ul className="list-disc list-inside text-sm text-muted-foreground space-y-1">
                            <li>{t('tenant.settings.delete_item_projects')}</li>
                            <li>{t('tenant.settings.delete_item_members')}</li>
                            <li>{t('tenant.settings.delete_item_settings')}</li>
                            <li>{t('tenant.settings.delete_item_history')}</li>
                            <li>{t('tenant.settings.delete_item_tokens')}</li>
                        </ul>

                        <div className="space-y-4 pt-4 border-t">
                            <div className="space-y-2">
                                <Label htmlFor="confirm">
                                    {t('tenant.settings.confirm_type')}{' '}
                                    <code className="bg-muted px-1 rounded font-bold">
                                        {tenantData.slug}
                                    </code>
                                </Label>
                                <Input
                                    id="confirm"
                                    value={confirmText}
                                    onChange={(e) =>
                                        setConfirmText(e.target.value)
                                    }
                                    placeholder={tenantData.slug}
                                    className="max-w-sm"
                                />
                            </div>

                            <AlertDialog>
                                <AlertDialogTrigger asChild>
                                    <Button
                                        variant="destructive"
                                        disabled={!isConfirmed || processing}
                                    >
                                        <Trash2 className="mr-2 h-4 w-4" />
                                        {t('tenant.settings.delete_permanently')}
                                    </Button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>
                                            {t('tenant.settings.are_you_sure')}
                                        </AlertDialogTitle>
                                        <AlertDialogDescription>
                                            {t('tenant.settings.delete_final_warning', { name: tenantData.name })}
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>
                                            {t('common.cancel')}
                                        </AlertDialogCancel>
                                        <AlertDialogAction
                                            onClick={handleDelete}
                                            className="bg-red-600 hover:bg-red-700"
                                        >
                                            {processing
                                                ? t('tenant.settings.deleting')
                                                : t('tenant.settings.yes_delete_all')}
                                        </AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                        </div>
                    </CardContent>
                </Card>
                </PageContent>
            </Page>
        </TenantAdminLayout>
    );
}
