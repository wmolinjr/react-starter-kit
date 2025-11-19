import { AddDomainDialog } from '@/components/domains/add-domain-dialog';
import { DomainStatusBadge } from '@/components/domains/domain-status-badge';
import { DomainVerificationInstructions } from '@/components/domains/domain-verification-instructions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
import type { BreadcrumbItem, Domain, Tenant } from '@/types';
import { Head, router } from '@inertiajs/react';
import { AlertCircle, ArrowLeft, Globe, MoreVertical, Shield } from 'lucide-react';
import { useState } from 'react';

interface DomainsPageProps {
    tenant: Tenant & { domains: Domain[] };
}

export default function DomainsPage({ tenant }: DomainsPageProps) {
    const [selectedDomain, setSelectedDomain] = useState<Domain | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Workspaces',
            href: tenants.index().url,
        },
        {
            title: tenant.name,
            href: tenants.show({ slug: tenant.slug }).url,
        },
        {
            title: 'Settings',
            href: tenants.show({ slug: tenant.slug }).url + '/settings',
        },
    ];

    const handleSetPrimary = (domain: Domain) => {
        router.patch(
            tenants.domains.update({ slug: tenant.slug, domain: domain.id }).url,
            { is_primary: true },
            {
                preserveScroll: true,
                onSuccess: () => {
                    console.log('Domain set as primary');
                },
                onError: (errors) => {
                    console.error('Failed to set domain as primary:', errors);
                },
            }
        );
    };

    const handleVerify = (domain: Domain) => {
        router.post(
            tenants.domains.verify({ slug: tenant.slug, domain: domain.id }).url,
            {},
            {
                preserveScroll: true,
                onError: (errors) => {
                    console.error('Verification failed:', errors);
                },
            }
        );
    };

    const handleDelete = (domain: Domain) => {
        if (domain.is_primary && tenant.domains.length === 1) {
            alert('Cannot delete the only primary domain');
            return;
        }

        if (
            confirm(
                `Are you sure you want to delete "${domain.domain}"? This action cannot be undone.`
            )
        ) {
            router.delete(tenants.domains.destroy({ slug: tenant.slug, domain: domain.id }).url, {
                preserveScroll: true,
                onSuccess: () => {
                    setSelectedDomain(null);
                },
                onError: (errors) => {
                    console.error('Failed to delete domain:', errors);
                },
            });
        }
    };

    const handleViewInstructions = (domain: Domain) => {
        setSelectedDomain(domain);
    };

    const domains = tenant.domains || [];
    const hasDomains = domains.length > 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${tenant.name} - Custom Domains`} />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="icon" asChild>
                        <a href={tenants.show({ slug: tenant.slug }).url + '/settings'}>
                            <ArrowLeft className="h-4 w-4" />
                        </a>
                    </Button>
                    <div className="flex-1">
                        <h1 className="text-2xl font-semibold">Custom Domains</h1>
                        <p className="text-sm text-muted-foreground">
                            Manage custom domains for your workspace. You can add multiple domains and
                            set one as primary.
                        </p>
                    </div>
                    <AddDomainDialog tenantSlug={tenant.slug} />
                </div>

                <div className="mx-auto w-full max-w-6xl space-y-6">
                    {/* Verification Instructions for Selected Domain */}
                    {selectedDomain && selectedDomain.verification_status === 'pending' && (
                        <DomainVerificationInstructions
                            domain={selectedDomain}
                            tenantSlug={tenant.slug}
                        />
                    )}

                    {/* Domains Table */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                    <Globe className="h-5 w-5" />
                                </div>
                                <div>
                                    <CardTitle>Domains</CardTitle>
                                    <CardDescription>
                                        {hasDomains
                                            ? `${domains.length} custom ${domains.length === 1 ? 'domain' : 'domains'} configured`
                                            : 'No custom domains configured yet'}
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {hasDomains ? (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Domain</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Added</TableHead>
                                            <TableHead className="w-[80px]">Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {domains.map((domain) => (
                                            <TableRow key={domain.id}>
                                                <TableCell>
                                                    <div className="flex items-center gap-2">
                                                        <span className="font-medium">
                                                            {domain.domain}
                                                        </span>
                                                        {domain.is_primary && (
                                                            <Badge
                                                                variant="secondary"
                                                                className="bg-blue-500/10 text-blue-700 dark:text-blue-400"
                                                            >
                                                                <Shield className="mr-1 h-3 w-3" />
                                                                Primary
                                                            </Badge>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <DomainStatusBadge
                                                        status={domain.verification_status}
                                                    />
                                                </TableCell>
                                                <TableCell className="text-sm text-muted-foreground">
                                                    {new Date(domain.created_at).toLocaleDateString()}
                                                </TableCell>
                                                <TableCell>
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="h-8 w-8"
                                                            >
                                                                <MoreVertical className="h-4 w-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            {domain.verification_status ===
                                                                'verified' &&
                                                                !domain.is_primary && (
                                                                    <>
                                                                        <DropdownMenuItem
                                                                            onClick={() =>
                                                                                handleSetPrimary(
                                                                                    domain
                                                                                )
                                                                            }
                                                                        >
                                                                            <Shield className="mr-2 h-4 w-4" />
                                                                            Set as Primary
                                                                        </DropdownMenuItem>
                                                                        <DropdownMenuSeparator />
                                                                    </>
                                                                )}
                                                            {domain.verification_status ===
                                                                'pending' && (
                                                                <>
                                                                    <DropdownMenuItem
                                                                        onClick={() =>
                                                                            handleViewInstructions(
                                                                                domain
                                                                            )
                                                                        }
                                                                    >
                                                                        <AlertCircle className="mr-2 h-4 w-4" />
                                                                        View Instructions
                                                                    </DropdownMenuItem>
                                                                    <DropdownMenuItem
                                                                        onClick={() =>
                                                                            handleVerify(domain)
                                                                        }
                                                                    >
                                                                        <Shield className="mr-2 h-4 w-4" />
                                                                        Verify Now
                                                                    </DropdownMenuItem>
                                                                    <DropdownMenuSeparator />
                                                                </>
                                                            )}
                                                            <DropdownMenuItem
                                                                className="text-destructive focus:text-destructive"
                                                                onClick={() => handleDelete(domain)}
                                                                disabled={
                                                                    domain.is_primary &&
                                                                    domains.length === 1
                                                                }
                                                            >
                                                                Delete
                                                            </DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            ) : (
                                <div className="flex flex-col items-center justify-center py-12 text-center">
                                    <div className="flex h-16 w-16 items-center justify-center rounded-full bg-muted">
                                        <Globe className="h-8 w-8 text-muted-foreground" />
                                    </div>
                                    <h3 className="mt-4 text-lg font-semibold">No custom domains</h3>
                                    <p className="mt-2 max-w-sm text-sm text-muted-foreground">
                                        Get started by adding your first custom domain to this workspace.
                                        You'll need to verify ownership via DNS.
                                    </p>
                                    <div className="mt-6">
                                        <AddDomainDialog tenantSlug={tenant.slug} />
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
