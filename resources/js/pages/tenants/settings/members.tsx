import { InviteMemberDialog } from '@/components/members/invite-member-dialog';
import { MemberRoleSelect } from '@/components/members/member-role-select';
import { PendingInvitations } from '@/components/members/pending-invitations';
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
import type { BreadcrumbItem, Tenant, TenantInvitation, TenantRole, TenantUser } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Crown, MoreVertical, Shield, Trash2, Users } from 'lucide-react';
import { formatDistanceToNow } from 'date-fns';

interface MembersPageProps {
    tenant: Tenant;
    members: TenantUser[];
    invitations: TenantInvitation[];
}

export default function MembersPage({ tenant, members, invitations }: MembersPageProps) {
    const { auth } = usePage().props;
    const currentUser = auth.user;

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

    const handleRoleChange = (member: TenantUser, newRole: TenantRole) => {
        if (member.role === newRole) return;

        router.patch(
            tenants.members.update({ slug: tenant.slug, user: member.id }).url,
            { role: newRole },
            {
                preserveScroll: true,
                onSuccess: () => {
                    console.log('Member role updated');
                },
                onError: (errors) => {
                    console.error('Failed to update member role:', errors);
                },
            }
        );
    };

    const handleRemoveMember = (member: TenantUser) => {
        if (
            confirm(
                `Are you sure you want to remove ${member.name} from this workspace? This action cannot be undone.`
            )
        ) {
            router.delete(tenants.members.destroy({ slug: tenant.slug, user: member.id }).url, {
                preserveScroll: true,
                onError: (errors) => {
                    console.error('Failed to remove member:', errors);
                },
            });
        }
    };

    const handleTransferOwnership = (member: TenantUser) => {
        if (
            confirm(
                `Are you sure you want to transfer ownership to ${member.name}? You will become an admin after this action.`
            )
        ) {
            router.post(
                tenants.members['transfer-ownership']({ slug: tenant.slug, user: member.id }).url,
                {},
                {
                    preserveScroll: true,
                    onError: (errors) => {
                        console.error('Failed to transfer ownership:', errors);
                    },
                }
            );
        }
    };

    const currentUserMember = members.find((m) => m.id === currentUser.id);
    const isOwner = currentUserMember?.role === 'owner';
    const isAdmin = currentUserMember?.role === 'admin' || isOwner;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${tenant.name} - Team Members`} />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="icon" asChild>
                        <a href={tenants.show({ slug: tenant.slug }).url + '/settings'}>
                            <ArrowLeft className="h-4 w-4" />
                        </a>
                    </Button>
                    <div className="flex-1">
                        <h1 className="text-2xl font-semibold">Team Members</h1>
                        <p className="text-sm text-muted-foreground">
                            Manage your workspace members and their roles. Invite new members or update
                            existing permissions.
                        </p>
                    </div>
                    {isAdmin && <InviteMemberDialog tenantSlug={tenant.slug} />}
                </div>

                <div className="mx-auto w-full max-w-6xl space-y-6">
                    {/* Team Members Table */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                    <Users className="h-5 w-5" />
                                </div>
                                <div>
                                    <CardTitle>Active Members</CardTitle>
                                    <CardDescription>
                                        {members.length} {members.length === 1 ? 'member' : 'members'} in
                                        this workspace
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Member</TableHead>
                                        <TableHead>Role</TableHead>
                                        <TableHead>Joined</TableHead>
                                        <TableHead className="w-[80px]">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {members.map((member) => {
                                        const isCurrentUser = member.id === currentUser.id;
                                        const isMemberOwner = member.role === 'owner';
                                        const canModify = isAdmin && !isCurrentUser;

                                        return (
                                            <TableRow key={member.id}>
                                                <TableCell>
                                                    <div className="flex flex-col">
                                                        <div className="flex items-center gap-2">
                                                            <span className="font-medium">
                                                                {member.name}
                                                            </span>
                                                            {isCurrentUser && (
                                                                <Badge variant="outline" className="text-xs">
                                                                    You
                                                                </Badge>
                                                            )}
                                                            {isMemberOwner && (
                                                                <Badge
                                                                    variant="secondary"
                                                                    className="bg-amber-500/10 text-amber-700 dark:text-amber-400"
                                                                >
                                                                    <Crown className="mr-1 h-3 w-3" />
                                                                    Owner
                                                                </Badge>
                                                            )}
                                                        </div>
                                                        <span className="text-sm text-muted-foreground">
                                                            {member.email}
                                                        </span>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    {canModify && !isMemberOwner ? (
                                                        <MemberRoleSelect
                                                            value={member.role}
                                                            onChange={(role) =>
                                                                handleRoleChange(member, role)
                                                            }
                                                        />
                                                    ) : (
                                                        <span className="capitalize">{member.role}</span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-sm text-muted-foreground">
                                                    {formatDistanceToNow(new Date(member.joined_at), {
                                                        addSuffix: true,
                                                    })}
                                                </TableCell>
                                                <TableCell>
                                                    {canModify && (
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
                                                                {isOwner && !isMemberOwner && (
                                                                    <>
                                                                        <DropdownMenuItem
                                                                            onClick={() =>
                                                                                handleTransferOwnership(
                                                                                    member
                                                                                )
                                                                            }
                                                                        >
                                                                            <Shield className="mr-2 h-4 w-4" />
                                                                            Transfer Ownership
                                                                        </DropdownMenuItem>
                                                                        <DropdownMenuSeparator />
                                                                    </>
                                                                )}
                                                                <DropdownMenuItem
                                                                    className="text-destructive focus:text-destructive"
                                                                    onClick={() => handleRemoveMember(member)}
                                                                    disabled={isMemberOwner}
                                                                >
                                                                    <Trash2 className="mr-2 h-4 w-4" />
                                                                    Remove Member
                                                                </DropdownMenuItem>
                                                            </DropdownMenuContent>
                                                        </DropdownMenu>
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    {/* Pending Invitations */}
                    {isAdmin && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Pending Invitations</CardTitle>
                                <CardDescription>
                                    Invitations sent to new members that haven't been accepted yet
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <PendingInvitations
                                    invitations={invitations}
                                    tenantSlug={tenant.slug}
                                />
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
