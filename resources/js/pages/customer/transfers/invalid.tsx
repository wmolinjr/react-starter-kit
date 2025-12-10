import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import AuthLayout from '@/layouts/auth-layout';
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
                return t('customer.transfer_already_completed');
            case 'cancelled':
                return t('customer.transfer_was_cancelled');
            case 'rejected':
                return t('customer.transfer_was_rejected');
            case 'expired':
                return t('customer.transfer_has_expired');
            default:
                return t('customer.transfer_not_available');
        }
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'completed':
                return <Badge variant="default">{t('customer.completed')}</Badge>;
            case 'cancelled':
                return <Badge variant="outline">{t('customer.cancelled')}</Badge>;
            case 'rejected':
                return <Badge variant="destructive">{t('customer.rejected')}</Badge>;
            case 'expired':
                return <Badge variant="secondary">{t('customer.expired')}</Badge>;
            default:
                return <Badge variant="outline">{status}</Badge>;
        }
    };

    return (
        <AuthLayout
            title={t('customer.transfer_unavailable')}
            description={getStatusMessage(status)}
        >
            <Head title={t('customer.transfer_unavailable')} />

            <div className="flex flex-col items-center text-center space-y-6">
                <div className="rounded-full bg-destructive/10 p-4">
                    <XCircle className="h-8 w-8 text-destructive" />
                </div>

                <div className="space-y-2">
                    <div className="flex items-center justify-center gap-2">
                        <h2 className="text-lg font-semibold">
                            {t('customer.transfer_status')}:
                        </h2>
                        {getStatusBadge(status)}
                    </div>
                    <p className="text-sm text-muted-foreground max-w-sm">
                        {getStatusMessage(status)}
                    </p>
                </div>

                <Button asChild>
                    <Link href="/account">
                        {t('customer.go_to_dashboard')}
                    </Link>
                </Button>
            </div>
        </AuthLayout>
    );
}
