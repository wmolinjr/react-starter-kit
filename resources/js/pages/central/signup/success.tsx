import { useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { CheckCircle, Loader2, ArrowRight, Rocket, AlertCircle } from 'lucide-react';
import AppLogoIcon from '@/components/shared/branding/app-logo-icon';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import type { PendingSignupResource } from '@/types/resources';

interface SuccessPageProps {
    signup?: PendingSignupResource | null;
    tenantUrl?: string | null;
    tenantName?: string | null;
}

export default function SuccessPage({ signup, tenantUrl, tenantName }: SuccessPageProps) {
    const { t } = useLaravelReactI18n();

    // Auto-redirect after 3 seconds if tenant URL is available
    useEffect(() => {
        if (tenantUrl) {
            const timer = setTimeout(() => {
                window.location.href = tenantUrl;
            }, 3000);

            return () => clearTimeout(timer);
        }
    }, [tenantUrl]);

    // If signup is still processing, show loading state
    if (signup && signup.status === 'processing') {
        return (
            <>
                <Head title={t('signup.success.processing_title', { default: 'Processing...' })} />
                <div className="bg-background flex min-h-svh flex-col items-center justify-center p-6">
                    <div className="mx-auto w-full max-w-md text-center">
                        <AppLogoIcon className="text-foreground mx-auto mb-6 size-12" />
                        <Loader2 className="text-primary mx-auto mb-4 h-12 w-12 animate-spin" />
                        <h1 className="text-xl font-semibold">
                            {t('signup.success.processing_title', { default: 'Setting up your workspace...' })}
                        </h1>
                        <p className="text-muted-foreground mt-2">
                            {t('signup.success.processing_description', {
                                default: 'This may take a few moments. Please wait.',
                            })}
                        </p>
                    </div>
                </div>
            </>
        );
    }

    // If no signup or failed, show error state
    if (!signup || signup.status === 'failed') {
        return (
            <>
                <Head title={t('signup.success.error_title', { default: 'Error' })} />
                <div className="bg-background flex min-h-svh flex-col items-center justify-center p-6">
                    <div className="mx-auto w-full max-w-md text-center">
                        <AppLogoIcon className="text-foreground mx-auto mb-6 size-12" />
                        <AlertCircle className="mx-auto mb-4 h-12 w-12 text-red-500" />
                        <h1 className="text-xl font-semibold">
                            {t('signup.success.error_title', { default: 'Something went wrong' })}
                        </h1>
                        <p className="text-muted-foreground mt-2">
                            {signup?.failure_reason ||
                                t('signup.success.error_description', {
                                    default: 'We could not complete your signup. Please try again.',
                                })}
                        </p>
                        <Button className="mt-6" onClick={() => router.visit('/signup')}>
                            {t('signup.success.try_again', { default: 'Try Again' })}
                        </Button>
                    </div>
                </div>
            </>
        );
    }

    // Success state
    return (
        <>
            <Head title={t('signup.success.title', { default: 'Welcome!' })} />
            <div className="bg-background flex min-h-svh flex-col items-center justify-center p-6">
                <div className="mx-auto w-full max-w-md">
                    <Card className="overflow-hidden">
                        {/* Success Header */}
                        <div className="bg-gradient-to-r from-green-500 to-emerald-600 p-8 text-center text-white">
                            <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-white/20">
                                <CheckCircle className="h-10 w-10" />
                            </div>
                            <h1 className="text-2xl font-bold">
                                {t('signup.success.title', { default: 'Welcome!' })}
                            </h1>
                            <p className="mt-2 text-green-100">
                                {t('signup.success.subtitle', {
                                    default: 'Your workspace is ready to go.',
                                })}
                            </p>
                        </div>

                        <CardContent className="p-6">
                            {/* Workspace Info */}
                            <div className="mb-6 rounded-lg border bg-muted/30 p-4 text-center">
                                <p className="text-muted-foreground text-sm">
                                    {t('signup.success.workspace_name', { default: 'Your workspace' })}
                                </p>
                                <p className="text-lg font-semibold">{tenantName || signup.workspace_name}</p>
                            </div>

                            {/* Auto-redirect notice */}
                            {tenantUrl && (
                                <p className="text-muted-foreground mb-6 text-center text-sm">
                                    {t('signup.success.redirecting', {
                                        default: 'Redirecting automatically in a few seconds...',
                                    })}
                                </p>
                            )}

                            {/* CTA */}
                            {tenantUrl && (
                                <Button
                                    className="w-full"
                                    size="lg"
                                    onClick={() => (window.location.href = tenantUrl)}
                                >
                                    <Rocket className="mr-2 h-5 w-5" />
                                    {t('signup.success.go_to_workspace', { default: 'Go to Workspace' })}
                                    <ArrowRight className="ml-2 h-5 w-5" />
                                </Button>
                            )}

                            {/* Email Notice */}
                            <p className="text-muted-foreground mt-4 text-center text-sm">
                                {t('signup.success.email_sent', {
                                    default: 'A confirmation email was sent to',
                                })}{' '}
                                <strong>{signup.email}</strong>
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
