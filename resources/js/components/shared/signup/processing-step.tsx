import { useEffect, useState } from 'react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Loader2, CheckCircle } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { status as checkStatus } from '@/routes/central/signup';
import type { PendingSignupResource } from '@/types/resources';

interface ProcessingStepProps {
    signup: PendingSignupResource;
    onComplete: (tenantUrl: string) => void;
}

export function ProcessingStep({ signup, onComplete }: ProcessingStepProps) {
    const { t } = useLaravelReactI18n();
    const [isCompleted, setIsCompleted] = useState(false);
    const [tenantUrl, setTenantUrl] = useState<string | null>(null);

    // Poll for payment status
    useEffect(() => {
        let pollCount = 0;
        const maxPolls = 120; // 10 minutes with 5s interval
        const pollInterval = 5000;

        const poll = async () => {
            try {
                const response = await fetch(checkStatus.url({ signup: signup.id }), {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) return;

                const data = await response.json();

                if (data.is_completed && data.tenant_url) {
                    setIsCompleted(true);
                    setTenantUrl(data.tenant_url);

                    // Auto-redirect after showing success
                    setTimeout(() => {
                        onComplete(data.tenant_url);
                    }, 2000);

                    return;
                }

                if (data.status === 'failed') {
                    // Handle failure - reload page to show error
                    window.location.reload();
                    return;
                }

                // Continue polling
                pollCount++;
                if (pollCount < maxPolls) {
                    setTimeout(poll, pollInterval);
                }
            } catch (error) {
                console.error('Failed to check payment status', error);
                // Retry on error
                pollCount++;
                if (pollCount < maxPolls) {
                    setTimeout(poll, pollInterval);
                }
            }
        };

        poll();

        return () => {
            pollCount = maxPolls; // Stop polling on unmount
        };
    }, [signup.id, onComplete]);

    if (isCompleted) {
        return (
            <Card className="border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950">
                <CardContent className="flex flex-col items-center py-12">
                    <CheckCircle className="mb-4 h-16 w-16 text-green-600 dark:text-green-400" />
                    <h2 className="text-xl font-semibold text-green-800 dark:text-green-200">
                        {t('signup.processing.payment_confirmed', { default: 'Payment Confirmed!' })}
                    </h2>
                    <p className="text-green-700 dark:text-green-300 mt-2">
                        {t('signup.processing.redirecting', {
                            default: 'Redirecting to your workspace...',
                        })}
                    </p>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader className="text-center">
                <CardTitle className="text-2xl">
                    {t('signup.processing.title', { default: 'Processing Payment' })}
                </CardTitle>
                <CardDescription>
                    {signup.payment_method === 'pix'
                        ? t('signup.processing.pix_description', {
                              default: 'Complete your PIX payment to proceed',
                          })
                        : t('signup.processing.boleto_description', {
                              default: 'Complete your Boleto payment to proceed',
                          })}
                </CardDescription>
            </CardHeader>

            <CardContent className="flex flex-col items-center py-8">
                <Loader2 className="text-primary mb-4 h-12 w-12 animate-spin" />
                <p className="text-muted-foreground text-center">
                    {t('signup.processing.waiting', {
                        default: 'Waiting for payment confirmation...',
                    })}
                </p>
                <p className="text-muted-foreground mt-2 text-center text-sm">
                    {t('signup.processing.auto_redirect', {
                        default: 'You will be redirected automatically once payment is confirmed.',
                    })}
                </p>
            </CardContent>
        </Card>
    );
}
