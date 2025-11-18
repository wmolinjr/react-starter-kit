import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import tenants from '@/routes/tenants';
import type { BreadcrumbItem, TenantIndexItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Building2, Plus, Users } from 'lucide-react';

interface TenantsIndexProps {
    tenants: TenantIndexItem[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Workspaces',
        href: tenants.index().url,
    },
];

export default function TenantsIndex({ tenants: tenantsList }: TenantsIndexProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Workspaces" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">Workspaces</h1>
                        <p className="text-sm text-muted-foreground">
                            Manage your workspaces and switch between them
                        </p>
                    </div>
                    <Link href={tenants.create().url}>
                        <Button>
                            <Plus className="mr-2 h-4 w-4" />
                            New Workspace
                        </Button>
                    </Link>
                </div>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {tenantsList.map((tenant) => (
                        <Link
                            key={tenant.id}
                            href={tenants.show({ slug: tenant.slug }).url}
                        >
                            <Card className="group cursor-pointer transition-all hover:border-primary hover:shadow-md">
                                <CardHeader>
                                    <div className="flex items-start justify-between">
                                        <div className="flex items-center gap-3">
                                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                <Building2 className="h-5 w-5" />
                                            </div>
                                            <div>
                                                <CardTitle className="text-base group-hover:text-primary">
                                                    {tenant.name}
                                                </CardTitle>
                                                <CardDescription className="text-xs">
                                                    {tenant.slug}
                                                </CardDescription>
                                            </div>
                                        </div>
                                        <div className="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium capitalize text-primary">
                                            {tenant.role}
                                        </div>
                                    </div>

                                    <div className="mt-4 flex items-center gap-4 text-xs text-muted-foreground">
                                        <div className="flex items-center gap-1">
                                            <Users className="h-3.5 w-3.5" />
                                            <span>
                                                {tenant.users_count}{' '}
                                                {tenant.users_count === 1 ? 'member' : 'members'}
                                            </span>
                                        </div>
                                        <div
                                            className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                                                tenant.status === 'active'
                                                    ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                                    : tenant.status === 'inactive'
                                                      ? 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'
                                                      : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                                            }`}
                                        >
                                            {tenant.status}
                                        </div>
                                    </div>
                                </CardHeader>
                            </Card>
                        </Link>
                    ))}
                </div>

                {tenantsList.length === 0 && (
                    <div className="flex flex-1 items-center justify-center rounded-xl border border-dashed border-sidebar-border/70 p-12 dark:border-sidebar-border">
                        <div className="text-center">
                            <Building2 className="mx-auto h-12 w-12 text-muted-foreground/50" />
                            <h3 className="mt-4 text-lg font-semibold">
                                No workspaces yet
                            </h3>
                            <p className="mt-2 text-sm text-muted-foreground">
                                Get started by creating your first workspace
                            </p>
                            <Link href={tenants.create().url}>
                                <Button className="mt-6">
                                    <Plus className="mr-2 h-4 w-4" />
                                    Create Workspace
                                </Button>
                            </Link>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
