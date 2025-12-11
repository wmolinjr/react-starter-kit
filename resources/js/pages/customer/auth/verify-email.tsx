import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { Form, Head } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';

interface VerifyEmailProps {
    status?: string;
}

export default function VerifyEmail({ status }: VerifyEmailProps) {
    const { t } = useLaravelReactI18n();

    return (
        <AuthLayout
            title={t('auth.verify.verify')}
            description={t('auth.verify_email_description')}
        >
            <Head title={t('auth.verify.verify')} />

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {t('auth.verify.link_sent')}
                </div>
            )}

            <div className="flex flex-col gap-6">
                <p className="text-center text-sm text-muted-foreground">
                    {t('auth.verify_email_instructions')}
                </p>

                <Form
                    action="/account/email/verification-notification"
                    method="post"
                >
                    {({ processing }) => (
                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            {t('auth.verify.resend')}
                        </Button>
                    )}
                </Form>

                <Form action="/account/logout" method="post">
                    {({ processing }) => (
                        <Button
                            type="submit"
                            variant="outline"
                            className="w-full"
                            disabled={processing}
                        >
                            {t('auth.logout.button')}
                        </Button>
                    )}
                </Form>
            </div>
        </AuthLayout>
    );
}
