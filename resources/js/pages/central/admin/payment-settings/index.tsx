import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion';
import AdminLayout from '@/layouts/central/admin-layout';
import { Head, router, useRemember } from '@inertiajs/react';
import {
    CreditCard,
    Landmark,
    ShieldCheck,
    Store,
    CheckCircle,
    XCircle,
    AlertTriangle,
    ToggleLeft,
    ToggleRight,
    ExternalLink,
} from 'lucide-react';
import {
    Page,
    PageHeader,
    PageHeaderContent,
    PageTitle,
    PageDescription,
    PageContent,
} from '@/components/shared/layout/page';
import { type BreadcrumbItem } from '@/types';
import admin from '@/routes/central/admin';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement, useState } from 'react';
import { GatewayForm } from './components/gateway-form';

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
    docs_url: string;
    sandbox_url: string | null;
    production_credential_hints: Record<string, string | null>;
    sandbox_credential_hints: Record<string, string | null>;
    has_production_credentials: boolean;
    has_sandbox_credentials: boolean;
    last_tested_at: string | null;
    last_test_success: boolean | null;
    last_test_error: string | null;
    created_at: string | null;
    updated_at: string | null;
}

interface GatewayMeta {
    value: string;
    displayName: string;
    description: string;
    icon: string;
    color: string;
    supportedPaymentTypes: string[];
    defaultCountries: string[];
    docsUrl: string;
}

interface Props {
    settings: PaymentSettingResource[];
    gateways: Record<string, GatewayMeta>;
}

const gatewayIcons: Record<string, typeof CreditCard> = {
    stripe: CreditCard,
    asaas: Landmark,
    pagseguro: ShieldCheck,
    mercadopago: Store,
};

function PaymentSettingsIndex({ settings, gateways }: Props) {
    const { t } = useLaravelReactI18n();
    const [testingGateway, setTestingGateway] = useState<string | null>(null);
    const [openAccordion, setOpenAccordion] = useRemember<string>(
        '',
        'payment-settings-accordion'
    );

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        {
            title: t('payment_settings.title'),
            href: admin.paymentSettings.index.url(),
        },
    ];

    useSetBreadcrumbs(breadcrumbs);

    const handleToggleSandbox = (gateway: string) => {
        router.post(
            admin.paymentSettings.toggleSandbox.url(gateway),
            {},
            {
                preserveScroll: true,
                preserveState: true,
            }
        );
    };

    const handleTest = (gateway: string) => {
        setTestingGateway(gateway);
        router.post(
            admin.paymentSettings.test.url(gateway),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => {
                    setTestingGateway(null);
                },
            }
        );
    };

    const GatewayCard = ({ setting }: { setting: PaymentSettingResource }) => {
        const Icon = gatewayIcons[setting.gateway] || CreditCard;
        const meta = gateways[setting.gateway];

        const hasCredentials = setting.is_sandbox
            ? setting.has_sandbox_credentials
            : setting.has_production_credentials;

        return (
            <AccordionItem value={setting.gateway} className="border rounded-lg px-4">
                <AccordionTrigger className="hover:no-underline">
                    <div className="flex items-center justify-between w-full pr-4">
                        <div className="flex items-center gap-3">
                            <div
                                className="rounded-lg p-2"
                                style={{ backgroundColor: `${meta?.color}20` }}
                            >
                                <Icon
                                    className="h-5 w-5"
                                    style={{ color: meta?.color }}
                                />
                            </div>
                            <div className="text-left">
                                <h3 className="font-medium">
                                    {setting.display_name}
                                </h3>
                                <p className="text-muted-foreground text-sm">
                                    {meta?.description ||
                                        t('payment_settings.no_description')}
                                </p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            {setting.is_enabled ? (
                                <Badge variant="default" className="bg-green-500">
                                    {t('common.enabled')}
                                </Badge>
                            ) : (
                                <Badge variant="secondary">
                                    {t('common.disabled')}
                                </Badge>
                            )}
                            <Badge
                                variant={setting.is_sandbox ? 'outline' : 'default'}
                                className={
                                    setting.is_sandbox
                                        ? 'border-orange-500 text-orange-500'
                                        : 'bg-blue-500'
                                }
                            >
                                {setting.is_sandbox
                                    ? t('payment_settings.mode.sandbox')
                                    : t('payment_settings.mode.production')}
                            </Badge>
                            {setting.is_default && (
                                <Badge variant="secondary">
                                    {t('common.default')}
                                </Badge>
                            )}
                        </div>
                    </div>
                </AccordionTrigger>
                <AccordionContent className="pt-4">
                    <div className="space-y-6">
                        {/* Quick Actions */}
                        <div className="flex flex-wrap items-center gap-4">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => handleToggleSandbox(setting.gateway)}
                                className="gap-2"
                            >
                                {setting.is_sandbox ? (
                                    <>
                                        <ToggleRight className="h-4 w-4" />
                                        {t(
                                            'payment_settings.actions.switch_to_production'
                                        )}
                                    </>
                                ) : (
                                    <>
                                        <ToggleLeft className="h-4 w-4" />
                                        {t(
                                            'payment_settings.actions.switch_to_sandbox'
                                        )}
                                    </>
                                )}
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => handleTest(setting.gateway)}
                                disabled={
                                    testingGateway === setting.gateway ||
                                    !hasCredentials
                                }
                                className="gap-2"
                            >
                                {testingGateway === setting.gateway ? (
                                    <>
                                        <span className="animate-spin">...</span>
                                        {t('payment_settings.actions.testing')}
                                    </>
                                ) : (
                                    <>
                                        <CheckCircle className="h-4 w-4" />
                                        {t('payment_settings.actions.test_connection')}
                                    </>
                                )}
                            </Button>
                            {meta?.docsUrl && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    asChild
                                    className="gap-2"
                                >
                                    <a
                                        href={meta.docsUrl}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <ExternalLink className="h-4 w-4" />
                                        {t('payment_settings.actions.documentation')}
                                    </a>
                                </Button>
                            )}
                        </div>

                        {/* Last Test Status */}
                        {setting.last_tested_at && (
                            <div
                                className={`rounded-lg p-3 flex items-start gap-2 ${
                                    setting.last_test_success
                                        ? 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400'
                                        : 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400'
                                }`}
                            >
                                {setting.last_test_success ? (
                                    <CheckCircle className="h-5 w-5 shrink-0" />
                                ) : (
                                    <XCircle className="h-5 w-5 shrink-0" />
                                )}
                                <div className="flex flex-col gap-1">
                                    <span className="text-sm">
                                        {setting.last_test_success
                                            ? t('payment_settings.test.success')
                                            : setting.last_test_error}
                                    </span>
                                    <span className="text-xs opacity-75">
                                        {t('payment_settings.last_tested', {
                                            date: new Date(
                                                setting.last_tested_at
                                            ).toLocaleString(),
                                        })}
                                    </span>
                                </div>
                            </div>
                        )}

                        {/* Credentials Warning */}
                        {!hasCredentials && (
                            <div className="rounded-lg bg-yellow-50 dark:bg-yellow-900/20 p-3 flex items-start gap-2 text-yellow-700 dark:text-yellow-400">
                                <AlertTriangle className="h-5 w-5 shrink-0" />
                                <span className="text-sm">
                                    {t(
                                        'payment_settings.warnings.no_credentials',
                                        {
                                            mode: setting.is_sandbox
                                                ? t('payment_settings.mode.sandbox')
                                                : t('payment_settings.mode.production'),
                                        }
                                    )}
                                </span>
                            </div>
                        )}

                        {/* Gateway Form */}
                        <GatewayForm setting={setting} />
                    </div>
                </AccordionContent>
            </AccordionItem>
        );
    };

    return (
        <>
            <Head title={t('payment_settings.title')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle>{t('payment_settings.title')}</PageTitle>
                        <PageDescription>
                            {t('payment_settings.description')}
                        </PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    {settings.length > 0 ? (
                        <Accordion
                            type="single"
                            collapsible
                            className="space-y-4"
                            value={openAccordion}
                            onValueChange={setOpenAccordion}
                        >
                            {settings.map((setting) => (
                                <GatewayCard key={setting.gateway} setting={setting} />
                            ))}
                        </Accordion>
                    ) : (
                        <Card className="border-dashed">
                            <CardContent className="flex flex-col items-center justify-center py-12">
                                <CreditCard className="text-muted-foreground mb-4 h-12 w-12" />
                                <p className="text-muted-foreground">
                                    {t('payment_settings.no_gateways')}
                                </p>
                            </CardContent>
                        </Card>
                    )}
                </PageContent>
            </Page>
        </>
    );
}

PaymentSettingsIndex.layout = (page: ReactElement) => (
    <AdminLayout>{page}</AdminLayout>
);

export default PaymentSettingsIndex;
