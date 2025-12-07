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
import { CheckCircle, CreditCard, LayoutGrid } from 'lucide-react';

interface Props {
    plan?: string;
    message?: string;
}

export default function BillingSuccess({ plan, message }: Props) {
    const { t } = useLaravelReactI18n();

    return (
        <AdminLayout>
            <Head title={t('tenant.billing.payment_confirmed')} />

            <div className="flex items-center justify-center min-h-[60vh]">
                <Card className="max-w-md w-full text-center">
                    <CardHeader>
                        <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-100 dark:bg-green-900">
                            <CheckCircle className="h-10 w-10 text-green-600 dark:text-green-400" />
                        </div>
                        <CardTitle className="text-2xl">
                            {t('tenant.billing.payment_confirmed')}
                        </CardTitle>
                        <CardDescription>
                            {message || t('tenant.billing.subscription_activated', { plan: plan || '' })}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <p className="text-sm text-muted-foreground">
                            {t('tenant.billing.success_message')}
                        </p>

                        <div className="flex flex-col gap-2 pt-4">
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
        </AdminLayout>
    );
}
