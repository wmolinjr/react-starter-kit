import { useState } from 'react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ArrowLeft, CreditCard, Shield } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { AsaasCardForm } from '@/components/shared/billing/asaas-card-form';
import { cardPayment } from '@/routes/central/signup';
import type { PlanResource, PendingSignupResource } from '@/types/resources';

interface AsaasCardStepProps {
    signup: PendingSignupResource;
    plan: PlanResource;
    amount: number;
    onSuccess: (tenantUrl: string) => void;
    onBack: () => void;
}

export function AsaasCardStep({
    signup,
    plan,
    amount,
    onSuccess,
    onBack,
}: AsaasCardStepProps) {
    const { t } = useLaravelReactI18n();
    const [error, setError] = useState<string | null>(null);

    // Format price
    const formatPrice = (amountCents: number): string => {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency: plan.currency || 'BRL',
            minimumFractionDigits: amountCents % 100 === 0 ? 0 : 2,
            maximumFractionDigits: 2,
        }).format(amountCents / 100);
    };

    const handleSuccess = (result: { purchase_id?: string; tenant_url?: string; card?: { last_four?: string } }) => {
        // The backend returns tenant_url on success
        if (result.tenant_url) {
            onSuccess(result.tenant_url);
        }
    };

    const handleError = (errorMessage: string) => {
        setError(errorMessage);
    };

    return (
        <div className="space-y-6">
            {/* Header Card */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2 text-lg">
                        <CreditCard className="h-5 w-5" />
                        {t('signup.card.title', { default: 'Card Payment' })}
                    </CardTitle>
                    <CardDescription>
                        {t('signup.card.description', {
                            default: 'Enter your card details to complete the purchase',
                        })}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {/* Error display */}
                    {error && (
                        <div className="mb-4 rounded-md bg-destructive/10 p-3 text-sm text-destructive">
                            {error}
                        </div>
                    )}

                    {/* Card Form */}
                    <AsaasCardForm
                        purchaseId={signup.id}
                        amount={amount}
                        formattedAmount={formatPrice(amount)}
                        submitEndpoint={cardPayment.url({ signup: signup.id })}
                        onSuccess={handleSuccess}
                        onError={handleError}
                        defaultHolder={{
                            name: signup.name,
                            email: signup.email,
                        }}
                    />
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

            {/* Back button */}
            <div className="flex gap-4">
                <Button type="button" variant="outline" onClick={onBack} className="flex-1">
                    <ArrowLeft className="mr-2 h-4 w-4" />
                    {t('common.back', { default: 'Back' })}
                </Button>
            </div>
        </div>
    );
}
