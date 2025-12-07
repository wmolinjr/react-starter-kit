import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import CentralAdminLayout from '@/layouts/central-admin-layout';
import { logout } from '@/routes/central/admin/auth';
import { send } from '@/routes/central/admin/auth/verification';
import { Form, Head } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Mail } from 'lucide-react';

export default function VerifyEmail({ status }: { status?: string }) {
    const { t } = useLaravelReactI18n();

    return (
        <CentralAdminLayout>
            <Head title={t('Email verification')} />

            <div className="flex min-h-[calc(100vh-8rem)] items-center justify-center p-4">
                <Card className="w-full max-w-md">
                    <CardHeader className="text-center">
                        <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
                            <Mail className="h-6 w-6 text-primary" />
                        </div>
                        <CardTitle>{t('Verify email')}</CardTitle>
                        <CardDescription>
                            {t('Please verify your email address by clicking on the link we just emailed to you.')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {status === 'verification-link-sent' && (
                            <div className="mb-4 rounded-md bg-green-50 p-3 text-center text-sm font-medium text-green-600 dark:bg-green-900/20 dark:text-green-400">
                                {t('A new verification link has been sent to the email address you provided during registration.')}
                            </div>
                        )}

                        <Form {...send()} className="space-y-6 text-center">
                            {({ processing }) => (
                                <>
                                    <Button disabled={processing} variant="secondary" className="w-full">
                                        {processing && <Spinner />}
                                        {t('Resend verification email')}
                                    </Button>

                                    <TextLink
                                        href={logout()}
                                        className="mx-auto block text-sm"
                                    >
                                        {t('Log out')}
                                    </TextLink>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </CentralAdminLayout>
    );
}
