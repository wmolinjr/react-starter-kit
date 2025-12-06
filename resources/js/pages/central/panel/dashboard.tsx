import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import UniversalLayout from '@/layouts/universal-layout';
import central from '@/routes/central';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    Building2,
    Users,
    ArrowRight,
    CalendarDays,
    Plus,
} from 'lucide-react';
import { useLaravelReactI18n } from 'laravel-react-i18n';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: central.panel.dashboard.url(),
    },
];

interface Tenant {
    id: string;
    name: string;
    slug: string;
    role: string | null;
    joined_at: string;
    url: string;
}

interface DashboardProps {
    tenants: Tenant[];
}

const roleColors: Record<string, string> = {
    owner: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
    admin: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    member: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    guest: 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400',
};

export default function Dashboard({ tenants = [] }: DashboardProps) {
    const { t } = useLaravelReactI18n();

    return (
        <UniversalLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">
                            {t('central.dashboard.my_tenants')}
                        </h1>
                        <p className="text-muted-foreground">
                            {t('central.dashboard.manage_tenants')}
                        </p>
                    </div>
                    <Button>
                        <Plus className="mr-2 h-4 w-4" />
                        {t('central.dashboard.create_tenant')}
                    </Button>
                </div>

                {/* Tenants Grid */}
                {tenants.length === 0 ? (
                    <Card className="col-span-full">
                        <CardContent className="flex flex-col items-center justify-center py-16">
                            <Building2 className="mb-4 h-12 w-12 text-muted-foreground" />
                            <h3 className="mb-2 text-lg font-semibold">
                                {t('central.dashboard.no_tenants')}
                            </h3>
                            <p className="mb-4 text-center text-sm text-muted-foreground">
                                {t('central.dashboard.no_tenants_description')}
                            </p>
                            <Button>
                                <Plus className="mr-2 h-4 w-4" />
                                {t('central.dashboard.create_first_tenant')}
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {tenants.map((tenant) => (
                            <Card
                                key={tenant.id}
                                className="group relative overflow-hidden transition-all hover:shadow-lg"
                            >
                                <CardHeader>
                                    <div className="flex items-start justify-between">
                                        <div className="flex items-center gap-2">
                                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                                <Building2 className="h-5 w-5 text-primary" />
                                            </div>
                                            <div>
                                                <CardTitle className="text-lg">
                                                    {tenant.name}
                                                </CardTitle>
                                                <CardDescription className="text-xs">
                                                    {tenant.slug}
                                                </CardDescription>
                                            </div>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {/* Role Badge */}
                                    <div className="flex items-center gap-2">
                                        <Users className="h-4 w-4 text-muted-foreground" />
                                        <Badge
                                            variant="secondary"
                                            className={
                                                roleColors[
                                                    tenant.role || 'guest'
                                                ]
                                            }
                                        >
                                            {tenant.role || t('central.dashboard.no_role')}
                                        </Badge>
                                    </div>

                                    {/* Joined Date */}
                                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                        <CalendarDays className="h-4 w-4" />
                                        <span>
                                            {t('central.dashboard.member_since')}{' '}
                                            {new Date(
                                                tenant.joined_at,
                                            ).toLocaleDateString('pt-BR', {
                                                day: '2-digit',
                                                month: 'short',
                                                year: 'numeric',
                                            })}
                                        </span>
                                    </div>

                                    {/* Access Button */}
                                    <Link
                                        href={central.panel.access.url({
                                            tenant: tenant.id,
                                        })}
                                        className="mt-4 flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                                    >
                                        {t('central.dashboard.access_tenant')}
                                        <ArrowRight className="h-4 w-4" />
                                    </Link>
                                </CardContent>

                                {/* Hover Gradient */}
                                <div className="pointer-events-none absolute inset-0 bg-gradient-to-br from-primary/5 to-transparent opacity-0 transition-opacity group-hover:opacity-100" />
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </UniversalLayout>
    );
}
