import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { MemberRoleSelect } from '@/components/members/member-role-select';
import tenants from '@/routes/tenants';
import type { TenantRole } from '@/types';
import { useForm } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface InviteMemberDialogProps {
    tenantSlug: string;
}

export function InviteMemberDialog({ tenantSlug }: InviteMemberDialogProps) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm<{
        email: string;
        role: TenantRole;
    }>({
        email: '',
        role: 'member',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(tenants.invitations.store({ slug: tenantSlug }).url, {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                reset();
            },
            onError: (errors) => {
                console.error('Failed to send invitation:', errors);
            },
        });
    };

    const handleOpenChange = (isOpen: boolean) => {
        setOpen(isOpen);
        if (!isOpen) {
            reset();
        }
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild>
                <Button>
                    <UserPlus className="mr-2 h-4 w-4" />
                    Invite Member
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-[500px]">
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>Invite Team Member</DialogTitle>
                        <DialogDescription>
                            Send an invitation to add a new member to your workspace. They will receive an
                            email with a link to accept the invitation.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4 py-4">
                        <div className="space-y-2">
                            <Label htmlFor="email">
                                Email Address
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                placeholder="colleague@example.com"
                                required
                                autoFocus
                                disabled={processing}
                            />
                            {errors.email && (
                                <p className="text-sm text-destructive">{errors.email}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="role">
                                Role
                                <span className="text-destructive">*</span>
                            </Label>
                            <MemberRoleSelect
                                value={data.role}
                                onChange={(role) => setData('role', role)}
                                disabled={processing}
                            />
                            {errors.role && <p className="text-sm text-destructive">{errors.role}</p>}
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => handleOpenChange(false)}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Sending...' : 'Send Invitation'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
