import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import AuthLayout from '@/layouts/auth-layout';
import customer from '@/routes/central/account';
import { Head, Link } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { XCircle } from 'lucide-react';

interface TransferInvalidProps {
    status: string;
}

export default function TransferInvalid({ status }: TransferInvalidProps) {
    const { t } = useLaravelReactI18n();

    const getStatusMessage = (status: string) => {
        switch (status) {
            case 'completed':
                return t('customer.transfer.already_completed');
            case 'cancelled':
                return t('customer.transfer.cancelled');
            case 'rejected':
                return t('customer.transfer.rejected');
            case 'expired':
                return t('customer.transfer.has_expired');
            default:
                return t('customer.transfer.not_available');
        }
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'completed':
                return <Badge variant="default">{t('customer.status.completed')}</Badge>;
            case 'cancelled':
                return <Badge variant="outline">{t('customer.status.cancelled')}</Badge>;
            case 'rejected':
                return <Badge variant="destructive">{t('customer.status.rejected')}</Badge>;
            case 'expired':
                return <Badge variant="secondary">{t('customer.status.expired')}</Badge>;
            default:
                return <Badge variant="outline">{status}</Badge>;
        }
    };

    return (
        <AuthLayout
            title={t('customer.transfer.unavailable')}
            description={getStatusMessage(status)}
        >
            <Head title={t('customer.transfer.unavailable')} />

            <div className="flex flex-col items-center text-center space-y-6">
                <div className="rounded-full bg-destructive/10 p-4">
                    <XCircle className="h-8 w-8 text-destructive" />
                </div>

                <div className="space-y-2">
                    <div className="flex items-center justify-center gap-2">
                        <h2 className="text-lg font-semibold">
                            {t('customer.transfer.status')}:
                        </h2>
                        {getStatusBadge(status)}
                    </div>
                    <p className="text-sm text-muted-foreground max-w-sm">
                        {getStatusMessage(status)}
                    </p>
                </div>

                <Button asChild>
                    <Link href={customer.dashboard.url()}>
                        {t('customer.workspace.go_to_dashboard')}
                    </Link>
                </Button>
            </div>
        </AuthLayout>
    );
}
