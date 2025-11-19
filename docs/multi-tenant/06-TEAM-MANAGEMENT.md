# 06 - Team Management

## Índice

- [Sistema de Convites](#sistema-de-convites)
- [Gerenciar Membros](#gerenciar-membros)
- [Controller Completo](#controller-completo)
- [Páginas Inertia/React](#páginas-inertia-react)

---

## Sistema de Convites

### Flow de Convite

```
1. Owner/Admin convida usuário por email
   ↓
2. Verifica se email já existe:
   - Se SIM: criar registro em tenant_user com invitation_token
   - Se NÃO: criar User + registro em tenant_user
   ↓
3. Enviar email com link de convite
   ↓
4. User clica no link
   ↓
5. Se já tem conta: aceita convite
   Se não tem conta: cria senha e aceita convite
   ↓
6. Atualiza tenant_user.joined_at
```

### Migration: Já criada em 02-DATABASE.md

```php
Schema::create('tenant_user', function (Blueprint $table) {
    // ...
    $table->timestamp('invited_at')->nullable();
    $table->string('invitation_token')->nullable()->unique();
    $table->timestamp('joined_at')->nullable();
});
```

---

## Gerenciar Membros

### TeamController

```bash
php artisan make:controller TeamController
```

```php
<?php

namespace App\Http\Controllers;

use App\Mail\TeamInvitation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TeamController extends Controller
{
    /**
     * Mostrar página de gerenciamento de equipe
     */
    public function index()
    {
        $tenant = current_tenant();

        $members = $tenant->users()
            ->withPivot('role', 'invited_at', 'joined_at', 'invitation_token')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->pivot->role,
                    'invited_at' => $user->pivot->invited_at,
                    'joined_at' => $user->pivot->joined_at,
                    'is_pending' => $user->pivot->joined_at === null,
                ];
            });

        return inertia('team/index', [
            'members' => $members,
            'roles' => ['owner', 'admin', 'member', 'guest'],
        ]);
    }

    /**
     * Convidar novo membro
     */
    public function invite(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'role' => 'required|in:admin,member,guest',
        ]);

        $tenant = current_tenant();

        // Prevenir convidar para ser owner (apenas via transfer ownership)
        if ($request->role === 'owner') {
            return back()->withErrors(['role' => 'Não é possível convidar como owner.']);
        }

        // Verificar limite de usuários
        $currentMembers = $tenant->users()->count();
        $maxUsers = $tenant->getSetting('limits.max_users', 10);

        if ($tenant->hasReachedLimit('max_users', $currentMembers)) {
            return back()->withErrors([
                'email' => "Limite de {$maxUsers} usuários atingido. Faça upgrade do seu plano."
            ]);
        }

        return DB::transaction(function () use ($request, $tenant) {
            $user = User::where('email', $request->email)->first();

            // Se user não existe, criar
            if (!$user) {
                $user = User::create([
                    'name' => explode('@', $request->email)[0], // Nome temporário
                    'email' => $request->email,
                    'password' => bcrypt(Str::random(32)), // Senha temporária
                ]);
            }

            // Verificar se já é membro
            if ($tenant->users()->where('user_id', $user->id)->exists()) {
                return back()->withErrors(['email' => 'Este usuário já é membro.']);
            }

            // Criar convite
            $token = Str::random(64);

            $tenant->users()->attach($user->id, [
                'role' => $request->role,
                'invited_at' => now(),
                'invitation_token' => $token,
                'joined_at' => null,
            ]);

            // Enviar email
            Mail::to($user->email)->send(new TeamInvitation(
                $tenant,
                auth()->user(),
                $token,
                $request->role
            ));

            return back()->with('success', 'Convite enviado com sucesso!');
        });
    }

    /**
     * Aceitar convite
     */
    public function acceptInvitation(string $token)
    {
        $invitation = DB::table('tenant_user')
            ->where('invitation_token', $token)
            ->whereNull('joined_at')
            ->first();

        if (!$invitation) {
            abort(404, 'Convite inválido ou já aceito.');
        }

        $tenant = Tenant::find($invitation->tenant_id);
        $user = User::find($invitation->user_id);

        // Se user já está logado e é o correto
        if (auth()->check() && auth()->id() === $user->id) {
            DB::table('tenant_user')
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $user->id)
                ->update([
                    'joined_at' => now(),
                    'invitation_token' => null,
                ]);

            // Redirecionar para tenant
            return redirect()->to($tenant->url() . '/dashboard')
                ->with('success', 'Convite aceito! Bem-vindo à ' . $tenant->name);
        }

        // Se não está logado ou é outro user, mostrar página de aceitar convite
        return inertia('team/accept-invitation', [
            'tenant' => [
                'name' => $tenant->name,
            ],
            'token' => $token,
            'user' => [
                'email' => $user->email,
                'name' => $user->name,
            ],
        ]);
    }

    /**
     * Atualizar role de membro
     */
    public function updateRole(Request $request, User $user)
    {
        $request->validate([
            'role' => 'required|in:owner,admin,member,guest',
        ]);

        $tenant = current_tenant();

        // Prevenir remover último owner
        if ($user->pivot->role === 'owner' && $request->role !== 'owner') {
            $ownerCount = $tenant->owners()->count();

            if ($ownerCount <= 1) {
                return back()->withErrors([
                    'role' => 'Deve haver pelo menos um owner.'
                ]);
            }
        }

        $tenant->users()->updateExistingPivot($user->id, [
            'role' => $request->role,
        ]);

        return back()->with('success', 'Role atualizado com sucesso!');
    }

    /**
     * Remover membro
     */
    public function remove(User $user)
    {
        $tenant = current_tenant();

        // Prevenir remover a si mesmo
        if ($user->id === auth()->id()) {
            return back()->withErrors(['error' => 'Você não pode remover a si mesmo.']);
        }

        // Prevenir remover último owner
        if ($user->pivot->role === 'owner') {
            $ownerCount = $tenant->owners()->count();

            if ($ownerCount <= 1) {
                return back()->withErrors([
                    'error' => 'Não é possível remover o último owner.'
                ]);
            }
        }

        $tenant->users()->detach($user->id);

        return back()->with('success', 'Membro removido com sucesso!');
    }
}
```

### Email: TeamInvitation

```bash
php artisan make:mail TeamInvitation
```

```php
<?php

namespace App\Mail;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TeamInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public User $inviter,
        public string $token,
        public string $role
    ) {}

    public function build()
    {
        $acceptUrl = $this->tenant->url() . '/invitation/' . $this->token;

        return $this->subject("Convite para {$this->tenant->name}")
            ->markdown('emails.team-invitation', [
                'tenant' => $this->tenant,
                'inviter' => $this->inviter,
                'acceptUrl' => $acceptUrl,
                'role' => $this->role,
            ]);
    }
}
```

**Template: `resources/views/emails/team-invitation.blade.php`**

```blade
@component('mail::message')
# Convite para {{ $tenant->name }}

Você foi convidado por **{{ $inviter->name }}** para fazer parte de **{{ $tenant->name }}** como **{{ $role }}**.

@component('mail::button', ['url' => $acceptUrl])
Aceitar Convite
@endcomponent

Este convite é válido por 7 dias.

Obrigado,<br>
{{ config('app.name') }}
@endcomponent
```

---

## Páginas Inertia/React

### Team Index

```tsx
// resources/js/pages/team/index.tsx

import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { InviteMemberDialog } from './invite-member-dialog';
import { usePermissions } from '@/hooks/use-permissions';

interface Member {
  id: number;
  name: string;
  email: string;
  role: string;
  invited_at: string | null;
  joined_at: string | null;
  is_pending: boolean;
}

interface Props {
  members: Member[];
  roles: string[];
}

export default function TeamIndex({ members, roles }: Props) {
  const [inviteDialogOpen, setInviteDialogOpen] = useState(false);
  const permissions = usePermissions();

  const handleUpdateRole = (userId: number, newRole: string) => {
    router.patch(`/team/members/${userId}`, { role: newRole }, {
      preserveScroll: true,
    });
  };

  const handleRemoveMember = (userId: number) => {
    if (confirm('Tem certeza que deseja remover este membro?')) {
      router.delete(`/team/members/${userId}`, {
        preserveScroll: true,
      });
    }
  };

  return (
    <AppLayout>
      <Head title="Gerenciar Equipe" />

      <div className="py-12">
        <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
          <div className="flex items-center justify-between">
            <h1 className="text-3xl font-bold">Equipe</h1>

            {permissions.canManageTeam && (
              <Button onClick={() => setInviteDialogOpen(true)}>
                Convidar Membro
              </Button>
            )}
          </div>

          <Card className="mt-6">
            <CardHeader>
              <CardTitle>Membros ({members.length})</CardTitle>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Nome</TableHead>
                    <TableHead>Email</TableHead>
                    <TableHead>Role</TableHead>
                    <TableHead>Status</TableHead>
                    {permissions.canManageTeam && <TableHead>Ações</TableHead>}
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {members.map((member) => (
                    <TableRow key={member.id}>
                      <TableCell>{member.name}</TableCell>
                      <TableCell>{member.email}</TableCell>
                      <TableCell>
                        {permissions.canManageTeam ? (
                          <select
                            value={member.role}
                            onChange={(e) =>
                              handleUpdateRole(member.id, e.target.value)
                            }
                            className="rounded border px-2 py-1"
                            disabled={member.role === 'owner' && permissions.role !== 'owner'}
                          >
                            {roles.map((role) => (
                              <option key={role} value={role}>
                                {role}
                              </option>
                            ))}
                          </select>
                        ) : (
                          <Badge>{member.role}</Badge>
                        )}
                      </TableCell>
                      <TableCell>
                        {member.is_pending ? (
                          <Badge variant="outline">Pendente</Badge>
                        ) : (
                          <Badge variant="success">Ativo</Badge>
                        )}
                      </TableCell>
                      {permissions.canManageTeam && (
                        <TableCell>
                          <Button
                            variant="destructive"
                            size="sm"
                            onClick={() => handleRemoveMember(member.id)}
                            disabled={member.role === 'owner'}
                          >
                            Remover
                          </Button>
                        </TableCell>
                      )}
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </div>
      </div>

      <InviteMemberDialog
        open={inviteDialogOpen}
        onClose={() => setInviteDialogOpen(false)}
        roles={roles.filter((r) => r !== 'owner')}
      />
    </AppLayout>
  );
}
```

### Invite Member Dialog

```tsx
// resources/js/pages/team/invite-member-dialog.tsx

import { useForm } from '@inertiajs/react';
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

interface Props {
  open: boolean;
  onClose: () => void;
  roles: string[];
}

export function InviteMemberDialog({ open, onClose, roles }: Props) {
  const { data, setData, post, processing, errors, reset } = useForm({
    email: '',
    role: 'member',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    post('/team/invite', {
      onSuccess: () => {
        reset();
        onClose();
      },
    });
  };

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent>
        <form onSubmit={handleSubmit}>
          <DialogHeader>
            <DialogTitle>Convidar Membro</DialogTitle>
            <DialogDescription>
              Envie um convite por email para adicionar um novo membro à equipe.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4 py-4">
            <div>
              <Label htmlFor="email">Email</Label>
              <Input
                id="email"
                type="email"
                value={data.email}
                onChange={(e) => setData('email', e.target.value)}
                placeholder="usuario@exemplo.com"
                required
              />
              {errors.email && (
                <p className="mt-1 text-sm text-red-600">{errors.email}</p>
              )}
            </div>

            <div>
              <Label htmlFor="role">Role</Label>
              <Select value={data.role} onValueChange={(value) => setData('role', value)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {roles.map((role) => (
                    <SelectItem key={role} value={role}>
                      {role}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {errors.role && (
                <p className="mt-1 text-sm text-red-600">{errors.role}</p>
              )}
            </div>
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose}>
              Cancelar
            </Button>
            <Button type="submit" disabled={processing}>
              Enviar Convite
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
```

---

## Checklist

- [ ] `TeamController` criado com métodos index, invite, acceptInvitation, updateRole, remove
- [ ] Email `TeamInvitation` criado
- [ ] Template de email criado
- [ ] Página `team/index.tsx` criada
- [ ] Componente `invite-member-dialog.tsx` criado
- [ ] Rotas de team configuradas
- [ ] Teste: convidar membro funciona
- [ ] Teste: aceitar convite funciona
- [ ] Teste: atualizar role funciona
- [ ] Teste: remover membro funciona
- [ ] Teste: não é possível remover último owner

---

## Próximo Passo

➡️ **[07-BILLING.md](./07-BILLING.md)** - Sistema de Assinaturas

---

**Versão:** 1.0
**Última atualização:** 2025-11-19
