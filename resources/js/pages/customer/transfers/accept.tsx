import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AuthLayout from '@/layouts/auth-layout';
import { Form, Head } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Building2, Clock, ArrowRightLeft, User } from 'lucide-react';
import { Spinner } from '@/components/ui/spinner';

interface Transfer {
    token: string;
    to_email: string;
    transfer_fee: number;
    expires_at: string;
    notes: string | null;
}

interface Tenant {
    name: string;
    domain: string;
    plan: string | null;
}

interface FromCustomer {
    name: string;
}

interface TransferAcceptProps {
    transfer: Transfer;
    tenant: Tenant;
    from_customer: FromCustomer;
}

export default function TransferAccept({ transfer, tenant, from_customer }: TransferAcceptProps) {
    const { t } = useLaravelReactI18n();

    const expiresAt = new Date(transfer.expires_at);
    const now = new Date();
    const daysRemaining = Math.ceil((expiresAt.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));

    return (
        <AuthLayout
            title={t('customer.workspace_transfer')}
            description={t('customer.you_have_been_invited')}
        >
            <Head title={t('customer.accept_transfer')} />

            <div className="space-y-6">
                {/* Transfer Info Card */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Building2 className="h-5 w-5" />
                            {tenant.name}
                        </CardTitle>
                        <CardDescription>
                            {tenant.domain}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex items-center gap-3 text-sm">
                            <User className="h-4 w-4 text-muted-foreground" />
                            <span className="text-muted-foreground">{t('customer.from')}:</span>
                            <span className="font-medium">{from_customer.name}</span>
                        </div>

                        {tenant.plan && (
                            <div className="flex items-center gap-3 text-sm">
                                <ArrowRightLeft className="h-4 w-4 text-muted-foreground" />
                                <span className="text-muted-foreground">{t('customer.plan')}:</span>
                                <span className="font-medium">{tenant.plan}</span>
                            </div>
                        )}

                        <div className="flex items-center gap-3 text-sm">
                            <Clock className="h-4 w-4 text-muted-foreground" />
                            <span className="text-muted-foreground">{t('customer.expires')}:</span>
                            <span className="font-medium">
                                {expiresAt.toLocaleDateString()} ({daysRemaining} {t('customer.days_remaining')})
                            </span>
                        </div>

                        {transfer.notes && (
                            <div className="p-3 bg-muted rounded-lg text-sm">
                                <p className="text-muted-foreground mb-1">{t('customer.notes')}:</p>
                                <p>{transfer.notes}</p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Accept Form */}
                <Form
                    action={`/account/transfers/${transfer.token}/confirm`}
                    method="post"
                    className="space-y-4"
                >
                    {({ processing }) => (
                        <>
                            <p className="text-sm text-muted-foreground">
                                {t('customer.accept_transfer_description')}
                            </p>

                            <div className="flex gap-4">
                                <Button
                                    type="submit"
                                    className="flex-1"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    {t('customer.accept_transfer')}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>

                <p className="text-xs text-center text-muted-foreground">
                    {t('customer.accept_transfer_disclaimer')}
                </p>
            </div>
        </AuthLayout>
    );
}
