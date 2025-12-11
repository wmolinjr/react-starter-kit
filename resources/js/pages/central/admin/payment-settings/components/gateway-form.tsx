import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useForm } from '@inertiajs/react';
import { Eye, EyeOff, Copy, Check, Info } from 'lucide-react';
import admin from '@/routes/central/admin';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { useState, useEffect } from 'react';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

interface CredentialField {
    key: string;
    label: string;
    type: string;
    required: boolean;
    prefix?: string;
    help?: string;
}

interface PaymentSettingResource {
    id: string;
    gateway: string;
    display_name: string;
    is_enabled: boolean;
    is_sandbox: boolean;
    is_default: boolean;
    enabled_payment_types: string[];
    available_countries: string[];
    webhook_urls: Record<string, string>;
    supported_payment_types: string[];
    credential_fields: CredentialField[];
    production_credential_hints: Record<string, string | null>;
    sandbox_credential_hints: Record<string, string | null>;
    has_production_credentials: boolean;
    has_sandbox_credentials: boolean;
}

interface Props {
    setting: PaymentSettingResource;
}

const PAYMENT_TYPE_LABELS: Record<string, string> = {
    card: 'admin.payments.settings.payment_types.card',
    pix: 'admin.payments.settings.payment_types.pix',
    boleto: 'admin.payments.settings.payment_types.boleto',
};

const COUNTRY_LABELS: Record<string, string> = {
    BR: 'Brazil',
    US: 'United States',
    CA: 'Canada',
    GB: 'United Kingdom',
    EU: 'European Union',
    AU: 'Australia',
    AR: 'Argentina',
    MX: 'Mexico',
    CO: 'Colombia',
    CL: 'Chile',
};

export function GatewayForm({ setting }: Props) {
    const { t } = useLaravelReactI18n();
    const [showPasswords, setShowPasswords] = useState<Record<string, boolean>>({});
    const [copiedWebhook, setCopiedWebhook] = useState<string | null>(null);

    const { data, setData, put, processing, errors } = useForm({
        is_enabled: setting.is_enabled,
        is_default: setting.is_default,
        enabled_payment_types: setting.enabled_payment_types,
        available_countries: setting.available_countries,
        production_credentials: {} as Record<string, string>,
        sandbox_credentials: {} as Record<string, string>,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(admin.paymentSettings.update.url(setting.gateway), {
            preserveScroll: true,
        });
    };

    const togglePasswordVisibility = (key: string) => {
        setShowPasswords((prev) => ({
            ...prev,
            [key]: !prev[key],
        }));
    };

    const handleCopyWebhook = async (url: string, mode: string) => {
        await navigator.clipboard.writeText(url);
        setCopiedWebhook(mode);
        setTimeout(() => setCopiedWebhook(null), 2000);
    };

    const handlePaymentTypeToggle = (type: string, checked: boolean) => {
        if (checked) {
            setData('enabled_payment_types', [...data.enabled_payment_types, type]);
        } else {
            setData(
                'enabled_payment_types',
                data.enabled_payment_types.filter((t) => t !== type)
            );
        }
    };

    const handleCountryToggle = (country: string, checked: boolean) => {
        if (checked) {
            setData('available_countries', [...data.available_countries, country]);
        } else {
            setData(
                'available_countries',
                data.available_countries.filter((c) => c !== country)
            );
        }
    };

    const CredentialInput = ({
        field,
        mode,
        hints,
    }: {
        field: CredentialField;
        mode: 'production' | 'sandbox';
        hints: Record<string, string | null>;
    }) => {
        const key = `${mode}_${field.key}`;
        const isPassword = field.type === 'password';
        const showPassword = showPasswords[key];
        const hint = hints[field.key];
        const credKey = `${mode}_credentials` as const;

        return (
            <div className="space-y-2">
                <div className="flex items-center gap-2">
                    <Label htmlFor={key}>
                        {field.label}
                        {field.required && <span className="text-red-500 ml-1">*</span>}
                    </Label>
                    {field.help && (
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Info className="h-4 w-4 text-muted-foreground cursor-help" />
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p className="max-w-xs">{field.help}</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    )}
                </div>
                <div className="relative">
                    <Input
                        id={key}
                        type={isPassword && !showPassword ? 'password' : 'text'}
                        placeholder={hint || field.prefix || undefined}
                        value={(data[credKey] as Record<string, string>)[field.key] || ''}
                        onChange={(e) =>
                            setData(credKey, {
                                ...(data[credKey] as Record<string, string>),
                                [field.key]: e.target.value,
                            })
                        }
                        className={isPassword ? 'pr-10' : ''}
                    />
                    {isPassword && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="absolute right-1 top-1/2 -translate-y-1/2 h-7 w-7"
                            onClick={() => togglePasswordVisibility(key)}
                        >
                            {showPassword ? (
                                <EyeOff className="h-4 w-4" />
                            ) : (
                                <Eye className="h-4 w-4" />
                            )}
                        </Button>
                    )}
                </div>
                {hint && (
                    <p className="text-xs text-muted-foreground">
                        {t('payments.settings.current_hint')}: {hint}
                    </p>
                )}
            </div>
        );
    };

    const WebhookUrlDisplay = ({
        mode,
        url,
    }: {
        mode: string;
        url: string;
    }) => (
        <div className="flex items-center gap-2">
            <code className="flex-1 bg-muted px-3 py-2 rounded text-sm break-all">
                {url}
            </code>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                onClick={() => handleCopyWebhook(url, mode)}
            >
                {copiedWebhook === mode ? (
                    <Check className="h-4 w-4 text-green-500" />
                ) : (
                    <Copy className="h-4 w-4" />
                )}
            </Button>
        </div>
    );

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            {/* General Settings */}
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">
                        {t('payments.settings.sections.general')}
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <Label>{t('payments.settings.fields.enabled')}</Label>
                            <p className="text-sm text-muted-foreground">
                                {t('payments.settings.fields.enabled_help')}
                            </p>
                        </div>
                        <Switch
                            checked={data.is_enabled}
                            onCheckedChange={(checked) => setData('is_enabled', checked)}
                        />
                    </div>
                    <div className="flex items-center justify-between">
                        <div>
                            <Label>{t('payments.settings.fields.default')}</Label>
                            <p className="text-sm text-muted-foreground">
                                {t('payments.settings.fields.default_help')}
                            </p>
                        </div>
                        <Switch
                            checked={data.is_default}
                            onCheckedChange={(checked) => setData('is_default', checked)}
                        />
                    </div>
                </CardContent>
            </Card>

            {/* Payment Types */}
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">
                        {t('payments.settings.sections.payment_types')}
                    </CardTitle>
                    <CardDescription>
                        {t('payments.settings.sections.payment_types_help')}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="flex flex-wrap gap-4">
                        {setting.supported_payment_types.map((type) => (
                            <div key={type} className="flex items-center space-x-2">
                                <Checkbox
                                    id={`payment-type-${type}`}
                                    checked={data.enabled_payment_types.includes(type)}
                                    onCheckedChange={(checked) =>
                                        handlePaymentTypeToggle(type, !!checked)
                                    }
                                />
                                <Label htmlFor={`payment-type-${type}`} className="cursor-pointer">
                                    {t(PAYMENT_TYPE_LABELS[type] || type)}
                                </Label>
                            </div>
                        ))}
                    </div>
                </CardContent>
            </Card>

            {/* Available Countries */}
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">
                        {t('payments.settings.sections.countries')}
                    </CardTitle>
                    <CardDescription>
                        {t('payments.settings.sections.countries_help')}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="flex flex-wrap gap-4">
                        {Object.entries(COUNTRY_LABELS).map(([code, name]) => (
                            <div key={code} className="flex items-center space-x-2">
                                <Checkbox
                                    id={`country-${code}`}
                                    checked={data.available_countries.includes(code)}
                                    onCheckedChange={(checked) =>
                                        handleCountryToggle(code, !!checked)
                                    }
                                />
                                <Label htmlFor={`country-${code}`} className="cursor-pointer">
                                    {name}
                                </Label>
                            </div>
                        ))}
                    </div>
                </CardContent>
            </Card>

            {/* Credentials */}
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">
                        {t('payments.settings.sections.credentials')}
                    </CardTitle>
                    <CardDescription>
                        {t('payments.settings.sections.credentials_help')}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Tabs defaultValue="production" className="w-full">
                        <TabsList className="grid w-full grid-cols-2">
                            <TabsTrigger value="production">
                                {t('payments.settings.mode.production')}
                                {setting.has_production_credentials && (
                                    <Check className="ml-2 h-4 w-4 text-green-500" />
                                )}
                            </TabsTrigger>
                            <TabsTrigger value="sandbox">
                                {t('payments.settings.mode.sandbox')}
                                {setting.has_sandbox_credentials && (
                                    <Check className="ml-2 h-4 w-4 text-green-500" />
                                )}
                            </TabsTrigger>
                        </TabsList>
                        <TabsContent value="production" className="space-y-4 mt-4">
                            {setting.credential_fields.map((field) => (
                                <CredentialInput
                                    key={field.key}
                                    field={field}
                                    mode="production"
                                    hints={setting.production_credential_hints}
                                />
                            ))}
                            {setting.webhook_urls.production && (
                                <div className="space-y-2 pt-4 border-t">
                                    <Label>{t('payments.settings.fields.webhook_url')}</Label>
                                    <WebhookUrlDisplay
                                        mode="production"
                                        url={setting.webhook_urls.production}
                                    />
                                </div>
                            )}
                        </TabsContent>
                        <TabsContent value="sandbox" className="space-y-4 mt-4">
                            {setting.credential_fields.map((field) => (
                                <CredentialInput
                                    key={field.key}
                                    field={field}
                                    mode="sandbox"
                                    hints={setting.sandbox_credential_hints}
                                />
                            ))}
                            {setting.webhook_urls.sandbox && (
                                <div className="space-y-2 pt-4 border-t">
                                    <Label>{t('payments.settings.fields.webhook_url')}</Label>
                                    <WebhookUrlDisplay
                                        mode="sandbox"
                                        url={setting.webhook_urls.sandbox}
                                    />
                                </div>
                            )}
                        </TabsContent>
                    </Tabs>
                </CardContent>
            </Card>

            {/* Submit */}
            <div className="flex justify-end">
                <Button type="submit" disabled={processing}>
                    {processing
                        ? t('common.saving')
                        : t('payments.settings.actions.save_settings')}
                </Button>
            </div>
        </form>
    );
}
