import {
    Page,
    PageHeader,
    PageHeaderContent,
    PageTitle,
    PageDescription,
    PageContent,
} from '@/components/shared/layout/page';
import CustomerLayout from '@/layouts/customer-layout';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import InputError from '@/components/shared/feedback/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import customerRoutes from '@/routes/central/account';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { User } from 'lucide-react';
import { type ReactElement } from 'react';

interface Customer {
    id: string;
    name: string;
    email: string;
    phone: string | null;
    locale: string;
    currency: string;
    billing_address: {
        line1?: string;
        line2?: string;
        city?: string;
        state?: string;
        postal_code?: string;
        country?: string;
    } | null;
    email_verified_at: string | null;
    two_factor_enabled: boolean;
}

interface ProfileEditProps {
    customer: Customer;
    status?: string;
}

function ProfileEdit({ customer, status }: ProfileEditProps) {
    const { t } = useLaravelReactI18n();
    const { errors } = usePage().props as { errors?: Record<string, string> };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('customer.dashboard.title'), href: customerRoutes.dashboard.url() },
        { title: t('customer.profile.title'), href: customerRoutes.profile.edit.url() },
    ];
    useSetBreadcrumbs(breadcrumbs);

    return (
        <>
            <Head title={t('customer.profile.title')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={User}>
                            {t('customer.profile.title')}
                        </PageTitle>
                        <PageDescription>
                            {t('customer.profile.description')}
                        </PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    {status && (
                        <div className="rounded-lg bg-green-50 p-4 text-green-700">
                            {status === 'profile-updated' && t('customer.profile.updated')}
                            {status === 'password-updated' && t('customer.security.password_updated')}
                            {status === 'billing-updated' && t('customer.billing.updated')}
                        </div>
                    )}

                    {/* Profile Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('customer.profile.information')}</CardTitle>
                            <CardDescription>
                                {t('customer.profile.information_description')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form
                                action="/account/profile"
                                method="patch"
                                className="space-y-4"
                            >
                                {({ processing }) => (
                                    <>
                                        <div className="grid gap-4 md:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label htmlFor="name">{t('auth.field.name')}</Label>
                                                <Input
                                                    id="name"
                                                    name="name"
                                                    defaultValue={customer.name}
                                                    required
                                                />
                                                <InputError message={errors?.name} />
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="email">{t('auth.field.email_address')}</Label>
                                                <Input
                                                    id="email"
                                                    name="email"
                                                    type="email"
                                                    defaultValue={customer.email}
                                                    required
                                                />
                                                <InputError message={errors?.email} />
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="phone">{t('customer.address.phone')}</Label>
                                                <Input
                                                    id="phone"
                                                    name="phone"
                                                    type="tel"
                                                    defaultValue={customer.phone ?? ''}
                                                />
                                                <InputError message={errors?.phone} />
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="locale">{t('customer.profile.language')}</Label>
                                                <Input
                                                    id="locale"
                                                    name="locale"
                                                    defaultValue={customer.locale}
                                                />
                                                <InputError message={errors?.locale} />
                                            </div>
                                        </div>

                                        <Button type="submit" disabled={processing}>
                                            {processing && <Spinner />}
                                            {t('common.save')}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>

                    {/* Password */}
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('customer.security.update_password')}</CardTitle>
                            <CardDescription>
                                {t('customer.security.update_password_description')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form
                                action="/account/profile/password"
                                method="patch"
                                className="space-y-4"
                            >
                                {({ processing }) => (
                                    <>
                                        <div className="grid gap-4 md:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label htmlFor="current_password">
                                                    {t('auth.field.current_password')}
                                                </Label>
                                                <Input
                                                    id="current_password"
                                                    name="current_password"
                                                    type="password"
                                                    autoComplete="current-password"
                                                />
                                                <InputError message={errors?.current_password} />
                                            </div>

                                            <div />

                                            <div className="space-y-2">
                                                <Label htmlFor="password">
                                                    {t('auth.field.new_password')}
                                                </Label>
                                                <Input
                                                    id="password"
                                                    name="password"
                                                    type="password"
                                                    autoComplete="new-password"
                                                />
                                                <InputError message={errors?.password} />
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="password_confirmation">
                                                    {t('auth.field.confirm_password')}
                                                </Label>
                                                <Input
                                                    id="password_confirmation"
                                                    name="password_confirmation"
                                                    type="password"
                                                    autoComplete="new-password"
                                                />
                                            </div>
                                        </div>

                                        <Button type="submit" disabled={processing}>
                                            {processing && <Spinner />}
                                            {t('customer.security.update_password')}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>

                    {/* Billing Address */}
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('customer.billing.address')}</CardTitle>
                            <CardDescription>
                                {t('customer.billing.address_description')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form
                                action="/account/profile/billing"
                                method="patch"
                                className="space-y-4"
                            >
                                {({ processing }) => (
                                    <>
                                        <div className="grid gap-4 md:grid-cols-2">
                                            <div className="space-y-2 md:col-span-2">
                                                <Label htmlFor="billing_address.line1">
                                                    {t('customer.address.line1')}
                                                </Label>
                                                <Input
                                                    id="billing_address.line1"
                                                    name="billing_address[line1]"
                                                    defaultValue={customer.billing_address?.line1 ?? ''}
                                                />
                                            </div>

                                            <div className="space-y-2 md:col-span-2">
                                                <Label htmlFor="billing_address.line2">
                                                    {t('customer.address.line2')}
                                                </Label>
                                                <Input
                                                    id="billing_address.line2"
                                                    name="billing_address[line2]"
                                                    defaultValue={customer.billing_address?.line2 ?? ''}
                                                />
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="billing_address.city">
                                                    {t('customer.address.city')}
                                                </Label>
                                                <Input
                                                    id="billing_address.city"
                                                    name="billing_address[city]"
                                                    defaultValue={customer.billing_address?.city ?? ''}
                                                />
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="billing_address.state">
                                                    {t('customer.address.state')}
                                                </Label>
                                                <Input
                                                    id="billing_address.state"
                                                    name="billing_address[state]"
                                                    defaultValue={customer.billing_address?.state ?? ''}
                                                />
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="billing_address.postal_code">
                                                    {t('customer.address.postal_code')}
                                                </Label>
                                                <Input
                                                    id="billing_address.postal_code"
                                                    name="billing_address[postal_code]"
                                                    defaultValue={customer.billing_address?.postal_code ?? ''}
                                                />
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="billing_address.country">
                                                    {t('customer.address.country')}
                                                </Label>
                                                <Input
                                                    id="billing_address.country"
                                                    name="billing_address[country]"
                                                    defaultValue={customer.billing_address?.country ?? ''}
                                                    placeholder="BR"
                                                    maxLength={2}
                                                />
                                            </div>
                                        </div>

                                        <Button type="submit" disabled={processing}>
                                            {processing && <Spinner />}
                                            {t('common.save')}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>

                    {/* Delete Account */}
                    <Card className="border-destructive">
                        <CardHeader>
                            <CardTitle className="text-destructive">
                                {t('customer.account.delete')}
                            </CardTitle>
                            <CardDescription>
                                {t('customer.account.delete_warning')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form
                                action="/account/profile"
                                method="delete"
                                className="space-y-4"
                            >
                                {({ processing }) => (
                                    <>
                                        <div className="space-y-2">
                                            <Label htmlFor="delete_password">
                                                {t('auth.field.password')}
                                            </Label>
                                            <Input
                                                id="delete_password"
                                                name="password"
                                                type="password"
                                                autoComplete="current-password"
                                                required
                                            />
                                            <InputError message={errors?.password} />
                                        </div>

                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            disabled={processing}
                                        >
                                            {processing && <Spinner />}
                                            {t('customer.account.delete')}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                </PageContent>
            </Page>
        </>
    );
}

ProfileEdit.layout = (page: ReactElement) => <CustomerLayout>{page}</CustomerLayout>;

export default ProfileEdit;
