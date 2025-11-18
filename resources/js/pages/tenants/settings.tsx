import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import tenants from '@/routes/tenants';
import type { BreadcrumbItem, TenantWithUsers } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Building2, Save, Trash2, Users } from 'lucide-react';
import { FormEventHandler } from 'react';

interface TenantsSettingsProps {
    tenant: TenantWithUsers;
}

export default function TenantsSettings({ tenant }: TenantsSettingsProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Workspaces',
            href: tenants.index().url,
        },
        {
            title: tenant.name,
            href: tenants.show({ slug: tenant.slug }).url,
        },
    ];

    const { data, setData, put, processing, errors } = useForm({
        name: tenant.name,
        domain: tenant.domain || '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(tenants.update({ slug: tenant.slug }).url);
    };

    const handleDelete = () => {
        if (
            confirm(
                `Are you sure you want to delete "${tenant.name}"? This action cannot be undone.`
            )
        ) {
            router.delete(tenants.destroy({ slug: tenant.slug }).url);
        }
    };

    const currentUserRole = tenant.users.find(
        (user) => user.id === (window as any).Laravel?.user?.id
    )?.role;
    const canUpdate = currentUserRole === 'owner' || currentUserRole === 'admin';
    const canDelete = currentUserRole === 'owner';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${tenant.name} - Settings`} />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center gap-4">
                    <Button
                        variant="outline"
                        size="icon"
                        asChild
                    >
                        <a href={tenants.index().url}>
                            <ArrowLeft className="h-4 w-4" />
                        </a>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-semibold">{tenant.name}</h1>
                        <p className="text-sm text-muted-foreground">
                            Workspace settings and members
                        </p>
                    </div>
                </div>

                <div className="mx-auto w-full max-w-4xl space-y-6">
                    {/* Workspace Details */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                    <Building2 className="h-5 w-5" />
                                </div>
                                <div>
                                    <CardTitle>Workspace Details</CardTitle>
                                    <CardDescription>
                                        Update your workspace information
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submit} className="space-y-6">
                                <div className="space-y-2">
                                    <Label htmlFor="name">
                                        Workspace Name
                                        <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="name"
                                        type="text"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder="My Awesome Workspace"
                                        disabled={!canUpdate}
                                        required
                                    />
                                    {errors.name && (
                                        <p className="text-sm text-destructive">{errors.name}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="slug">Slug</Label>
                                    <Input
                                        id="slug"
                                        type="text"
                                        value={tenant.slug}
                                        disabled
                                        className="bg-muted"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        The workspace slug cannot be changed
                                    </p>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="domain">
                                        Custom Domain
                                        <span className="text-muted-foreground text-xs ml-1">
                                            (optional)
                                        </span>
                                    </Label>
                                    <Input
                                        id="domain"
                                        type="text"
                                        value={data.domain}
                                        onChange={(e) => setData('domain', e.target.value)}
                                        placeholder="workspace.example.com"
                                        disabled={!canUpdate}
                                    />
                                    {errors.domain && (
                                        <p className="text-sm text-destructive">{errors.domain}</p>
                                    )}
                                    <p className="text-xs text-muted-foreground">
                                        Optional custom domain for this workspace
                                    </p>
                                </div>

                                {canUpdate && (
                                    <div className="flex items-center gap-3 pt-4">
                                        <Button type="submit" disabled={processing}>
                                            <Save className="mr-2 h-4 w-4" />
                                            {processing ? 'Saving...' : 'Save Changes'}
                                        </Button>
                                    </div>
                                )}
                            </form>
                        </CardContent>
                    </Card>

                    {/* Members */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                    <Users className="h-5 w-5" />
                                </div>
                                <div>
                                    <CardTitle>Members</CardTitle>
                                    <CardDescription>
                                        People who have access to this workspace
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Email</TableHead>
                                        <TableHead>Role</TableHead>
                                        <TableHead>Joined</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {tenant.users.map((user) => (
                                        <TableRow key={user.id}>
                                            <TableCell className="font-medium">
                                                {user.name}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {user.email}
                                            </TableCell>
                                            <TableCell>
                                                <div className="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium capitalize text-primary inline-block">
                                                    {user.role}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {new Date(user.joined_at).toLocaleDateString()}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    {/* Danger Zone */}
                    {canDelete && (
                        <Card className="border-destructive/50">
                            <CardHeader>
                                <CardTitle className="text-destructive">Danger Zone</CardTitle>
                                <CardDescription>
                                    Irreversible actions for this workspace
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="font-medium">Delete Workspace</p>
                                        <p className="text-sm text-muted-foreground">
                                            Permanently delete this workspace and all its data
                                        </p>
                                    </div>
                                    <Button
                                        variant="destructive"
                                        onClick={handleDelete}
                                    >
                                        <Trash2 className="mr-2 h-4 w-4" />
                                        Delete
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
