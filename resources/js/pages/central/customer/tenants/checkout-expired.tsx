import {
    Page,
    PageContent,
} from '@/components/shared/layout/page';
import CustomerLayout from '@/layouts/customer-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import customer from '@/routes/central/account';
import { Head, Link } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Clock, RefreshCcw, ArrowLeft } from 'lucide-react';
import { type ReactElement } from 'react';

function CheckoutExpired() {
    const { t } = useLaravelReactI18n();

    return (
        <>
            <Head title={t('billing.page.checkout_expired')} />

            <Page>
                <PageContent>
                    <div className="max-w-lg mx-auto py-12">
                        <Card>
                            <CardContent className="pt-6">
                                <div className="text-center space-y-6">
                                    {/* Expired Icon */}
                                    <div className="flex justify-center">
                                        <div className="w-20 h-20 rounded-full bg-amber-100 flex items-center justify-center">
                                            <Clock className="h-12 w-12 text-amber-600" />
                                        </div>
                                    </div>

                                    {/* Expired Message */}
                                    <div className="space-y-2">
                                        <h1 className="text-2xl font-bold">
                                            {t('billing.page.checkout_expired')}
                                        </h1>
                                        <p className="text-muted-foreground">
                                            {t('billing.page.checkout_expired_description')}
                                        </p>
                                    </div>

                                    {/* Actions */}
                                    <div className="flex flex-col gap-3 pt-4">
                                        <Button asChild size="lg">
                                            <Link href={customer.tenants.create.url()}>
                                                <RefreshCcw className="mr-2 h-4 w-4" />
                                                {t('customer.workspace.start_again')}
                                            </Link>
                                        </Button>
                                        <Button asChild variant="outline">
                                            <Link href={customer.dashboard.url()}>
                                                <ArrowLeft className="mr-2 h-4 w-4" />
                                                {t('customer.dashboard.go_to_dashboard')}
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </PageContent>
            </Page>
        </>
    );
}

CheckoutExpired.layout = (page: ReactElement) => <CustomerLayout>{page}</CustomerLayout>;

export default CheckoutExpired;
