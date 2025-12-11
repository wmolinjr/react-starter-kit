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
import AdminLayout from '@/layouts/tenant/admin-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Building2, Check, Globe, Mail, Settings2 } from 'lucide-react';
import { FormEvent, useEffect, useMemo, useState } from 'react';
import { Page, PageHeader, PageHeaderContent, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { type BreadcrumbItem } from '@/types';
import { format } from 'date-fns';
import { TZDate } from '@date-fns/tz';
import { getDateFnsLocale } from '@/lib/date-locale';

import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';

interface Props {
    tenantData: { id: string; name: string };
    config: {
        app_name: string | null;
        locale: string;
        timezone: string;
        date_format: string;
        time_format: string;
        week_starts_on: number;
        mail_from_address: string | null;
        mail_from_name: string | null;
        currency: string;
        currency_locale: string;
    };
    availableLocales: string[];
    localeLabels: Record<string, string>;
    availableTimezones: string[];
    availableCurrencies: Record<string, string>;
    availableDateFormats: Record<string, string>;
    availableTimeFormats: Record<string, string>;
    availableWeekdays: Record<number, string>;
}

function ConfigSettings({
    tenantData,
    config,
    availableLocales,
    localeLabels,
    availableTimezones,
    availableCurrencies,
    availableDateFormats,
    availableTimeFormats,
    availableWeekdays,
}: Props) {
    const { t } = useLaravelReactI18n();
    const [timezoneSearch, setTimezoneSearch] = useState('');
    const [currentTime, setCurrentTime] = useState(new Date());

    // Update current time every second for live preview
    useEffect(() => {
        const interval = setInterval(() => {
            setCurrentTime(new Date());
        }, 1000);
        return () => clearInterval(interval);
    }, []);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('dashboard.page.title'), href: admin.dashboard.url() },
        { title: t('settings.title'), href: admin.settings.index.url() },
        { title: t('config.page.title'), href: admin.settings.config.url() },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const { data, setData, post, processing, recentlySuccessful, errors } = useForm({
        app_name: config.app_name ?? '',
        locale: config.locale,
        timezone: config.timezone,
        date_format: config.date_format,
        time_format: config.time_format,
        week_starts_on: config.week_starts_on,
        mail_from_address: config.mail_from_address ?? '',
        mail_from_name: config.mail_from_name ?? '',
        currency: config.currency,
        currency_locale: config.currency_locale,
    });

    // Get date-fns locale based on selected locale
    const dateFnsLocale = useMemo(() => getDateFnsLocale(data.locale), [data.locale]);

    // Map backend time format values to date-fns tokens
    const timeFormatTokens: Record<string, string> = {
        '24h': 'HH:mm:ss',
        '12h': 'h:mm:ss a',
    };

    // Format date/time for a specific timezone using date-fns
    const formatForTimezone = (date: Date, timezone: string, dateFormat: string, timeFormat: string) => {
        try {
            const tzDate = new TZDate(date, timezone);
            const timeToken = timeFormatTokens[timeFormat] ?? 'HH:mm:ss';
            const formattedDate = format(tzDate, dateFormat, { locale: dateFnsLocale });
            const formattedTime = format(tzDate, timeToken, { locale: dateFnsLocale });
            return { date: formattedDate, time: formattedTime };
        } catch {
            return { date: '---', time: '--:--' };
        }
    };

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
        <>
            <Head title={t('config.page.title')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={Settings2}>{t('config.page.title')}</PageTitle>
                        <PageDescription>
                            {t('config.description', { name: tenantData.name })}
                        </PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Branding Section */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Building2 className="h-5 w-5" />
                                    {t('config.page.branding')}
                                </CardTitle>
                                <CardDescription>
                                    {t('config.page.branding_description')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2 max-w-md">
                                    <Label htmlFor="app_name">
                                        {t('config.page.app_name_label')}
                                    </Label>
                                    <Input
                                        id="app_name"
                                        type="text"
                                        placeholder={t('config.page.app_name_placeholder')}
                                        value={data.app_name}
                                        onChange={(e) => setData('app_name', e.target.value)}
                                    />
                                    {errors.app_name && (
                                        <p className="text-sm text-destructive">{errors.app_name}</p>
                                    )}
                                    <p className="text-sm text-muted-foreground">
                                        {t('config.page.app_name_note')}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Regional Settings Section */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Globe className="h-5 w-5" />
                                    {t('config.page.regional_settings')}
                                </CardTitle>
                                <CardDescription>
                                    {t('config.page.regional_settings_description')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                {/* Language & Currency Row */}
                                <div className="grid gap-6 md:grid-cols-2">
                                    {/* Language */}
                                    <div className="space-y-2">
                                        <Label htmlFor="locale">{t('config.page.language')}</Label>
                                        <Select
                                            value={data.locale}
                                            onValueChange={(value) => setData('locale', value)}
                                        >
                                            <SelectTrigger id="locale">
                                                <SelectValue placeholder={t('config.page.select_language')} />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {availableLocales.map((locale) => (
                                                    <SelectItem key={locale} value={locale}>
                                                        {localeLabels[locale] ?? locale}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {errors.locale && (
                                            <p className="text-sm text-destructive">{errors.locale}</p>
                                        )}
                                    </div>

                                    {/* Currency */}
                                    <div className="space-y-2">
                                        <Label htmlFor="currency">{t('config.page.currency')}</Label>
                                        <Select
                                            value={data.currency}
                                            onValueChange={(value) => setData('currency', value)}
                                        >
                                            <SelectTrigger id="currency">
                                                <SelectValue placeholder={t('config.page.select_currency')} />
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
                                        {/* Currency Locale (hidden, auto-synced with locale) */}
                                        <input type="hidden" name="currency_locale" value={data.locale} />
                                    </div>
                                </div>

                                {/* Date & Time Settings Row */}
                                <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                                    {/* Timezone */}
                                    <div className="space-y-2">
                                        <Label htmlFor="timezone">{t('config.page.timezone')}</Label>
                                        <Select
                                            value={data.timezone}
                                            onValueChange={(value) => setData('timezone', value)}
                                        >
                                            <SelectTrigger id="timezone">
                                                <SelectValue placeholder={t('config.page.select_timezone')} />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <div className="px-2 pb-2">
                                                    <Input
                                                        placeholder={t('config.page.search_timezone')}
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
                                                        {t('config.more_timezones', { count: filteredTimezones.length - 50 })}
                                                    </p>
                                                )}
                                            </SelectContent>
                                        </Select>
                                        {errors.timezone && (
                                            <p className="text-sm text-destructive">{errors.timezone}</p>
                                        )}
                                    </div>

                                    {/* Date Format */}
                                    <div className="space-y-2">
                                        <Label htmlFor="date_format">{t('config.page.date_format')}</Label>
                                        <Select
                                            value={data.date_format}
                                            onValueChange={(value) => setData('date_format', value)}
                                        >
                                            <SelectTrigger id="date_format">
                                                <SelectValue placeholder={t('config.page.select_date_format')} />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {Object.entries(availableDateFormats).map(([format, label]) => (
                                                    <SelectItem key={format} value={format}>
                                                        {label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {errors.date_format && (
                                            <p className="text-sm text-destructive">{errors.date_format}</p>
                                        )}
                                    </div>

                                    {/* Time Format */}
                                    <div className="space-y-2">
                                        <Label htmlFor="time_format">{t('config.page.time_format')}</Label>
                                        <Select
                                            value={data.time_format}
                                            onValueChange={(value) => setData('time_format', value)}
                                        >
                                            <SelectTrigger id="time_format">
                                                <SelectValue placeholder={t('config.page.select_time_format')} />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {Object.entries(availableTimeFormats).map(([format, label]) => (
                                                    <SelectItem key={format} value={format}>
                                                        {label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {errors.time_format && (
                                            <p className="text-sm text-destructive">{errors.time_format}</p>
                                        )}
                                    </div>

                                    {/* Week Starts On */}
                                    <div className="space-y-2">
                                        <Label htmlFor="week_starts_on">{t('config.page.week_starts_on')}</Label>
                                        <Select
                                            value={String(data.week_starts_on)}
                                            onValueChange={(value) => setData('week_starts_on', parseInt(value, 10))}
                                        >
                                            <SelectTrigger id="week_starts_on">
                                                <SelectValue placeholder={t('config.page.select_week_start')} />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {Object.entries(availableWeekdays).map(([day, label]) => (
                                                    <SelectItem key={day} value={day}>
                                                        {label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {errors.week_starts_on && (
                                            <p className="text-sm text-destructive">{errors.week_starts_on}</p>
                                        )}
                                    </div>
                                </div>

                                {/* Timezone Helper & Preview */}
                                <div className="rounded-md border bg-muted/50 p-4 space-y-3">
                                    <p className="text-sm text-muted-foreground">
                                        {t('config.page.timezone_helper')}
                                    </p>
                                    <div className="space-y-2">
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">{t('config.page.universal_time')}</span>
                                            <span className="font-mono">
                                                {(() => {
                                                    const utc = formatForTimezone(currentTime, 'UTC', data.date_format, data.time_format);
                                                    return `${utc.date} ${utc.time}`;
                                                })()}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">{t('config.page.local_time')}</span>
                                            <span className="font-mono font-medium">
                                                {(() => {
                                                    const local = formatForTimezone(currentTime, data.timezone, data.date_format, data.time_format);
                                                    return `${local.date} ${local.time}`;
                                                })()}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Email Section */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Mail className="h-5 w-5" />
                                    {t('config.page.email_settings')}
                                </CardTitle>
                                <CardDescription>
                                    {t('config.page.email_settings_description')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-6 md:grid-cols-2">
                                {/* From Address */}
                                <div className="space-y-2">
                                    <Label htmlFor="mail_from_address">
                                        {t('config.page.from_address')}
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
                                        {t('config.page.from_address_note')}
                                    </p>
                                </div>

                                {/* From Name */}
                                <div className="space-y-2">
                                    <Label htmlFor="mail_from_name">
                                        {t('config.page.from_name')}
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
        </>
    );
}

ConfigSettings.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default ConfigSettings;
