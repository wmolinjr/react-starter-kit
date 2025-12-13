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
import { CheckCircle2, Building2, ArrowRight } from 'lucide-react';
import { type ReactElement } from 'react';

interface SignupData {
    id: string;
    workspace_name: string;
    status: string;
}

interface CheckoutSuccessProps {
    signup: SignupData;
}

function CheckoutSuccess({ signup }: CheckoutSuccessProps) {
    const { t } = useLaravelReactI18n();

    return (
        <>
            <Head title={t('billing.page.payment_successful')} />

            <Page>
                <PageContent>
                    <div className="max-w-lg mx-auto py-12">
                        <Card>
                            <CardContent className="pt-6">
                                <div className="text-center space-y-6">
                                    {/* Success Icon */}
                                    <div className="flex justify-center">
                                        <div className="w-20 h-20 rounded-full bg-green-100 flex items-center justify-center">
                                            <CheckCircle2 className="h-12 w-12 text-green-600" />
                                        </div>
                                    </div>

                                    {/* Success Message */}
                                    <div className="space-y-2">
                                        <h1 className="text-2xl font-bold">
                                            {t('billing.page.payment_successful')}
                                        </h1>
                                        <p className="text-muted-foreground">
                                            {t('customer.workspace.created_successfully')}
                                        </p>
                                    </div>

                                    {/* Workspace Info */}
                                    <div className="flex items-center justify-center gap-3 p-4 bg-muted rounded-lg">
                                        <Building2 className="h-6 w-6 text-muted-foreground" />
                                        <div className="text-left">
                                            <p className="font-medium">{signup.workspace_name}</p>
                                            <p className="text-sm text-muted-foreground">
                                                {t('customer.workspace.ready_to_use')}
                                            </p>
                                        </div>
                                    </div>

                                    {/* Actions */}
                                    <div className="flex flex-col gap-3 pt-4">
                                        <Button asChild size="lg">
                                            <Link href={customer.tenants.index.url()}>
                                                {t('customer.workspace.view_workspaces')}
                                                <ArrowRight className="ml-2 h-4 w-4" />
                                            </Link>
                                        </Button>
                                        <Button asChild variant="outline">
                                            <Link href={customer.dashboard.url()}>
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

CheckoutSuccess.layout = (page: ReactElement) => <CustomerLayout>{page}</CustomerLayout>;

export default CheckoutSuccess;
