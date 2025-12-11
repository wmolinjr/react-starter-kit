import InputError from '@/components/shared/feedback/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { Form, Head, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';

interface ResetPasswordProps {
    token: string;
    email: string;
}

export default function ResetPassword({ token, email }: ResetPasswordProps) {
    const { t } = useLaravelReactI18n();
    const { errors } = usePage().props as { errors?: Record<string, string> };

    return (
        <AuthLayout
            title={t('auth.reset.button')}
            description={t('auth.reset_password_description')}
        >
            <Head title={t('auth.reset.button')} />

            <Form
                action="/account/reset-password"
                method="post"
                className="flex flex-col gap-6"
            >
                {({ processing }) => (
                    <div className="grid gap-6">
                        <input type="hidden" name="token" value={token} />

                        <div className="grid gap-2">
                            <Label htmlFor="email">
                                {t('auth.field.email_address')}
                            </Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                defaultValue={email}
                                required
                                autoComplete="email"
                            />
                            <InputError message={errors?.email} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">
                                {t('auth.field.new_password')}
                            </Label>
                            <Input
                                id="password"
                                type="password"
                                name="password"
                                required
                                autoFocus
                                autoComplete="new-password"
                                placeholder={t('auth.field.new_password')}
                            />
                            <InputError message={errors?.password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                {t('auth.field.confirm_password')}
                            </Label>
                            <Input
                                id="password_confirmation"
                                type="password"
                                name="password_confirmation"
                                required
                                autoComplete="new-password"
                                placeholder={t('auth.field.confirm_password')}
                            />
                            <InputError message={errors?.password_confirmation} />
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            {t('auth.reset.button')}
                        </Button>
                    </div>
                )}
            </Form>
        </AuthLayout>
    );
}
