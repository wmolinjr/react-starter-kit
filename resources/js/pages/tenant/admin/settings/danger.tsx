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
import AdminLayout from '@/layouts/tenant/admin-layout';
import { Head, useForm } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { AlertTriangle, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Page, PageHeader, PageHeaderContent, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { type BreadcrumbItem } from '@/types';

import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';

interface Props {
    tenant: { id: string; name: string; slug: string };
}

function DangerSettings({ tenant: tenantData }: Props) {
    const { t } = useLaravelReactI18n();
    const [confirmText, setConfirmText] = useState('');
    const isConfirmed = confirmText === tenantData.slug;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('dashboard.page.title'), href: admin.dashboard.url() },
        { title: t('settings.title'), href: admin.settings.index.url() },
        { title: t('settings.danger_zone'), href: admin.settings.danger.url() },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const { delete: destroy, processing } = useForm();

    const handleDelete = () => {
        destroy('/tenant-settings/delete', {
            onSuccess: () => {
                window.location.href = '/';
            },
        });
    };

    return (
        <>
            <Head title={t('settings.danger_zone')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={AlertTriangle} className="text-red-600 dark:text-red-400">
                            {t('settings.danger_zone')}
                        </PageTitle>
                        <PageDescription>
                            {t('settings.danger_zone_page_description', { name: tenantData.name })}
                        </PageDescription>
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
                                    {t('settings.attention')}
                                </p>
                                <p className="text-sm text-red-600/80 dark:text-red-400/80">
                                    {t('settings.danger_warning')}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Delete Tenant */}
                <Card className="border-red-200 dark:border-red-900">
                    <CardHeader>
                        <CardTitle className="text-red-600 dark:text-red-400">
                            {t('settings.delete_organization')}
                        </CardTitle>
                        <CardDescription>
                            {t('settings.delete_description', { name: tenantData.name })}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <ul className="list-disc list-inside text-sm text-muted-foreground space-y-1">
                            <li>{t('settings.delete_item_projects')}</li>
                            <li>{t('settings.delete_item_members')}</li>
                            <li>{t('settings.delete_item_settings')}</li>
                            <li>{t('settings.delete_item_history')}</li>
                            <li>{t('settings.delete_item_tokens')}</li>
                        </ul>

                        <div className="space-y-4 pt-4 border-t">
                            <div className="space-y-2">
                                <Label htmlFor="confirm">
                                    {t('settings.confirm_type')}{' '}
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
                                        {t('settings.delete_permanently')}
                                    </Button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>
                                            {t('settings.are_you_sure')}
                                        </AlertDialogTitle>
                                        <AlertDialogDescription>
                                            {t('settings.delete_final_warning', { name: tenantData.name })}
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
                                                ? t('settings.deleting')
                                                : t('settings.yes_delete_all')}
                                        </AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                        </div>
                    </CardContent>
                </Card>
                </PageContent>
            </Page>
        </>
    );
}

DangerSettings.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default DangerSettings;
