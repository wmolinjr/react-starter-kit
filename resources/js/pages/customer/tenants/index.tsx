import CustomerLayout from '@/layouts/customer-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import customer from '@/routes/central/account';
import { Head, Link } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Building2, Plus, ArrowRight, Globe } from 'lucide-react';

interface TenantSummary {
    id: string;
    name: string;
    slug: string;
    domain: string;
    plan_name: string | null;
    created_at: string;
}

interface TenantsIndexProps {
    tenants: TenantSummary[];
}

export default function TenantsIndex({ tenants }: TenantsIndexProps) {
    const { t } = useLaravelReactI18n();

    return (
        <CustomerLayout
            breadcrumbs={[
                { title: t('customer.dashboard.title'), href: customer.dashboard.url() },
                { title: t('customer.workspace.title'), href: customer.tenants.index.url() },
            ]}
        >
            <Head title={t('customer.workspace.title')} />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            {t('customer.workspace.title')}
                        </h1>
                        <p className="text-muted-foreground">
                            {t('customer.workspace.manage_description')}
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={customer.tenants.create.url()}>
                            <Plus className="mr-2 h-4 w-4" />
                            {t('customer.workspace.create')}
                        </Link>
                    </Button>
                </div>

                {tenants.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-16">
                            <Building2 className="h-16 w-16 text-muted-foreground/50 mb-4" />
                            <h3 className="text-lg font-medium mb-2">
                                {t('customer.workspace.no_workspaces')}
                            </h3>
                            <p className="text-muted-foreground text-center mb-4">
                                {t('customer.workspace.no_workspaces_description')}
                            </p>
                            <Button asChild>
                                <Link href={customer.tenants.create.url()}>
                                    <Plus className="mr-2 h-4 w-4" />
                                    {t('customer.workspace.create_first')}
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {tenants.map((tenant) => (
                            <Card key={tenant.id} className="hover:shadow-md transition-shadow">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Building2 className="h-5 w-5" />
                                        {tenant.name}
                                    </CardTitle>
                                    <CardDescription className="flex items-center gap-1">
                                        <Globe className="h-3 w-3" />
                                        {tenant.domain}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="flex items-center justify-between">
                                        {tenant.plan_name ? (
                                            <span className="text-xs bg-primary/10 text-primary px-2 py-1 rounded">
                                                {tenant.plan_name}
                                            </span>
                                        ) : (
                                            <span className="text-xs text-muted-foreground">
                                                {t('customer.subscription.no_plan')}
                                            </span>
                                        )}
                                        <Button asChild variant="ghost" size="sm">
                                            <Link href={customer.tenants.show.url(tenant.id)}>
                                                {t('common.manage')}
                                                <ArrowRight className="ml-2 h-4 w-4" />
                                            </Link>
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </CustomerLayout>
    );
}
