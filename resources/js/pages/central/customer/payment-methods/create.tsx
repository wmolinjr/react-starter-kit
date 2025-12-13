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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import customer from '@/routes/central/account';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { CreditCard, Lock, AlertCircle, Plus } from 'lucide-react';
import { useState, useEffect, type ReactElement } from 'react';

interface PaymentMethodCreateProps {
    provider: string;
    setupData: {
        client_secret?: string;
        publishable_key?: string;
    };
    supportedTypes: string[];
}

function PaymentMethodCreate({ provider, setupData, supportedTypes }: PaymentMethodCreateProps) {
    const { t } = useLaravelReactI18n();
    const [stripeReady, setStripeReady] = useState(false);
    const [stripeError, setStripeError] = useState<string | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('customer.dashboard.title'), href: customer.dashboard.url() },
        { title: t('customer.payment.methods'), href: customer.paymentMethods.index.url() },
        { title: t('customer.payment.add_method'), href: customer.paymentMethods.create.url() },
    ];
    useSetBreadcrumbs(breadcrumbs);

    const { data, setData, post, processing, errors } = useForm({
        payment_method_id: '',
        card_token: '',
        type: 'card',
        provider: provider,
        card_number: '',
        card_holder: '',
        card_exp_month: '',
        card_exp_year: '',
        card_cvv: '',
    });

    const serverErrors = errors as Record<string, string>;

    useEffect(() => {
        if (provider === 'stripe' && setupData.publishable_key) {
            const script = document.createElement('script');
            script.src = 'https://js.stripe.com/v3/';
            script.async = true;
            script.onload = () => setStripeReady(true);
            document.body.appendChild(script);

            return () => {
                document.body.removeChild(script);
            };
        }
    }, [provider, setupData.publishable_key]);

    const handleStripeSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setStripeError(null);

        if (!window.Stripe || !setupData.client_secret) {
            setStripeError(t('customer.stripe_not_loaded'));
            return;
        }

        setStripeError(t('customer.stripe_elements_required'));
    };

    const handleManualSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        post('/account/payment-methods', {
            preserveScroll: true,
        });
    };

    const handleSubmit = provider === 'stripe' ? handleStripeSubmit : handleManualSubmit;

    return (
        <>
            <Head title={t('customer.payment.add_method')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={Plus}>
                            {t('customer.payment.add_method')}
                        </PageTitle>
                        <PageDescription>
                            {t('customer.payment.add_method_description')}
                        </PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent className="max-w-2xl">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <CreditCard className="h-5 w-5" />
                                {t('customer.payment.card_details')}
                            </CardTitle>
                            <CardDescription>
                                {t('customer.payment.card_details_description')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleSubmit} className="space-y-6">
                                {provider === 'stripe' ? (
                                    <div className="space-y-4">
                                        <div className="p-4 border rounded-lg bg-muted/50 min-h-[120px] flex items-center justify-center">
                                            {stripeReady ? (
                                                <p className="text-sm text-muted-foreground text-center">
                                                    {t('customer.payment.stripe_placeholder')}
                                                </p>
                                            ) : (
                                                <p className="text-sm text-muted-foreground">
                                                    {t('customer.loading_stripe')}...
                                                </p>
                                            )}
                                        </div>
                                        {stripeError && (
                                            <Alert variant="destructive">
                                                <AlertCircle className="h-4 w-4" />
                                                <AlertDescription>{stripeError}</AlertDescription>
                                            </Alert>
                                        )}
                                    </div>
                                ) : (
                                    <div className="space-y-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="card_holder">{t('customer.card_holder_name')}</Label>
                                            <Input
                                                id="card_holder"
                                                value={data.card_holder}
                                                onChange={(e) => setData('card_holder', e.target.value)}
                                                placeholder={t('customer.card_holder_placeholder')}
                                                required
                                            />
                                            {errors.card_holder && (
                                                <p className="text-sm text-destructive">{errors.card_holder}</p>
                                            )}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="card_number">{t('customer.card_number')}</Label>
                                            <Input
                                                id="card_number"
                                                value={data.card_number}
                                                onChange={(e) => setData('card_number', e.target.value.replace(/\D/g, '').slice(0, 16))}
                                                placeholder="4242 4242 4242 4242"
                                                maxLength={16}
                                                required
                                            />
                                            {errors.card_number && (
                                                <p className="text-sm text-destructive">{errors.card_number}</p>
                                            )}
                                        </div>

                                        <div className="grid grid-cols-3 gap-4">
                                            <div className="space-y-2">
                                                <Label htmlFor="card_exp_month">{t('customer.exp_month')}</Label>
                                                <Input
                                                    id="card_exp_month"
                                                    value={data.card_exp_month}
                                                    onChange={(e) => setData('card_exp_month', e.target.value.replace(/\D/g, '').slice(0, 2))}
                                                    placeholder="MM"
                                                    maxLength={2}
                                                    required
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="card_exp_year">{t('customer.exp_year')}</Label>
                                                <Input
                                                    id="card_exp_year"
                                                    value={data.card_exp_year}
                                                    onChange={(e) => setData('card_exp_year', e.target.value.replace(/\D/g, '').slice(0, 4))}
                                                    placeholder="YYYY"
                                                    maxLength={4}
                                                    required
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="card_cvv">{t('customer.cvv')}</Label>
                                                <Input
                                                    id="card_cvv"
                                                    type="password"
                                                    value={data.card_cvv}
                                                    onChange={(e) => setData('card_cvv', e.target.value.replace(/\D/g, '').slice(0, 4))}
                                                    placeholder="123"
                                                    maxLength={4}
                                                    required
                                                />
                                            </div>
                                        </div>
                                        {(errors.card_exp_month || errors.card_exp_year || errors.card_cvv) && (
                                            <p className="text-sm text-destructive">
                                                {errors.card_exp_month || errors.card_exp_year || errors.card_cvv}
                                            </p>
                                        )}
                                    </div>
                                )}

                                {serverErrors.payment_method && (
                                    <Alert variant="destructive">
                                        <AlertCircle className="h-4 w-4" />
                                        <AlertDescription>{serverErrors.payment_method}</AlertDescription>
                                    </Alert>
                                )}

                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Lock className="h-4 w-4" />
                                    {t('customer.payment.secure')}
                                </div>

                                <div className="flex gap-4">
                                    <Button type="submit" disabled={processing}>
                                        {processing ? t('common.processing') : t('customer.payment.add_card')}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => router.visit(customer.paymentMethods.index.url())}
                                    >
                                        {t('common.cancel')}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="py-4">
                            <div className="flex items-center gap-4 text-sm text-muted-foreground">
                                <Lock className="h-5 w-5" />
                                <div>
                                    <p className="font-medium">{t('customer.payment.secure_processing')}</p>
                                    <p>{t('customer.payment.secure_processing_description')}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <p className="text-xs text-muted-foreground text-center">
                        {t('customer.payment_processed_by')} {provider.charAt(0).toUpperCase() + provider.slice(1)}
                    </p>
                </PageContent>
            </Page>
        </>
    );
}

PaymentMethodCreate.layout = (page: ReactElement) => <CustomerLayout>{page}</CustomerLayout>;

export default PaymentMethodCreate;

declare global {
    interface Window {
        Stripe?: (key: string) => unknown;
    }
}
