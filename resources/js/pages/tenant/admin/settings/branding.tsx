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
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/tenant/admin-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Palette, Upload } from 'lucide-react';
import { FormEvent, useRef } from 'react';
import { Page, PageHeader, PageHeaderContent, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { type BreadcrumbItem, type TenantSummaryResource } from '@/types';

import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';

interface BrandingData {
    logo_url?: string;
    primary_color?: string;
    secondary_color?: string;
    custom_css?: string;
}

interface Props {
    tenant: TenantSummaryResource;
    branding: BrandingData;
}

function BrandingSettingsPage({ tenant: tenantData, branding }: Props) {
    const { t } = useLaravelReactI18n();
    const fileInputRef = useRef<HTMLInputElement>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('dashboard.page.title'), href: admin.dashboard.url() },
        { title: t('settings.title'), href: admin.settings.index.url() },
        { title: 'Branding', href: admin.settings.branding.url() },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const { data, setData, post, processing, errors } = useForm({
        logo: null as File | null,
        primary_color: branding.primary_color || '#000000',
        secondary_color: branding.secondary_color || '#666666',
        custom_css: branding.custom_css || '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post(admin.settings.branding.url());
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            setData('logo', file);
        }
    };

    return (
        <>
            <Head title="Branding" />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={Palette}>{t('settings.branding')}</PageTitle>
                        <PageDescription>
                            {t('settings.branding_page_description', { name: tenantData.name })}
                        </PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Logo */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Logo</CardTitle>
                            <CardDescription>
                                {t('settings.logo_description')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center gap-6">
                                {branding.logo_url && (
                                    <img
                                        src={branding.logo_url}
                                        alt={t('settings.current_logo')}
                                        className="h-16 w-16 object-contain rounded border"
                                    />
                                )}
                                <div className="flex-1">
                                    <Input
                                        ref={fileInputRef}
                                        type="file"
                                        accept="image/*"
                                        onChange={handleFileChange}
                                        className="hidden"
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() =>
                                            fileInputRef.current?.click()
                                        }
                                    >
                                        <Upload className="mr-2 h-4 w-4" />
                                        {data.logo
                                            ? data.logo.name
                                            : t('settings.choose_file')}
                                    </Button>
                                    {errors.logo && (
                                        <p className="text-sm text-red-500 mt-1">
                                            {errors.logo}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Colors */}
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('settings.colors')}</CardTitle>
                            <CardDescription>
                                {t('settings.colors_description')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="primary_color">
                                        {t('settings.primary_color')}
                                    </Label>
                                    <div className="flex gap-2">
                                        <Input
                                            type="color"
                                            id="primary_color"
                                            value={data.primary_color}
                                            onChange={(e) =>
                                                setData(
                                                    'primary_color',
                                                    e.target.value,
                                                )
                                            }
                                            className="w-16 h-10 p-1"
                                        />
                                        <Input
                                            value={data.primary_color}
                                            onChange={(e) =>
                                                setData(
                                                    'primary_color',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="#000000"
                                            className="font-mono"
                                        />
                                    </div>
                                    {errors.primary_color && (
                                        <p className="text-sm text-red-500">
                                            {errors.primary_color}
                                        </p>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="secondary_color">
                                        {t('settings.secondary_color')}
                                    </Label>
                                    <div className="flex gap-2">
                                        <Input
                                            type="color"
                                            id="secondary_color"
                                            value={data.secondary_color}
                                            onChange={(e) =>
                                                setData(
                                                    'secondary_color',
                                                    e.target.value,
                                                )
                                            }
                                            className="w-16 h-10 p-1"
                                        />
                                        <Input
                                            value={data.secondary_color}
                                            onChange={(e) =>
                                                setData(
                                                    'secondary_color',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="#666666"
                                            className="font-mono"
                                        />
                                    </div>
                                    {errors.secondary_color && (
                                        <p className="text-sm text-red-500">
                                            {errors.secondary_color}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Custom CSS */}
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('settings.custom_css')}</CardTitle>
                            <CardDescription>
                                {t('settings.custom_css_description')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Textarea
                                value={data.custom_css}
                                onChange={(e) =>
                                    setData('custom_css', e.target.value)
                                }
                                placeholder=".my-class { color: red; }"
                                rows={8}
                                className="font-mono text-sm"
                            />
                            {errors.custom_css && (
                                <p className="text-sm text-red-500 mt-1">
                                    {errors.custom_css}
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    {/* Actions */}
                    <div className="flex justify-end gap-4">
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
        </>
    );
}

BrandingSettingsPage.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default BrandingSettingsPage;
