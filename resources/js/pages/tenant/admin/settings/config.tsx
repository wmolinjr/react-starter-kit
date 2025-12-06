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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import TenantAdminLayout from '@/layouts/tenant-admin-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ArrowLeft, Building2, Check, Clock, DollarSign, Globe, Mail, Settings2 } from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';
import { Page, PageHeader, PageHeaderContent, PageTitle, PageDescription, PageContent } from '@/components/page';
import { type BreadcrumbItem } from '@/types';

interface Props {
    tenant: { id: string; name: string };
    config: {
        app_name: string | null;
        locale: string;
        timezone: string;
        mail_from_address: string | null;
        mail_from_name: string | null;
        currency: string;
        currency_locale: string;
    };
    availableLocales: string[];
    localeLabels: Record<string, string>;
    availableTimezones: string[];
    availableCurrencies: Record<string, string>;
}

export default function ConfigSettings({
    tenant: tenantData,
    config,
    availableLocales,
    localeLabels,
    availableTimezones,
    availableCurrencies,
}: Props) {
    const { t } = useLaravelReactI18n();
    const [timezoneSearch, setTimezoneSearch] = useState('');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('tenant.settings.title'), href: admin.settings.index.url() },
        { title: t('tenant.config.title'), href: admin.settings.config.url() },
    ];

    const { data, setData, post, processing, recentlySuccessful, errors } = useForm({
        app_name: config.app_name ?? '',
        locale: config.locale,
        timezone: config.timezone,
        mail_from_address: config.mail_from_address ?? '',
        mail_from_name: config.mail_from_name ?? '',
        currency: config.currency,
        currency_locale: config.currency_locale,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post(admin.settings.config.url());
    };

    // Filter timezones based on search
    const filteredTimezones = useMemo(() => {
        if (!timezoneSearch) {
            // Show common timezones first when no search
            const common = [
                'UTC',
                'America/New_York',
                'America/Chicago',
                'America/Denver',
                'America/Los_Angeles',
                'America/Sao_Paulo',
                'Europe/London',
                'Europe/Paris',
                'Europe/Berlin',
                'Asia/Tokyo',
                'Asia/Shanghai',
                'Australia/Sydney',
            ];
            const commonSet = new Set(common);
            return [
                ...common.filter(tz => availableTimezones.includes(tz)),
                ...availableTimezones.filter(tz => !commonSet.has(tz)),
            ];
        }
        const search = timezoneSearch.toLowerCase();
        return availableTimezones.filter(tz =>
            tz.toLowerCase().includes(search)
        );
    }, [availableTimezones, timezoneSearch]);

    return (
        <TenantAdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('tenant.config.title')} />

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
                                <PageTitle icon={Settings2}>{t('tenant.config.title')}</PageTitle>
                                <PageDescription>
                                    {t('tenant.config.description', { name: tenantData.name })}
                                </PageDescription>
                            </div>
                        </div>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Branding Section */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Building2 className="h-5 w-5" />
                                    {t('tenant.config.branding')}
                                </CardTitle>
                                <CardDescription>
                                    {t('tenant.config.branding_description')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2 max-w-md">
                                    <Label htmlFor="app_name">
                                        {t('tenant.config.app_name_label')}
                                    </Label>
                                    <Input
                                        id="app_name"
                                        type="text"
                                        placeholder={t('tenant.config.app_name_placeholder')}
                                        value={data.app_name}
                                        onChange={(e) => setData('app_name', e.target.value)}
                                    />
                                    {errors.app_name && (
                                        <p className="text-sm text-destructive">{errors.app_name}</p>
                                    )}
                                    <p className="text-sm text-muted-foreground">
                                        {t('tenant.config.app_name_note')}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Localization Section */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Globe className="h-5 w-5" />
                                    {t('tenant.config.localization')}
                                </CardTitle>
                                <CardDescription>
                                    {t('tenant.config.localization_description')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-6 md:grid-cols-2">
                                {/* Language */}
                                <div className="space-y-2">
                                    <Label htmlFor="locale">{t('tenant.config.language')}</Label>
                                    <Select
                                        value={data.locale}
                                        onValueChange={(value) => setData('locale', value)}
                                    >
                                        <SelectTrigger id="locale">
                                            <SelectValue placeholder={t('tenant.config.select_language')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {availableLocales.map((locale) => (
                                                <SelectItem key={locale} value={locale}>
                                                    <div className="flex items-center gap-2">
                                                        <span>{localeLabels[locale] ?? locale}</span>
                                                        {locale === config.locale && (
                                                            <Check className="h-4 w-4 text-green-500" />
                                                        )}
                                                    </div>
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.locale && (
                                        <p className="text-sm text-destructive">{errors.locale}</p>
                                    )}
                                    <p className="text-sm text-muted-foreground">
                                        {t('tenant.config.language_note')}
                                    </p>
                                </div>

                                {/* Timezone */}
                                <div className="space-y-2">
                                    <Label htmlFor="timezone" className="flex items-center gap-2">
                                        <Clock className="h-4 w-4" />
                                        {t('tenant.config.timezone')}
                                    </Label>
                                    <Select
                                        value={data.timezone}
                                        onValueChange={(value) => setData('timezone', value)}
                                    >
                                        <SelectTrigger id="timezone">
                                            <SelectValue placeholder={t('tenant.config.select_timezone')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <div className="px-2 pb-2">
                                                <Input
                                                    placeholder={t('tenant.config.search_timezone')}
                                                    value={timezoneSearch}
                                                    onChange={(e) => setTimezoneSearch(e.target.value)}
                                                    className="h-8"
                                                />
                                            </div>
                                            {filteredTimezones.slice(0, 50).map((tz) => (
                                                <SelectItem key={tz} value={tz}>
                                                    {tz.replace(/_/g, ' ')}
                                                </SelectItem>
                                            ))}
                                            {filteredTimezones.length > 50 && (
                                                <p className="px-2 py-1 text-xs text-muted-foreground">
                                                    {t('tenant.config.more_timezones', { count: filteredTimezones.length - 50 })}
                                                </p>
                                            )}
                                        </SelectContent>
                                    </Select>
                                    {errors.timezone && (
                                        <p className="text-sm text-destructive">{errors.timezone}</p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Email Section */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Mail className="h-5 w-5" />
                                    {t('tenant.config.email_settings')}
                                </CardTitle>
                                <CardDescription>
                                    {t('tenant.config.email_settings_description')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-6 md:grid-cols-2">
                                {/* From Address */}
                                <div className="space-y-2">
                                    <Label htmlFor="mail_from_address">
                                        {t('tenant.config.from_address')}
                                    </Label>
                                    <Input
                                        id="mail_from_address"
                                        type="email"
                                        placeholder="noreply@example.com"
                                        value={data.mail_from_address}
                                        onChange={(e) => setData('mail_from_address', e.target.value)}
                                    />
                                    {errors.mail_from_address && (
                                        <p className="text-sm text-destructive">{errors.mail_from_address}</p>
                                    )}
                                    <p className="text-sm text-muted-foreground">
                                        {t('tenant.config.from_address_note')}
                                    </p>
                                </div>

                                {/* From Name */}
                                <div className="space-y-2">
                                    <Label htmlFor="mail_from_name">
                                        {t('tenant.config.from_name')}
                                    </Label>
                                    <Input
                                        id="mail_from_name"
                                        type="text"
                                        placeholder={tenantData.name}
                                        value={data.mail_from_name}
                                        onChange={(e) => setData('mail_from_name', e.target.value)}
                                    />
                                    {errors.mail_from_name && (
                                        <p className="text-sm text-destructive">{errors.mail_from_name}</p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Currency Section */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <DollarSign className="h-5 w-5" />
                                    {t('tenant.config.currency_settings')}
                                </CardTitle>
                                <CardDescription>
                                    {t('tenant.config.currency_settings_description')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-6 md:grid-cols-2">
                                {/* Currency */}
                                <div className="space-y-2">
                                    <Label htmlFor="currency">
                                        {t('tenant.config.default_currency')}
                                    </Label>
                                    <Select
                                        value={data.currency}
                                        onValueChange={(value) => setData('currency', value)}
                                    >
                                        <SelectTrigger id="currency">
                                            <SelectValue placeholder={t('tenant.config.select_currency')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {Object.entries(availableCurrencies).map(([code, name]) => (
                                                <SelectItem key={code} value={code}>
                                                    {name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.currency && (
                                        <p className="text-sm text-destructive">{errors.currency}</p>
                                    )}
                                </div>

                                {/* Currency Locale (hidden for now, auto-synced with locale) */}
                                <input type="hidden" name="currency_locale" value={data.locale} />
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
