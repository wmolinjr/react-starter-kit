import { useState } from 'react';
import {
    Page,
    PageHeader,
    PageHeaderContent,
    PageTitle,
    PageDescription,
    PageContent,
} from '@/components/shared/layout/page';
import CustomerLayout from '@/layouts/customer-layout';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';
import {
    PaymentMethodSelector,
    PixPayment,
    BoletoPayment,
} from '@/components/shared/billing';
import { PriceDisplay } from '@/components/shared/billing/primitives';
import customer from '@/routes/central/account';
import { type BreadcrumbItem, type PlanResource } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { CreditCard, Building2, Check, Shield, ArrowLeft } from 'lucide-react';
import { type ReactElement } from 'react';
import type { PaymentConfigResource } from '@/types/resources';

interface SignupData {
    id: string;
    workspace_name: string;
    workspace_slug: string;
    billing_period: 'monthly' | 'yearly';
    plan: PlanResource | null;
}

interface CheckoutProps {
    signup: SignupData;
    paymentConfig: PaymentConfigResource;
}

type PaymentStep = 'selecting' | 'processing' | 'pix' | 'boleto' | 'success';

function TenantCheckout({ signup, paymentConfig }: CheckoutProps) {
    const { t } = useLaravelReactI18n();
    const [paymentMethod, setPaymentMethod] = useState<'card' | 'pix' | 'boleto'>('card');
    const [paymentStep, setPaymentStep] = useState<PaymentStep>('selecting');
    const [pixData, setPixData] = useState<{ qr_code: string; qr_code_text: string; expires_at: string } | null>(null);
    const [boletoData, setBoletoData] = useState<{ barcode: string; pdf_url: string; due_date: string } | null>(null);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('customer.dashboard.title'), href: customer.dashboard.url() },
        { title: t('customer.workspace.title'), href: customer.tenants.index.url() },
        { title: t('billing.page.checkout'), href: '#' },
    ];
    useSetBreadcrumbs(breadcrumbs);

    const plan = signup.plan;
    const price = plan ? (signup.billing_period === 'yearly' ? (plan.price * 12 * 0.8) : plan.price) : 0;

    const handlePayment = async () => {
        setProcessing(true);
        setError(null);

        try {
            const response = await fetch(`/account/tenants/checkout/${signup.id}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ payment_method: paymentMethod }),
            });

            const data = await response.json();

            if (data.type === 'redirect') {
                // Stripe checkout - redirect
                window.location.href = data.url;
            } else if (data.type === 'pix') {
                setPixData(data.pix);
                setPaymentStep('pix');
            } else if (data.type === 'boleto') {
                setBoletoData(data.boleto);
                setPaymentStep('boleto');
            }
        } catch (err) {
            setError(t('billing.errors.payment_failed'));
        } finally {
            setProcessing(false);
        }
    };

    const availableMethods = paymentConfig?.available_methods || ['card'];

    return (
        <>
            <Head title={t('billing.page.checkout')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={CreditCard}>
                            {t('billing.page.checkout')}
                        </PageTitle>
                        <PageDescription>
                            {t('billing.page.complete_purchase')}
                        </PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    {paymentStep === 'selecting' && (
                        <div className="grid gap-6 lg:grid-cols-5">
                            {/* Order Summary - Left Side */}
                            <div className="lg:col-span-2 space-y-6">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>{t('checkout.summary.order_summary')}</CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        {/* Workspace Info */}
                                        <div className="flex items-center gap-3 p-3 bg-muted rounded-lg">
                                            <Building2 className="h-5 w-5 text-muted-foreground" />
                                            <div>
                                                <p className="font-medium">{signup.workspace_name}</p>
                                                <p className="text-sm text-muted-foreground">
                                                    {signup.workspace_slug}.test
                                                </p>
                                            </div>
                                        </div>

                                        {/* Plan Details */}
                                        {plan && (
                                            <div className="space-y-3">
                                                <div className="flex items-center justify-between">
                                                    <span className="text-muted-foreground">{t('billing.page.plan')}</span>
                                                    <span className="font-medium">{plan.name}</span>
                                                </div>
                                                <div className="flex items-center justify-between">
                                                    <span className="text-muted-foreground">{t('billing.page.billing_cycle')}</span>
                                                    <Badge variant="secondary">
                                                        {signup.billing_period === 'yearly'
                                                            ? t('enums.billing.period.yearly')
                                                            : t('enums.billing.period.monthly')
                                                        }
                                                    </Badge>
                                                </div>
                                                {signup.billing_period === 'yearly' && (
                                                    <div className="text-sm text-green-600 flex items-center gap-1">
                                                        <Check className="h-4 w-4" />
                                                        {t('billing.price.yearly_savings', { default: 'Save 20%' })}
                                                    </div>
                                                )}
                                            </div>
                                        )}

                                        <hr />

                                        {/* Total */}
                                        <div className="flex items-center justify-between">
                                            <span className="text-lg font-semibold">{t('billing.price.total')}</span>
                                            <PriceDisplay
                                                amount={price}
                                                currency={plan?.currency || 'USD'}
                                                period={signup.billing_period}
                                                size="lg"
                                            />
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Security Badge */}
                                <Card>
                                    <CardContent className="flex items-center gap-3 py-4">
                                        <Shield className="h-5 w-5 text-green-600" />
                                        <div className="text-sm">
                                            <p className="font-medium">{t('customer.payment.secure_processing')}</p>
                                            <p className="text-muted-foreground">
                                                {t('customer.payment.secure_processing_description')}
                                            </p>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>

                            {/* Payment Method - Right Side */}
                            <div className="lg:col-span-3">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>{t('customer.payment.method')}</CardTitle>
                                        <CardDescription>
                                            {t('checkout.payment.select_method')}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-6">
                                        <PaymentMethodSelector
                                            value={paymentMethod}
                                            onChange={(method) => setPaymentMethod(method as 'card' | 'pix' | 'boleto')}
                                            availableMethods={availableMethods}
                                        />

                                        {error && (
                                            <div className="p-3 text-sm text-destructive bg-destructive/10 rounded-lg">
                                                {error}
                                            </div>
                                        )}

                                        <div className="flex gap-4 pt-4">
                                            <Button
                                                onClick={handlePayment}
                                                disabled={processing}
                                                className="flex-1"
                                                size="lg"
                                            >
                                                {processing && <Spinner className="mr-2" />}
                                                {paymentMethod === 'card'
                                                    ? t('checkout.payment.pay_with_card')
                                                    : paymentMethod === 'pix'
                                                        ? t('checkout.payment.generate_pix')
                                                        : t('checkout.payment.generate_boleto')
                                                }
                                            </Button>
                                        </div>

                                        <Button
                                            variant="ghost"
                                            className="w-full"
                                            onClick={() => router.visit(customer.tenants.create.url())}
                                        >
                                            <ArrowLeft className="mr-2 h-4 w-4" />
                                            {t('common.back')}
                                        </Button>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>
                    )}

                    {paymentStep === 'pix' && pixData && (
                        <div className="max-w-lg mx-auto">
                            <PixPayment
                                qrCodeUrl={pixData.qr_code}
                                qrCodeData={pixData.qr_code_text}
                                expiresAt={pixData.expires_at}
                                purchaseId={signup.id}
                                onExpired={() => setPaymentStep('selecting')}
                            />
                        </div>
                    )}

                    {paymentStep === 'boleto' && boletoData && (
                        <div className="max-w-lg mx-auto">
                            <BoletoPayment
                                barcode={boletoData.barcode}
                                boletoUrl={boletoData.pdf_url}
                                dueDate={boletoData.due_date}
                                amount={new Intl.NumberFormat(undefined, {
                                    style: 'currency',
                                    currency: plan?.currency || 'BRL',
                                }).format(price)}
                            />
                        </div>
                    )}
                </PageContent>
            </Page>
        </>
    );
}

TenantCheckout.layout = (page: ReactElement) => <CustomerLayout>{page}</CustomerLayout>;

export default TenantCheckout;
