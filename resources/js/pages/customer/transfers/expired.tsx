import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';
import { Head, Link } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Clock } from 'lucide-react';

export default function TransferExpired() {
    const { t } = useLaravelReactI18n();

    return (
        <AuthLayout
            title={t('customer.transfer.expired')}
            description={t('customer.transfer.expired_description')}
        >
            <Head title={t('customer.transfer.expired')} />

            <div className="flex flex-col items-center text-center space-y-6">
                <div className="rounded-full bg-muted p-4">
                    <Clock className="h-8 w-8 text-muted-foreground" />
                </div>

                <div className="space-y-2">
                    <h2 className="text-lg font-semibold">
                        {t('customer.transfer.link_expired')}
                    </h2>
                    <p className="text-sm text-muted-foreground max-w-sm">
                        {t('customer.transfer.link_expired_description')}
                    </p>
                </div>

                <Button asChild>
                    <Link href="/account">
                        {t('customer.workspace.go_to_dashboard')}
                    </Link>
                </Button>
            </div>
        </AuthLayout>
    );
}
