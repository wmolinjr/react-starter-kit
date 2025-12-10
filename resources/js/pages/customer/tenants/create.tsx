import CustomerLayout from '@/layouts/customer-layout';
import InputError from '@/components/shared/feedback/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Form, Head, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { AlertCircle } from 'lucide-react';

interface TenantCreateProps {
    hasPaymentMethod: boolean;
}

export default function TenantCreate({ hasPaymentMethod }: TenantCreateProps) {
    const { t } = useLaravelReactI18n();
    const { errors } = usePage().props as { errors?: Record<string, string> };

    return (
        <CustomerLayout
            breadcrumbs={[
                { title: t('customer.dashboard'), href: '/account' },
                { title: t('customer.workspaces'), href: '/account/tenants' },
                { title: t('customer.create_workspace'), href: '/account/tenants/create' },
            ]}
        >
            <Head title={t('customer.create_workspace')} />

            <div className="space-y-6 max-w-2xl">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        {t('customer.create_workspace')}
                    </h1>
                    <p className="text-muted-foreground">
                        {t('customer.create_workspace_description')}
                    </p>
                </div>

                {!hasPaymentMethod && (
                    <Card className="border-warning bg-warning/10">
                        <CardContent className="flex items-center gap-4 py-4">
                            <AlertCircle className="h-5 w-5 text-warning" />
                            <div>
                                <p className="font-medium">{t('customer.payment_method_required')}</p>
                                <p className="text-sm text-muted-foreground">
                                    {t('customer.add_payment_method_first')}
                                </p>
                            </div>
                            <Button asChild variant="outline" className="ml-auto">
                                <a href="/account/payment-methods/create">
                                    {t('customer.add_payment_method')}
                                </a>
                            </Button>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>{t('customer.workspace_details')}</CardTitle>
                        <CardDescription>
                            {t('customer.workspace_details_description')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            action="/account/tenants"
                            method="post"
                            className="space-y-4"
                        >
                            {({ processing }) => (
                                <>
                                    <div className="space-y-2">
                                        <Label htmlFor="name">{t('customer.workspace_name')}</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            required
                                            autoFocus
                                            placeholder={t('customer.workspace_name_placeholder')}
                                        />
                                        <InputError message={errors?.name} />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="slug">{t('customer.workspace_slug')}</Label>
                                        <div className="flex items-center">
                                            <Input
                                                id="slug"
                                                name="slug"
                                                required
                                                placeholder="my-company"
                                                className="rounded-r-none"
                                            />
                                            <span className="inline-flex items-center px-3 border border-l-0 border-input bg-muted text-muted-foreground rounded-r-md text-sm">
                                                .{window.location.hostname.split('.').slice(-2).join('.')}
                                            </span>
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            {t('customer.workspace_slug_help')}
                                        </p>
                                        <InputError message={errors?.slug} />
                                    </div>

                                    <div className="flex gap-4 pt-4">
                                        <Button
                                            type="submit"
                                            disabled={processing || !hasPaymentMethod}
                                        >
                                            {processing && <Spinner />}
                                            {t('customer.create_workspace')}
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => window.history.back()}
                                        >
                                            {t('common.cancel')}
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </CustomerLayout>
    );
}
