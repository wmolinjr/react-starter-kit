import { useState, useEffect, useCallback } from 'react';
import { router, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ArrowLeft, Shield, Lock, CheckCircle, AlertTriangle } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import InputError from '@/components/shared/feedback/input-error';
import {
    PaymentMethodSelector,
    type PaymentMethod,
} from '@/components/shared/billing/payment-method-selector';
import { unformatCpfCnpj, isValidCpfCnpjLength } from '@/components/shared/billing/cpf-cnpj-input';
import { PriceDisplay } from '@/components/shared/billing/primitives/price-display';
import { process as processPayment } from '@/routes/central/signup/payment';
import type { PlanResource, PendingSignupResource, PaymentConfigResource } from '@/types/resources';
import type { PageProps, PaymentResult } from '@/types';

interface PaymentStepProps {
    signup: PendingSignupResource;
    plan: PlanResource;
    billingPeriod: 'monthly' | 'yearly';
    paymentConfig: PaymentConfigResource;
    onSuccess: (result: PaymentResult) => void;
    onBack: () => void;
}

export function PaymentStep({
    signup,
    plan,
    billingPeriod,
    paymentConfig,
    onSuccess,
    onBack,
}: PaymentStepProps) {
    const { t } = useLaravelReactI18n();
    const { props } = usePage<PageProps>();
    const { errors = {} } = props;
    const [isLoading, setIsLoading] = useState(false);
    const [paymentMethod, setPaymentMethod] = useState<PaymentMethod>('card');
    const [cpfCnpj, setCpfCnpj] = useState('');

    // Check if current method needs CPF/CNPJ
    const needsCpfCnpj = paymentMethod === 'pix' || paymentMethod === 'boleto';
    const cpfCnpjValid = !needsCpfCnpj || isValidCpfCnpjLength(cpfCnpj);

    // Watch for flash data changes (paymentResult from server)
    const handleFlashSuccess = useCallback(() => {
        const paymentResult = props.flash?.paymentResult as PaymentResult | undefined;
        if (paymentResult) {
            onSuccess(paymentResult);
        }
    }, [props.flash?.paymentResult, onSuccess]);

    useEffect(() => {
        handleFlashSuccess();
    }, [handleFlashSuccess]);

    // Calculate price based on billing period
    const monthlyPrice = plan.price;
    const yearlyPrice = monthlyPrice * 12 * 0.8; // 20% discount
    const totalPrice = billingPeriod === 'yearly' ? yearlyPrice : monthlyPrice;

    // Get available payment methods from config (no fallback - must be configured)
    const availableMethods: PaymentMethod[] = paymentConfig.available_methods ?? [];
    const hasPaymentMethods = availableMethods.length > 0;

    // Get first error message for display
    const errorMessage = errors.payment || errors.payment_method || errors.cpf_cnpj || Object.values(errors)[0];

    const handleSubmit = () => {
        // Client-side validation for CPF/CNPJ
        if (needsCpfCnpj && !cpfCnpjValid) {
            return;
        }

        setIsLoading(true);

        const data: { payment_method: string; cpf_cnpj?: string } = {
            payment_method: paymentMethod,
        };

        // Include CPF/CNPJ for PIX and Boleto (raw digits)
        if (needsCpfCnpj && cpfCnpj) {
            data.cpf_cnpj = unformatCpfCnpj(cpfCnpj);
        }

        router.post(processPayment.url({ signup: signup.id }), data, {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                setIsLoading(false);
            },
        });
    };

    return (
        <div className="space-y-6">
            {/* Order Summary */}
            <Card>
                <CardHeader>
                    <CardTitle className="text-lg">
                        {t('signup.payment.summary', { default: 'Order Summary' })}
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="font-medium">{plan.name}</p>
                            <p className="text-muted-foreground text-sm">
                                {billingPeriod === 'yearly'
                                    ? t('signup.payment.billed_yearly', { default: 'Billed yearly' })
                                    : t('signup.payment.billed_monthly', { default: 'Billed monthly' })}
                            </p>
                        </div>
                        <PriceDisplay
                            amount={totalPrice}
                            currency={plan.currency}
                            period={billingPeriod === 'yearly' ? 'yearly' : 'monthly'}
                            size="lg"
                        />
                    </div>

                    {billingPeriod === 'yearly' && (
                        <div className="bg-green-50 dark:bg-green-950/30 text-green-700 dark:text-green-300 rounded-md p-3 text-sm">
                            <CheckCircle className="mr-2 inline h-4 w-4" />
                            {t('signup.payment.yearly_savings', {
                                default: 'You save 20% with yearly billing',
                            })}
                        </div>
                    )}

                    <div className="border-t pt-4">
                        <div className="flex items-center justify-between font-medium">
                            <span>{t('signup.payment.total', { default: 'Total' })}</span>
                            <PriceDisplay
                                amount={totalPrice}
                                currency={plan.currency}
                                period={billingPeriod === 'yearly' ? 'yearly' : 'monthly'}
                                size="lg"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Payment Method Selection */}
            <Card>
                <CardHeader>
                    <CardTitle className="text-lg">
                        {t('signup.payment.method', { default: 'Payment Method' })}
                    </CardTitle>
                    <CardDescription>
                        {t('signup.payment.method_description', {
                            default: 'Choose how you want to pay',
                        })}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {hasPaymentMethods ? (
                        <PaymentMethodSelector
                            value={paymentMethod}
                            onChange={setPaymentMethod}
                            availableMethods={availableMethods}
                            disabled={isLoading}
                            cpfCnpj={cpfCnpj}
                            onCpfCnpjChange={setCpfCnpj}
                            cpfCnpjError={errors.cpf_cnpj}
                            requireCpfCnpj={true}
                        />
                    ) : (
                        <div className="flex items-center gap-3 rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-950/30">
                            <AlertTriangle className="h-5 w-5 shrink-0 text-yellow-600 dark:text-yellow-400" />
                            <div className="text-sm">
                                <p className="font-medium text-yellow-800 dark:text-yellow-200">
                                    {t('signup.payment.no_methods_title', {
                                        default: 'Payment methods not configured',
                                    })}
                                </p>
                                <p className="text-yellow-700 dark:text-yellow-300">
                                    {t('signup.payment.no_methods_description', {
                                        default: 'Please contact support to complete your purchase.',
                                    })}
                                </p>
                            </div>
                        </div>
                    )}
                    <InputError message={errorMessage} className="mt-2" />
                </CardContent>
            </Card>

            {/* Security Notice */}
            <div className="bg-muted/50 flex items-center gap-3 rounded-lg p-4">
                <Shield className="text-primary h-5 w-5 shrink-0" />
                <div className="text-sm">
                    <p className="font-medium">
                        {t('signup.payment.security_title', { default: 'Secure Checkout' })}
                    </p>
                    <p className="text-muted-foreground">
                        {t('signup.payment.security_description', {
                            default: 'Your payment is secured with 256-bit SSL encryption',
                        })}
                    </p>
                </div>
            </div>

            {/* Actions */}
            <div className="flex gap-4">
                <Button type="button" variant="outline" onClick={onBack} className="flex-1">
                    <ArrowLeft className="mr-2 h-4 w-4" />
                    {t('common.back', { default: 'Back' })}
                </Button>
                <Button onClick={handleSubmit} className="flex-1" disabled={isLoading || !hasPaymentMethods || !cpfCnpjValid}>
                    {isLoading ? (
                        <>
                            <Spinner className="mr-2" />
                            {t('signup.payment.processing', { default: 'Processing...' })}
                        </>
                    ) : (
                        <>
                            <Lock className="mr-2 h-4 w-4" />
                            {paymentMethod === 'card'
                                ? t('signup.payment.pay_with_card', { default: 'Pay with Card' })
                                : paymentMethod === 'pix'
                                  ? t('signup.payment.generate_pix', { default: 'Generate PIX' })
                                  : t('signup.payment.generate_boleto', { default: 'Generate Boleto' })}
                        </>
                    )}
                </Button>
            </div>
        </div>
    );
}
