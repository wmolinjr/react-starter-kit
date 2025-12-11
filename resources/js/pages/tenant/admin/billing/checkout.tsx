import { Head, router } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { type ReactElement, useState, useEffect, useCallback } from 'react';
import AdminLayout from '@/layouts/tenant/admin-layout';
import admin from '@/routes/tenant/admin';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import {
    Page,
    PageHeader,
    PageHeaderContent,
    PageTitle,
    PageDescription,
    PageContent,
    PageHeaderActions,
} from '@/components/shared/layout/page';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    ArrowLeft,
    ShoppingCart,
    Loader2,
    ArrowRight,
    CheckCircle2,
} from 'lucide-react';
import {
    CheckoutCartSection,
    CheckoutPaymentSection,
    CheckoutSummarySection,
    CheckoutBenefitsSection,
    CheckoutPoliciesSection,
} from '@/components/shared/billing/checkout';
import { type PaymentMethod } from '@/components/shared/billing/payment-method-selector';
import { PixPayment } from '@/components/shared/billing/pix-payment';
import { BoletoPayment } from '@/components/shared/billing/boleto-payment';
import { AsaasCardForm } from '@/components/shared/billing/asaas-card-form';
import { useCheckout, useBillingPeriod } from '@/hooks/billing';
import type { BreadcrumbItem, PaymentConfigResource } from '@/types';
import {
    cartCheckout,
    cartPaymentStatus,
    asaasCardPayment,
} from '@/routes/tenant/admin/billing';

type CheckoutState = 'selecting' | 'processing' | 'async-payment' | 'asaas-card' | 'success';

interface AsyncPaymentResult {
    type: 'pix' | 'boleto';
    payment_id: string;
    purchase_id: string;
    amount: number;
    pix?: {
        qr_code?: string;
        qr_code_base64?: string;
        copy_paste?: string;
        payload?: string;
        expiration?: string;
        expiration_date?: string;
    };
    boleto?: {
        url?: string;
        bank_slip_url?: string;
        barcode?: string;
        bar_code?: string;
        digitable_line?: string;
        identification_field?: string;
    };
    due_date?: string;
}

interface AsaasCardPaymentResult {
    type: 'asaas_card';
    purchase_id: string;
    amount: number;
    gateway: string;
    requires_card_data: boolean;
}

interface CheckoutPageProps {
    paymentConfig?: PaymentConfigResource;
    [key: string]: unknown;
}

function CheckoutPageContent({ paymentConfig }: CheckoutPageProps) {
    const { t } = useLaravelReactI18n();
    const { period, setPeriod } = useBillingPeriod();
    const { items, removeItem, updateQuantity, clearCart, total } = useCheckout();

    const [checkoutState, setCheckoutState] = useState<CheckoutState>('selecting');
    const [selectedPaymentMethod, setSelectedPaymentMethod] = useState<PaymentMethod>('card');
    const [asyncPaymentResult, setAsyncPaymentResult] = useState<AsyncPaymentResult | null>(null);
    const [asaasCardResult, setAsaasCardResult] = useState<AsaasCardPaymentResult | null>(null);
    const [error, setError] = useState<string | null>(null);

    // Redirect if cart is empty and not in success state
    useEffect(() => {
        if (items.length === 0 && checkoutState !== 'success' && checkoutState !== 'async-payment') {
            router.visit(admin.addons.index.url());
        }
    }, [items, checkoutState]);

    // Check if cart has recurring items
    const hasRecurringItems = items.some((item) => item.isRecurring);

    // Currency
    const currency = 'BRL';

    // Format price
    const formatPrice = (amount: number): string => {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency,
            minimumFractionDigits: amount % 100 === 0 ? 0 : 2,
            maximumFractionDigits: 2,
        }).format(amount / 100);
    };

    // Breadcrumbs
    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('dashboard.page.title'), href: admin.dashboard.url() },
        { title: t('billing.page.title'), href: admin.billing.index.url() },
        { title: t('checkout.page.title', { default: 'Checkout' }), href: admin.billing.checkoutPage.url() },
    ];
    useSetBreadcrumbs(breadcrumbs);

    // Back navigation
    const handleBack = () => {
        if (checkoutState === 'async-payment') {
            setCheckoutState('selecting');
            setAsyncPaymentResult(null);
        } else if (checkoutState === 'asaas-card') {
            setCheckoutState('selecting');
            setAsaasCardResult(null);
        } else {
            router.visit(admin.addons.index.url());
        }
    };

    // Build cart items for API
    const buildCartItems = useCallback(() => {
        return items.map((item) => ({
            type: item.product.type === 'bundle' ? 'bundle' : 'addon',
            slug: item.product.slug,
            quantity: item.quantity,
            billing_period: item.isRecurring ? period : 'one_time',
        }));
    }, [items, period]);

    // Handle checkout submission
    const handleCheckout = async () => {
        setCheckoutState('processing');
        setError(null);

        const cartItems = buildCartItems();

        try {
            const response = await fetch(cartCheckout.url(), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN':
                        document.cookie
                            .split('; ')
                            .find((row) => row.startsWith('XSRF-TOKEN='))
                            ?.split('=')[1]
                            ?.replace(/%3D/g, '=') || '',
                },
                body: JSON.stringify({
                    items: cartItems,
                    payment_method: selectedPaymentMethod,
                }),
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(
                    errorData.message ||
                        t('checkout.error.failed', { default: 'Checkout failed' })
                );
            }

            const result = await response.json();

            // Handle different response types
            if (result.type === 'redirect') {
                // Stripe card checkout - redirect to Stripe hosted page
                window.location.href = result.url;
                return;
            }

            if (result.type === 'asaas_card') {
                // Asaas card checkout - show card form
                setAsaasCardResult(result as AsaasCardPaymentResult);
                setCheckoutState('asaas-card');
                return;
            }

            // PIX or Boleto - show async payment UI
            setAsyncPaymentResult(result as AsyncPaymentResult);
            setCheckoutState('async-payment');
        } catch (err) {
            setError(
                err instanceof Error
                    ? err.message
                    : t('checkout.error.failed', { default: 'Checkout failed' })
            );
            setCheckoutState('selecting');
        }
    };

    // Handle async payment success
    const handleAsyncPaymentSuccess = () => {
        setCheckoutState('success');
        setTimeout(() => {
            clearCart();
        }, 500);
    };

    // Handle Asaas card success
    const handleAsaasCardSuccess = () => {
        setCheckoutState('success');
        setTimeout(() => {
            clearCart();
        }, 500);
    };

    // Handle success close
    const handleSuccessClose = () => {
        router.visit(admin.billing.index.url());
    };

    // Get button label based on payment method
    const getCheckoutButtonLabel = () => {
        switch (selectedPaymentMethod) {
            case 'pix':
                return t('checkout.payment.generate_pix', { default: 'Generate PIX' });
            case 'boleto':
                return t('checkout.payment.generate_boleto', { default: 'Generate Boleto' });
            default:
                return t('checkout.payment.pay_with_card', { default: 'Pay with Card' });
        }
    };

    // Render PIX payment
    const renderPixPayment = () => {
        if (!asyncPaymentResult?.pix) return null;

        const pix = asyncPaymentResult.pix;
        const qrCodeUrl = pix.qr_code_base64
            ? `data:image/png;base64,${pix.qr_code_base64}`
            : pix.qr_code || '';
        const qrCodeData = pix.copy_paste || pix.payload || '';
        const expiration =
            pix.expiration_date ||
            pix.expiration ||
            new Date(Date.now() + 30 * 60 * 1000).toISOString();

        return (
            <Card>
                <CardContent className="pt-6">
                    <PixPayment
                        qrCodeUrl={qrCodeUrl}
                        qrCodeData={qrCodeData}
                        expiresAt={expiration}
                        purchaseId={asyncPaymentResult.purchase_id}
                        onSuccess={handleAsyncPaymentSuccess}
                        statusEndpoint={cartPaymentStatus.url()}
                        pollInterval={5000}
                    />
                </CardContent>
            </Card>
        );
    };

    // Render Boleto payment
    const renderBoletoPayment = () => {
        if (!asyncPaymentResult?.boleto) return null;

        const boleto = asyncPaymentResult.boleto;
        const boletoUrl = boleto.url || boleto.bank_slip_url || '';
        const barcode =
            boleto.digitable_line ||
            boleto.identification_field ||
            boleto.barcode ||
            boleto.bar_code ||
            '';

        return (
            <Card>
                <CardContent className="pt-6">
                    <BoletoPayment
                        boletoUrl={boletoUrl}
                        barcode={barcode}
                        dueDate={asyncPaymentResult.due_date || ''}
                        amount={formatPrice(asyncPaymentResult.amount)}
                    />
                </CardContent>
            </Card>
        );
    };

    // Render success state
    const renderSuccess = () => (
        <Card>
            <CardContent className="py-12">
                <div className="flex flex-col items-center justify-center gap-6 text-center">
                    <div className="relative">
                        <div className="animate-in zoom-in-50 duration-500 ease-out flex h-20 w-20 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                            <CheckCircle2 className="animate-in fade-in-0 zoom-in-75 delay-200 duration-500 h-10 w-10 text-green-600 dark:text-green-400" />
                        </div>
                    </div>

                    <div className="animate-in fade-in-0 slide-in-from-bottom-4 delay-300 duration-500 space-y-2">
                        <h3 className="text-xl font-semibold text-foreground">
                            {t('checkout.success.confirmed', { default: 'Payment Confirmed!' })}
                        </h3>
                        <p className="text-sm text-muted-foreground max-w-md">
                            {t('checkout.success.message', {
                                default:
                                    'Your purchase has been completed successfully. Your add-ons are now active.',
                            })}
                        </p>
                    </div>

                    <div className="animate-in fade-in-0 slide-in-from-bottom-4 delay-500 duration-500 flex flex-col gap-2 w-full max-w-xs">
                        <Button onClick={handleSuccessClose} size="lg" className="w-full">
                            {t('checkout.success.view_billing', { default: 'View Billing' })}
                            <ArrowRight className="ml-2 h-4 w-4" />
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => router.visit(admin.addons.index.url())}
                            className="w-full"
                        >
                            {t('checkout.success.continue_shopping', { default: 'Continue Shopping' })}
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    );

    // Don't render if redirecting
    if (items.length === 0 && checkoutState !== 'success' && checkoutState !== 'async-payment') {
        return null;
    }

    return (
        <>
            <Head title={t('checkout.page.title', { default: 'Checkout' })} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle>
                            <ShoppingCart className="mr-2 h-6 w-6" />
                            {checkoutState === 'success'
                                ? t('checkout.success.title', { default: 'Order Complete' })
                                : checkoutState === 'async-payment'
                                  ? asyncPaymentResult?.type === 'pix'
                                      ? t('checkout.payment.pix', { default: 'PIX Payment' })
                                      : t('checkout.payment.boleto', { default: 'Boleto Payment' })
                                  : checkoutState === 'asaas-card'
                                    ? t('checkout.payment.card', { default: 'Card Payment' })
                                    : t('checkout.page.title', { default: 'Checkout' })}
                        </PageTitle>
                        <PageDescription>
                            {checkoutState === 'success'
                                ? t('checkout.success.description', { default: 'Thank you for your purchase' })
                                : checkoutState === 'async-payment'
                                  ? t('checkout.page.description', {
                                        default: 'Complete your payment to finish the purchase',
                                    })
                                  : t('checkout.page.description', {
                                        default: 'Review your order and select a payment method',
                                    })}
                        </PageDescription>
                    </PageHeaderContent>
                    <PageHeaderActions>
                        {checkoutState !== 'success' && (
                            <Button variant="outline" onClick={handleBack}>
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                {t('common.back', { default: 'Back' })}
                            </Button>
                        )}
                    </PageHeaderActions>
                </PageHeader>

                <PageContent>
                    {/* Error message */}
                    {error && (
                        <div className="mb-6 rounded-md bg-destructive/10 p-4 text-sm text-destructive">
                            {error}
                        </div>
                    )}

                    {/* Success state */}
                    {checkoutState === 'success' && (
                        <div className="max-w-md mx-auto">{renderSuccess()}</div>
                    )}

                    {/* Async payment states */}
                    {checkoutState === 'async-payment' && asyncPaymentResult && (
                        <div className="max-w-lg mx-auto">
                            {asyncPaymentResult.type === 'pix'
                                ? renderPixPayment()
                                : renderBoletoPayment()}
                        </div>
                    )}

                    {/* Asaas card form */}
                    {checkoutState === 'asaas-card' && asaasCardResult && (
                        <div className="max-w-lg mx-auto">
                            <Card>
                                <CardContent className="pt-6">
                                    <AsaasCardForm
                                        purchaseId={asaasCardResult.purchase_id}
                                        amount={asaasCardResult.amount}
                                        formattedAmount={formatPrice(asaasCardResult.amount)}
                                        submitEndpoint={asaasCardPayment.url()}
                                        onSuccess={handleAsaasCardSuccess}
                                        onError={(err) => {
                                            setError(err);
                                            setCheckoutState('selecting');
                                        }}
                                    />
                                </CardContent>
                            </Card>
                        </div>
                    )}

                    {/* Main checkout view - Two column layout */}
                    {(checkoutState === 'selecting' || checkoutState === 'processing') && (
                        <div className="grid gap-8 lg:grid-cols-5">
                            {/* Left column - Cart items (2/5) */}
                            <div className="lg:col-span-2 space-y-6">
                                <CheckoutCartSection
                                    items={items}
                                    billingPeriod={period}
                                    onBillingPeriodChange={(p) => setPeriod(p as 'monthly' | 'yearly')}
                                    onRemoveItem={removeItem}
                                    onUpdateQuantity={updateQuantity}
                                    showBillingToggle={hasRecurringItems}
                                    yearlySavings={t('billing.price.yearly_savings', { default: 'Save 20%' })}
                                />
                            </div>

                            {/* Right column - Payment + Summary (3/5) */}
                            <div className="lg:col-span-3 space-y-6">
                                <CheckoutPaymentSection
                                    paymentMethod={selectedPaymentMethod}
                                    onPaymentMethodChange={setSelectedPaymentMethod}
                                    paymentConfig={paymentConfig}
                                    hasRecurring={hasRecurringItems}
                                    disabled={checkoutState === 'processing'}
                                />

                                <CheckoutSummarySection
                                    items={items}
                                    billingPeriod={period}
                                    currency={currency}
                                />

                                <CheckoutBenefitsSection items={items} />

                                <CheckoutPoliciesSection />

                                {/* Checkout button */}
                                <Button
                                    className="w-full"
                                    size="lg"
                                    onClick={handleCheckout}
                                    disabled={checkoutState === 'processing' || items.length === 0}
                                >
                                    {checkoutState === 'processing' ? (
                                        <>
                                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                            {t('checkout.payment.processing', { default: 'Processing...' })}
                                        </>
                                    ) : (
                                        <>
                                            {getCheckoutButtonLabel()}
                                            <ArrowRight className="ml-2 h-4 w-4" />
                                        </>
                                    )}
                                </Button>
                            </div>
                        </div>
                    )}
                </PageContent>
            </Page>
        </>
    );
}

function CheckoutPage(props: CheckoutPageProps) {
    return <CheckoutPageContent {...props} />;
}

CheckoutPage.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default CheckoutPage;
