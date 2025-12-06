import { useForm } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Mail, AlertCircle } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Alert, AlertDescription } from '@/components/ui/alert';

interface InviteMemberDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  maxUsersReached: boolean;
}

export function InviteMemberDialog({ open, onOpenChange, maxUsersReached }: InviteMemberDialogProps) {
  const { t } = useLaravelReactI18n();
  const { data, setData, post, processing, errors, reset } = useForm({
    email: '',
    role: 'member',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    post('/team/invite', {
      preserveScroll: true,
      onSuccess: () => {
        reset();
        onOpenChange(false);
      },
    });
  };

  const handleOpenChange = (newOpen: boolean) => {
    if (!newOpen) {
      reset();
    }
    onOpenChange(newOpen);
  };

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent className="sm:max-w-[500px]">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Mail className="h-5 w-5" />
            {t('tenant.team.invite.title')}
          </DialogTitle>
          <DialogDescription>
            {t('tenant.team.invite.description')}
          </DialogDescription>
        </DialogHeader>

        {maxUsersReached && (
          <Alert variant="destructive">
            <AlertCircle className="h-4 w-4" />
            <AlertDescription>
              {t('tenant.team.invite.max_users_reached')}
            </AlertDescription>
          </Alert>
        )}

        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="email">
              {t('tenant.team.invite.email_label')} <span className="text-destructive">*</span>
            </Label>
            <Input
              id="email"
              type="email"
              placeholder={t('placeholders.member_email')}
              value={data.email}
              onChange={(e) => setData('email', e.target.value)}
              disabled={maxUsersReached || processing}
              required
            />
            {errors.email && <p className="text-sm text-destructive">{errors.email}</p>}
          </div>

          <div className="space-y-2">
            <Label htmlFor="role">
              {t('tenant.team.invite.role_label')} <span className="text-destructive">*</span>
            </Label>
            <Select
              value={data.role}
              onValueChange={(value) => setData('role', value)}
              disabled={maxUsersReached || processing}
            >
              <SelectTrigger id="role">
                <SelectValue placeholder={t('placeholders.select_role')} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="admin">{t('tenant.team.role_admin')}</SelectItem>
                <SelectItem value="member">{t('tenant.team.role_member')}</SelectItem>
                <SelectItem value="guest">{t('tenant.team.role_guest')}</SelectItem>
              </SelectContent>
            </Select>
            {errors.role && <p className="text-sm text-destructive">{errors.role}</p>}
            <p className="text-xs text-muted-foreground">
              {data.role === 'admin' && t('tenant.team.invite.role_admin_desc')}
              {data.role === 'member' && t('tenant.team.invite.role_member_desc')}
              {data.role === 'guest' && t('tenant.team.invite.role_guest_desc')}
            </p>
          </div>

          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={() => handleOpenChange(false)}
              disabled={processing}
            >
              {t('common.cancel')}
            </Button>
            <Button type="submit" disabled={maxUsersReached || processing}>
              {processing ? t('tenant.team.invite.sending') : t('tenant.team.invite.send')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
