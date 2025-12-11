import TextLink from '@/components/shared/typography/text-link';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import AdminLayout from '@/layouts/tenant/admin-layout';
import { logout } from '@/routes/tenant/admin/auth';
import { send } from '@/routes/tenant/admin/auth/verification';
import { Form, Head } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Mail } from 'lucide-react';
import { type ReactElement } from 'react';

function VerifyEmail({ status }: { status?: string }) {
    const { t } = useLaravelReactI18n();

    return (
        <>
            <Head title={t('auth.verify.title')} />

            <div className="flex min-h-[calc(100vh-8rem)] items-center justify-center p-4">
                <Card className="w-full max-w-md">
                    <CardHeader className="text-center">
                        <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
                            <Mail className="h-6 w-6 text-primary" />
                        </div>
                        <CardTitle>{t('auth.verify.verify')}</CardTitle>
                        <CardDescription>
                            {t('auth.verify.description')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {status === 'verification-link-sent' && (
                            <div className="mb-4 rounded-md bg-green-50 p-3 text-center text-sm font-medium text-green-600 dark:bg-green-900/20 dark:text-green-400">
                                {t('auth.verify.link_sent')}
                            </div>
                        )}

                        <Form {...send()} className="space-y-6 text-center">
                            {({ processing }) => (
                                <>
                                    <Button disabled={processing} variant="secondary" className="w-full">
                                        {processing && <Spinner />}
                                        {t('auth.verify.resend')}
                                    </Button>

                                    <TextLink
                                        href={logout()}
                                        className="mx-auto block text-sm"
                                    >
                                        {t('auth.logout.button')}
                                    </TextLink>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

VerifyEmail.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default VerifyEmail;
