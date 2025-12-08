import InputError from '@/components/shared/feedback/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AdminLayout from '@/layouts/tenant/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { store } from '@/routes/tenant/admin/auth/confirm-password';
import { Form, Head } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ShieldCheck } from 'lucide-react';

export default function ConfirmPassword() {
    const { t } = useLaravelReactI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('auth.confirm_password.breadcrumb'),
            href: '/user/confirm-password',
        },
    ];

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('auth.confirm_password.page_title')} />

            <div className="flex min-h-[calc(100vh-8rem)] items-center justify-center p-4">
                <Card className="w-full max-w-md">
                    <CardHeader className="text-center">
                        <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
                            <ShieldCheck className="h-6 w-6 text-primary" />
                        </div>
                        <CardTitle>{t('auth.confirm_password.title')}</CardTitle>
                        <CardDescription>
                            {t('auth.confirm_password.description')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form {...store()} resetOnSuccess={['password']}>
                            {({ processing, errors }) => (
                                <div className="space-y-6">
                                    <div className="grid gap-2">
                                        <Label htmlFor="password">{t('common.password')}</Label>
                                        <Input
                                            id="password"
                                            type="password"
                                            name="password"
                                            placeholder={t('common.password')}
                                            autoComplete="current-password"
                                            autoFocus
                                        />
                                        <InputError message={errors.password} />
                                    </div>

                                    <Button
                                        className="w-full"
                                        disabled={processing}
                                        data-test="confirm-password-button"
                                    >
                                        {processing && <Spinner />}
                                        {t('auth.confirm_password.submit')}
                                    </Button>
                                </div>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
