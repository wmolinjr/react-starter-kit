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
import customer from '@/routes/central/account';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, Link, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { AlertCircle, Plus } from 'lucide-react';
import { type ReactElement } from 'react';

interface TenantCreateProps {
    hasPaymentMethod: boolean;
}

function TenantCreate({ hasPaymentMethod }: TenantCreateProps) {
    const { t } = useLaravelReactI18n();
    const { errors } = usePage().props as { errors?: Record<string, string> };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('customer.dashboard.title'), href: customer.dashboard.url() },
        { title: t('customer.workspace.title'), href: customer.tenants.index.url() },
        { title: t('customer.workspace.create'), href: customer.tenants.create.url() },
    ];
    useSetBreadcrumbs(breadcrumbs);

    return (
        <>
            <Head title={t('customer.workspace.create')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={Plus}>
                            {t('customer.workspace.create')}
                        </PageTitle>
                        <PageDescription>
                            {t('customer.workspace.create_description')}
                        </PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent className="max-w-2xl">
                    {!hasPaymentMethod && (
                        <Card className="border-warning bg-warning/10">
                            <CardContent className="flex items-center gap-4 py-4">
                                <AlertCircle className="h-5 w-5 text-warning" />
                                <div>
                                    <p className="font-medium">{t('customer.payment.method_required')}</p>
                                    <p className="text-sm text-muted-foreground">
                                        {t('customer.payment.add_method_first')}
                                    </p>
                                </div>
                                <Button asChild variant="outline" className="ml-auto">
                                    <Link href={customer.paymentMethods.create.url()}>
                                        {t('customer.payment.add_method')}
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('customer.workspace.details')}</CardTitle>
                            <CardDescription>
                                {t('customer.workspace.details_description')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form
                                {...customer.tenants.store.form()}
                                className="space-y-4"
                            >
                                {({ processing }) => (
                                    <>
                                        <div className="space-y-2">
                                            <Label htmlFor="name">{t('customer.workspace.name')}</Label>
                                            <Input
                                                id="name"
                                                name="name"
                                                required
                                                autoFocus
                                                placeholder={t('customer.workspace.name_placeholder')}
                                            />
                                            <InputError message={errors?.name} />
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="slug">{t('customer.workspace.slug')}</Label>
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
                                                {t('customer.workspace.slug_help')}
                                            </p>
                                            <InputError message={errors?.slug} />
                                        </div>

                                        <div className="flex gap-4 pt-4">
                                            <Button
                                                type="submit"
                                                disabled={processing || !hasPaymentMethod}
                                            >
                                                {processing && <Spinner />}
                                                {t('customer.workspace.create')}
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
                </PageContent>
            </Page>
        </>
    );
}

TenantCreate.layout = (page: ReactElement) => <CustomerLayout>{page}</CustomerLayout>;

export default TenantCreate;
