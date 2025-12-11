import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AdminLayout from '@/layouts/tenant/admin-layout';
import admin from '@/routes/tenant/admin';
import { Head, Link } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { CheckCircle, CreditCard, LayoutGrid, Sparkles } from 'lucide-react';
import { type ReactElement } from 'react';

interface Props {
    plan?: string;
    message?: string;
}

function BillingSuccess({ plan, message }: Props) {
    const { t } = useLaravelReactI18n();

    return (
        <>
            <Head title={t('tenant.billing.payment_confirmed')} />

            <div className="flex items-center justify-center min-h-[60vh]">
                <Card className="animate-in fade-in-0 zoom-in-95 duration-500 max-w-md w-full text-center">
                    <CardHeader>
                        {/* Animated success icon with ripple effect */}
                        <div className="relative mx-auto mb-4">
                            <div className="animate-in zoom-in-50 duration-700 ease-out flex h-20 w-20 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                                <CheckCircle className="animate-in fade-in-0 zoom-in-75 delay-300 duration-500 h-12 w-12 text-green-600 dark:text-green-400" />
                            </div>
                            {/* Sparkle decorations */}
                            <Sparkles className="animate-in fade-in-0 delay-500 duration-700 absolute -top-2 -right-2 h-5 w-5 text-yellow-500" />
                            <Sparkles className="animate-in fade-in-0 delay-700 duration-700 absolute -bottom-1 -left-3 h-4 w-4 text-yellow-400" />
                            {/* Ripple effect */}
                            <div
                                className="animate-ping absolute inset-0 rounded-full bg-green-400/20 dark:bg-green-400/10"
                                style={{ animationDuration: '1.5s', animationIterationCount: '2' }}
                            />
                        </div>
                        <CardTitle className="animate-in fade-in-0 slide-in-from-bottom-4 delay-200 duration-500 text-2xl">
                            {t('tenant.billing.payment_confirmed')}
                        </CardTitle>
                        <CardDescription className="animate-in fade-in-0 slide-in-from-bottom-4 delay-300 duration-500">
                            {message || t('tenant.billing.subscription_activated', { plan: plan || '' })}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <p className="animate-in fade-in-0 slide-in-from-bottom-4 delay-400 duration-500 text-sm text-muted-foreground">
                            {t('tenant.billing.success_message')}
                        </p>

                        <div className="animate-in fade-in-0 slide-in-from-bottom-4 delay-500 duration-500 flex flex-col gap-2 pt-4">
                            <Button asChild>
                                <Link href={admin.dashboard.url()}>
                                    <LayoutGrid className="mr-2 h-4 w-4" />
                                    {t('tenant.billing.go_to_dashboard')}
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={admin.billing.index.url()}>
                                    <CreditCard className="mr-2 h-4 w-4" />
                                    {t('tenant.billing.view_subscription')}
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

BillingSuccess.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default BillingSuccess;
