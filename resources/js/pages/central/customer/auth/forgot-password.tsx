import InputError from '@/components/shared/feedback/input-error';
import TextLink from '@/components/shared/typography/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import MarketingAuthLayout from '@/layouts/marketing-auth-layout';
import customer from '@/routes/central/account';
import { Form, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';

interface ForgotPasswordProps {
    status?: string;
}

export default function ForgotPassword({ status }: ForgotPasswordProps) {
    const { t } = useLaravelReactI18n();
    const { errors } = usePage().props as { errors?: Record<string, string> };

    return (
        <MarketingAuthLayout
            title={t('auth.forgot.title')}
            cardTitle={t('auth.forgot.title')}
            cardDescription={t('auth.forgot.description')}
        >
            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            <Form
                {...customer.password.email.form()}
                className="flex flex-col gap-6"
            >
                {({ processing }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    {t('auth.field.email_address')}
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    autoComplete="email"
                                    placeholder="email@example.com"
                                />
                                <InputError message={errors?.email} />
                            </div>

                            <Button
                                type="submit"
                                className="w-full"
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                {t('auth.forgot.send_link')}
                            </Button>
                        </div>

                        <div className="text-muted-foreground text-center text-sm">
                            {t('common.remember_password')}{' '}
                            <TextLink href={customer.login.url()}>
                                {t('auth.login.button')}
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </MarketingAuthLayout>
    );
}
