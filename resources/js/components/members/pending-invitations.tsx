import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import tenants from '@/routes/tenants';
import type { TenantInvitation } from '@/types';
import { router } from '@inertiajs/react';
import { formatDistanceToNow } from 'date-fns';
import { Clock, Mail, Trash2, XCircle } from 'lucide-react';

interface PendingInvitationsProps {
    invitations: TenantInvitation[];
    tenantSlug: string;
}

export function PendingInvitations({ invitations, tenantSlug }: PendingInvitationsProps) {
    const handleResend = (invitation: TenantInvitation) => {
        router.post(tenants.invitations.resend({ slug: tenantSlug, invitation: invitation.id }).url, {}, {
            preserveScroll: true,
        });
    };

    const handleRevoke = (invitation: TenantInvitation) => {
        if (confirm(`Are you sure you want to revoke the invitation for ${invitation.email}?`)) {
            router.delete(
                tenants.invitations.destroy({ slug: tenantSlug, invitation: invitation.id }).url,
                {
                    preserveScroll: true,
                },
            );
        }
    };

    const getInvitationStatus = (invitation: TenantInvitation) => {
        if (invitation.accepted_at) {
            return { label: 'Accepted', variant: 'default' as const, icon: null };
        }

        const expiresAt = new Date(invitation.expires_at);
        const now = new Date();

        if (expiresAt < now) {
            return { label: 'Expired', variant: 'destructive' as const, icon: XCircle };
        }

        return { label: 'Pending', variant: 'secondary' as const, icon: Clock };
    };

    if (invitations.length === 0) {
        return (
            <div className="flex h-[200px] items-center justify-center rounded-md border border-dashed">
                <div className="text-center">
                    <Mail className="mx-auto h-8 w-8 text-muted-foreground" />
                    <p className="mt-2 text-sm text-muted-foreground">No pending invitations</p>
                </div>
            </div>
        );
    }

    return (
        <div className="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Email</TableHead>
                        <TableHead>Role</TableHead>
                        <TableHead>Invited By</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Expires</TableHead>
                        <TableHead className="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {invitations.map((invitation) => {
                        const status = getInvitationStatus(invitation);
                        const StatusIcon = status.icon;

                        return (
                            <TableRow key={invitation.id}>
                                <TableCell className="font-medium">{invitation.email}</TableCell>
                                <TableCell className="capitalize">{invitation.role}</TableCell>
                                <TableCell>{invitation.inviter?.name || 'Unknown'}</TableCell>
                                <TableCell>
                                    <Badge variant={status.variant}>
                                        {StatusIcon && <StatusIcon className="mr-1 h-3 w-3" />}
                                        {status.label}
                                    </Badge>
                                </TableCell>
                                <TableCell className="text-sm text-muted-foreground">
                                    {formatDistanceToNow(new Date(invitation.expires_at), {
                                        addSuffix: true,
                                    })}
                                </TableCell>
                                <TableCell className="text-right">
                                    <div className="flex justify-end gap-2">
                                        {!invitation.accepted_at &&
                                            new Date(invitation.expires_at) > new Date() && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => handleResend(invitation)}
                                                >
                                                    <Mail className="mr-1 h-3 w-3" />
                                                    Resend
                                                </Button>
                                            )}
                                        {!invitation.accepted_at && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => handleRevoke(invitation)}
                                            >
                                                <Trash2 className="mr-1 h-3 w-3" />
                                                Revoke
                                            </Button>
                                        )}
                                    </div>
                                </TableCell>
                            </TableRow>
                        );
                    })}
                </TableBody>
            </Table>
        </div>
    );
}
