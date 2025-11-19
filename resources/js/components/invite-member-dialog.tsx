import { useForm } from '@inertiajs/react';
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
            Convidar Novo Membro
          </DialogTitle>
          <DialogDescription>
            Envie um convite por e-mail para adicionar um novo membro ao seu time.
          </DialogDescription>
        </DialogHeader>

        {maxUsersReached && (
          <Alert variant="destructive">
            <AlertCircle className="h-4 w-4" />
            <AlertDescription>
              Você atingiu o limite máximo de usuários para o seu plano. Faça upgrade para adicionar mais membros.
            </AlertDescription>
          </Alert>
        )}

        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="email">
              E-mail <span className="text-destructive">*</span>
            </Label>
            <Input
              id="email"
              type="email"
              placeholder="membro@exemplo.com"
              value={data.email}
              onChange={(e) => setData('email', e.target.value)}
              disabled={maxUsersReached || processing}
              required
            />
            {errors.email && <p className="text-sm text-destructive">{errors.email}</p>}
          </div>

          <div className="space-y-2">
            <Label htmlFor="role">
              Função <span className="text-destructive">*</span>
            </Label>
            <Select
              value={data.role}
              onValueChange={(value) => setData('role', value)}
              disabled={maxUsersReached || processing}
            >
              <SelectTrigger id="role">
                <SelectValue placeholder="Selecione uma função" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="admin">Administrador</SelectItem>
                <SelectItem value="member">Membro</SelectItem>
                <SelectItem value="guest">Convidado</SelectItem>
              </SelectContent>
            </Select>
            {errors.role && <p className="text-sm text-destructive">{errors.role}</p>}
            <p className="text-xs text-muted-foreground">
              {data.role === 'admin' && 'Pode gerenciar membros e configurações do time.'}
              {data.role === 'member' && 'Pode criar e editar recursos, mas não gerenciar membros.'}
              {data.role === 'guest' && 'Acesso apenas para visualização, sem permissões de edição.'}
            </p>
          </div>

          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={() => handleOpenChange(false)}
              disabled={processing}
            >
              Cancelar
            </Button>
            <Button type="submit" disabled={maxUsersReached || processing}>
              {processing ? 'Enviando...' : 'Enviar Convite'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
