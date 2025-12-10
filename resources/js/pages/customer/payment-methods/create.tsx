import CustomerLayout from '@/layouts/customer-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Head, router } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { CreditCard, Lock } from 'lucide-react';
import { useState } from 'react';

interface PaymentMethodCreateProps {
    intent: {
        client_secret: string;
    };
    stripeKey: string;
}

export default function PaymentMethodCreate({ intent }: PaymentMethodCreateProps) {
    const { t } = useLaravelReactI18n();
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // Note: In a real implementation, you would use @stripe/react-stripe-js
    // This is a simplified placeholder that shows the structure
    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setError(null);

        // In production, this would be:
        // 1. Create a Stripe Elements instance
        // 2. Confirm the SetupIntent with the card details
        // 3. Submit the payment method ID to the backend

        // For now, show a message that Stripe integration is needed
        setError(t('customer.stripe_integration_required'));
        setProcessing(false);
    };

    return (
        <CustomerLayout
            breadcrumbs={[
                { title: t('customer.dashboard'), href: '/account' },
                { title: t('customer.payment_methods'), href: '/account/payment-methods' },
                { title: t('customer.add_payment_method'), href: '/account/payment-methods/create' },
            ]}
        >
            <Head title={t('customer.add_payment_method')} />

            <div className="space-y-6 max-w-2xl">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        {t('customer.add_payment_method')}
                    </h1>
                    <p className="text-muted-foreground">
                        {t('customer.add_payment_method_description')}
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <CreditCard className="h-5 w-5" />
                            {t('customer.card_details')}
                        </CardTitle>
                        <CardDescription>
                            {t('customer.card_details_description')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            {/* Stripe Elements would go here */}
                            <div className="p-4 border rounded-lg bg-muted/50">
                                <p className="text-sm text-muted-foreground text-center">
                                    {t('customer.stripe_card_element_placeholder')}
                                </p>
                                <p className="text-xs text-muted-foreground text-center mt-2">
                                    Client Secret: {intent.client_secret.substring(0, 20)}...
                                </p>
                            </div>

                            {error && (
                                <div className="p-4 bg-destructive/10 text-destructive rounded-lg">
                                    {error}
                                </div>
                            )}

                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Lock className="h-4 w-4" />
                                {t('customer.secure_payment')}
                            </div>

                            <div className="flex gap-4">
                                <Button type="submit" disabled={processing}>
                                    {processing ? t('common.processing') : t('customer.add_card')}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => router.visit('/account/payment-methods')}
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
                                <p className="font-medium">{t('customer.secure_processing')}</p>
                                <p>{t('customer.secure_processing_description')}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </CustomerLayout>
    );
}
