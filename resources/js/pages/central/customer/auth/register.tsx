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

export default function Register() {
    const { t } = useLaravelReactI18n();
    const { errors } = usePage().props as { errors?: Record<string, string> };

    return (
        <MarketingAuthLayout
            title={t('auth.register.sign_up')}
            cardTitle={t('customer.workspace.create_account')}
            cardDescription={t('customer.dashboard.start')}
        >
            <Form
                {...customer.register.store.form()}
                className="flex flex-col gap-6"
            >
                {({ processing }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">{t('auth.field.name')}</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    type="text"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="name"
                                    placeholder={t('auth.field.full_name')}
                                />
                                <InputError message={errors?.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    {t('auth.field.email_address')}
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    tabIndex={2}
                                    autoComplete="email"
                                    placeholder="email@example.com"
                                />
                                <InputError message={errors?.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">
                                    {t('auth.field.password')}
                                </Label>
                                <Input
                                    id="password"
                                    type="password"
                                    name="password"
                                    required
                                    tabIndex={3}
                                    autoComplete="new-password"
                                    placeholder={t('auth.field.password')}
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
                                    tabIndex={4}
                                    autoComplete="new-password"
                                    placeholder={t('auth.field.confirm_password')}
                                />
                                <InputError message={errors?.password_confirmation} />
                            </div>

                            <Button
                                type="submit"
                                className="mt-4 w-full"
                                tabIndex={5}
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                {t('auth.register.sign_up')}
                            </Button>
                        </div>

                        <div className="text-muted-foreground text-center text-sm">
                            {t('auth.register.already_have_account')}{' '}
                            <TextLink href={customer.login.url()} tabIndex={6}>
                                {t('auth.login.button')}
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </MarketingAuthLayout>
    );
}
