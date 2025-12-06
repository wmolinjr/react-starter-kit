import admin from '@/routes/tenant/admin';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import TenantAdminLayout from '@/layouts/tenant-admin-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ArrowLeft, Check, Languages } from 'lucide-react';
import { FormEvent } from 'react';
import { Page, PageHeader, PageHeaderContent, PageTitle, PageDescription, PageContent } from '@/components/page';
import { type BreadcrumbItem } from '@/types';

interface Props {
    tenant: { id: string; name: string };
    currentLanguage: string;
    availableLanguages: string[];
    languageLabels: Record<string, string>;
}

export default function LanguageSettings({
    tenant: tenantData,
    currentLanguage,
    availableLanguages,
    languageLabels,
}: Props) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('tenant.settings.title'), href: admin.settings.index.url() },
        { title: t('tenant.settings.language'), href: admin.settings.language.url() },
    ];

    const { data, setData, post, processing, recentlySuccessful } = useForm({
        language: currentLanguage,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post(admin.settings.language.url());
    };

    return (
        <TenantAdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('tenant.settings.language')} />

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
                                <PageTitle icon={Languages}>{t('tenant.settings.language')}</PageTitle>
                                <PageDescription>
                                    {t('tenant.settings.language_page_description', { name: tenantData.name })}
                                </PageDescription>
                            </div>
                        </div>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Default Language */}
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('tenant.settings.default_language')}</CardTitle>
                                <CardDescription>
                                    {t('tenant.settings.default_language_description')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="language">
                                        {t('tenant.settings.select_language')}
                                    </Label>
                                    <Select
                                        value={data.language}
                                        onValueChange={(value) => setData('language', value)}
                                    >
                                        <SelectTrigger className="w-full max-w-xs">
                                            <SelectValue placeholder={t('tenant.settings.select_language')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {availableLanguages.map((locale) => (
                                                <SelectItem key={locale} value={locale}>
                                                    <div className="flex items-center gap-2">
                                                        <span>{languageLabels[locale] || locale}</span>
                                                        {locale === currentLanguage && (
                                                            <Check className="h-4 w-4 text-green-500" />
                                                        )}
                                                    </div>
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <p className="text-sm text-muted-foreground">
                                        {t('tenant.settings.language_note')}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Language Preview */}
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('tenant.settings.language_preview')}</CardTitle>
                                <CardDescription>
                                    {t('tenant.settings.language_preview_description')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="rounded-lg border p-4 bg-muted/30">
                                    <div className="grid gap-3 text-sm">
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">{t('tenant.settings.current_selection')}:</span>
                                            <span className="font-medium">{languageLabels[data.language] || data.language}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">{t('tenant.settings.locale_code')}:</span>
                                            <span className="font-mono">{data.language}</span>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Actions */}
                        <div className="flex items-center justify-end gap-4">
                            {recentlySuccessful && (
                                <span className="text-sm text-green-600 flex items-center gap-1">
                                    <Check className="h-4 w-4" />
                                    {t('common.saved')}
                                </span>
                            )}
                            <Button variant="outline" asChild>
                                <Link href={admin.settings.index.url()}>
                                    {t('common.cancel')}
                                </Link>
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing ? t('common.saving') : t('common.save')}
                            </Button>
                        </div>
                    </form>
                </PageContent>
            </Page>
        </TenantAdminLayout>
    );
}
