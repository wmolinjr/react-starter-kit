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
import tenants from '@/routes/tenants';
import { useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface AddDomainDialogProps {
    tenantSlug: string;
}

export function AddDomainDialog({ tenantSlug }: AddDomainDialogProps) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        domain: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(tenants.domains.store({ slug: tenantSlug }).url, {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                reset();
            },
            onError: (errors) => {
                console.error('Failed to add domain:', errors);
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
                    <Plus className="mr-2 h-4 w-4" />
                    Add Custom Domain
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-[500px]">
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>Add Custom Domain</DialogTitle>
                        <DialogDescription>
                            Add a custom domain to your workspace. You'll need to verify ownership by
                            adding a DNS record.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4 py-4">
                        <div className="space-y-2">
                            <Label htmlFor="domain">
                                Domain Name
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="domain"
                                type="text"
                                value={data.domain}
                                onChange={(e) => setData('domain', e.target.value)}
                                placeholder="workspace.example.com"
                                required
                                autoFocus
                                disabled={processing}
                            />
                            {errors.domain && (
                                <p className="text-sm text-destructive">{errors.domain}</p>
                            )}
                            <p className="text-xs text-muted-foreground">
                                Enter a fully qualified domain name (e.g., workspace.example.com)
                            </p>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Adding...' : 'Add Domain'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
