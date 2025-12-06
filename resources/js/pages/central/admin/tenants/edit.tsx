import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import CentralAdminLayout from '@/layouts/central-admin-layout';
import admin from '@/routes/central/admin';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Page, PageHeader, PageHeaderContent, PageHeaderActions, PageTitle, PageDescription, PageContent } from '@/components/page';
import { type BreadcrumbItem } from '@/types';
import { useLaravelReactI18n } from 'laravel-react-i18n';

interface Props {
    tenant: {
        id: string;
        name: string;
        plan_id: string | null;
    };
    plans: { id: string; name: string }[];
}

export default function TenantEdit({ tenant, plans }: Props) {
    const { t } = useLaravelReactI18n();
    const { data, setData, put, processing } = useForm({
        name: tenant.name,
        plan_id: tenant.plan_id?.toString() || '',
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
        { title: t('breadcrumbs.tenants'), href: admin.tenants.index.url() },
        { title: tenant.name, href: admin.tenants.show.url(tenant.id) },
        { title: t('common.edit'), href: admin.tenants.edit.url(tenant.id) },
    ];

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(admin.tenants.update.url(tenant.id));
    };

    return (
        <CentralAdminLayout breadcrumbs={breadcrumbs}>
            <Head title={`${t('admin.tenants.edit_tenant')}: ${tenant.name}`} />

            <Page>
                <PageHeader>
                    <PageHeaderActions>
                        <Button variant="outline" size="icon" asChild>
                            <Link href={admin.tenants.index.url()}>
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                    </PageHeaderActions>
                    <PageHeaderContent>
                        <PageTitle>{t('admin.tenants.edit_tenant')}</PageTitle>
                        <PageDescription>{tenant.name}</PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    <form onSubmit={handleSubmit}>
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('admin.tenants.tenant_details')}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label>{t('common.name')}</Label>
                                <Input
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    required
                                />
                            </div>

                            <div className="space-y-2">
                                <Label>Plan</Label>
                                <Select
                                    value={data.plan_id}
                                    onValueChange={(v) => setData('plan_id', v)}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder={t('admin.tenants.select_plan')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {plans.map((plan) => (
                                            <SelectItem key={plan.id} value={plan.id.toString()}>
                                                {plan.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="flex justify-end gap-2 pt-4">
                                <Button type="button" variant="outline" asChild>
                                    <Link href={admin.tenants.index.url()}>{t('common.cancel')}</Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {t('common.save_changes')}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </form>
                </PageContent>
            </Page>
        </CentralAdminLayout>
    );
}
