import {
    Page,
    PageHeader,
    PageHeaderContent,
    PageHeaderActions,
    PageTitle,
    PageDescription,
    PageContent,
} from '@/components/shared/layout/page';
import CustomerLayout from '@/layouts/customer-layout';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import customer from '@/routes/central/account';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { CreditCard, Plus, Star, Trash2 } from 'lucide-react';
import { type ReactElement } from 'react';

interface PaymentMethod {
    id: string;
    type: 'card' | 'pix' | 'boleto' | 'bank_transfer';
    provider: string;
    brand: string | null;
    last4: string | null;
    exp_month: number | null;
    exp_year: number | null;
    bank_name: string | null;
    is_default: boolean;
    is_verified: boolean;
    is_expired: boolean;
    display_label: string;
    expiration_display: string | null;
    created_at: string;
}

interface PaymentMethodsIndexProps {
    paymentMethods: PaymentMethod[];
    status?: string;
}

function PaymentMethodsIndex({ paymentMethods, status }: PaymentMethodsIndexProps) {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('customer.dashboard.title'), href: customer.dashboard.url() },
        { title: t('customer.payment.methods'), href: customer.paymentMethods.index.url() },
    ];
    useSetBreadcrumbs(breadcrumbs);

    const getPaymentIcon = (type: string) => {
        switch (type) {
            case 'pix':
                return <span className="text-lg">P</span>;
            case 'boleto':
                return <span className="text-lg">B</span>;
            case 'bank_transfer':
                return <span className="text-lg">$</span>;
            default:
                return <CreditCard className="h-6 w-6" />;
        }
    };

    return (
        <>
            <Head title={t('customer.payment.methods')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={CreditCard}>
                            {t('customer.payment.methods')}
                        </PageTitle>
                        <PageDescription>
                            {t('customer.payment.methods_description')}
                        </PageDescription>
                    </PageHeaderContent>
                    <PageHeaderActions>
                        <Button asChild>
                            <Link href={customer.paymentMethods.create.url()}>
                                <Plus className="mr-2 h-4 w-4" />
                                {t('customer.payment.add_method')}
                            </Link>
                        </Button>
                    </PageHeaderActions>
                </PageHeader>

                <PageContent>
                    {status && (
                        <div className="rounded-lg bg-green-50 p-4 text-green-700">
                            {status === 'payment-method-added' && t('customer.payment.method_added')}
                            {status === 'payment-method-removed' && t('customer.payment.method_removed')}
                            {status === 'default-payment-method-updated' && t('customer.payment.default_updated')}
                        </div>
                    )}

                    {paymentMethods.length === 0 ? (
                        <Card>
                            <CardContent className="flex flex-col items-center justify-center py-16">
                                <CreditCard className="h-16 w-16 text-muted-foreground/50 mb-4" />
                                <h3 className="text-lg font-medium mb-2">
                                    {t('customer.payment.no_methods')}
                                </h3>
                                <p className="text-muted-foreground text-center mb-4">
                                    {t('customer.payment.no_methods_description')}
                                </p>
                                <Button asChild>
                                    <Link href={customer.paymentMethods.create.url()}>
                                        <Plus className="mr-2 h-4 w-4" />
                                        {t('customer.payment.add_first')}
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    ) : (
                        <div className="space-y-4">
                            {paymentMethods.map((method) => (
                                <Card key={method.id} className={method.is_expired ? 'opacity-60' : ''}>
                                    <CardContent className="flex items-center justify-between py-4">
                                        <div className="flex items-center gap-4">
                                            <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-muted">
                                                {getPaymentIcon(method.type)}
                                            </div>
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <p className="font-medium">
                                                        {method.display_label}
                                                    </p>
                                                    {method.is_default && (
                                                        <Badge variant="secondary" className="text-xs">
                                                            <Star className="mr-1 h-3 w-3" />
                                                            {t('customer.payment.default')}
                                                        </Badge>
                                                    )}
                                                    {method.is_expired && (
                                                        <Badge variant="destructive" className="text-xs">
                                                            {t('customer.status.expired')}
                                                        </Badge>
                                                    )}
                                                </div>
                                                {method.expiration_display && (
                                                    <p className="text-sm text-muted-foreground">
                                                        {t('customer.status.expires')} {method.expiration_display}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {!method.is_default && !method.is_expired && (
                                                <Form
                                                    {...customer.paymentMethods.default.form(method.id)}
                                                >
                                                    {({ processing }) => (
                                                        <Button
                                                            type="submit"
                                                            variant="outline"
                                                            size="sm"
                                                            disabled={processing}
                                                        >
                                                            {t('customer.payment.set_default')}
                                                        </Button>
                                                    )}
                                                </Form>
                                            )}
                                            <Form
                                                {...customer.paymentMethods.destroy.form(method.id)}
                                            >
                                                {({ processing }) => (
                                                    <Button
                                                        type="submit"
                                                        variant="ghost"
                                                        size="sm"
                                                        disabled={processing}
                                                        className="text-destructive hover:text-destructive"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                )}
                                            </Form>
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}
                </PageContent>
            </Page>
        </>
    );
}

PaymentMethodsIndex.layout = (page: ReactElement) => <CustomerLayout>{page}</CustomerLayout>;

export default PaymentMethodsIndex;
