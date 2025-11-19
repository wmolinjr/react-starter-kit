import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Users, UserPlus, Mail, MoreVertical, Trash2, Shield } from 'lucide-react';

import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { InviteMemberDialog } from '@/components/invite-member-dialog';
import { Can } from '@/components/can';

interface Member {
  id: number;
  name: string;
  email: string;
  role: 'owner' | 'admin' | 'member' | 'guest';
  invited_at: string;
  joined_at: string | null;
  is_pending: boolean;
}

interface Tenant {
  id: string;
  name: string;
  max_users: number | null;
  current_users: number;
}

interface Props {
  members: Member[];
  tenant: Tenant;
}

export default function TeamIndex({ members, tenant }: Props) {
  const [inviteDialogOpen, setInviteDialogOpen] = useState(false);

  const getRoleBadge = (role: string) => {
    const variants: Record<string, { variant: 'default' | 'secondary' | 'destructive' | 'outline'; label: string }> = {
      owner: { variant: 'default', label: 'Proprietário' },
      admin: { variant: 'secondary', label: 'Administrador' },
      member: { variant: 'outline', label: 'Membro' },
      guest: { variant: 'outline', label: 'Convidado' },
    };

    const config = variants[role] || variants.guest;
    return <Badge variant={config.variant}>{config.label}</Badge>;
  };

  const handleUpdateRole = (userId: number, newRole: string) => {
    if (confirm(`Tem certeza que deseja alterar a role deste membro para ${newRole}?`)) {
      router.patch(
        `/team/${userId}/role`,
        { role: newRole },
        {
          preserveScroll: true,
          onSuccess: () => {
            // Success message will be shown via flash message
          },
        }
      );
    }
  };

  const handleRemoveMember = (userId: number, userName: string) => {
    if (confirm(`Tem certeza que deseja remover ${userName} do time?`)) {
      router.delete(`/team/${userId}`, {
        preserveScroll: true,
        onSuccess: () => {
          // Success message will be shown via flash message
        },
      });
    }
  };

  return (
    <AppLayout>
      <Head title="Gerenciar Time" />

      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight flex items-center gap-2">
              <Users className="h-8 w-8" />
              Gerenciar Time
            </h1>
            <p className="text-muted-foreground mt-2">
              Convide membros, gerencie permissões e controle o acesso ao {tenant.name}.
            </p>
          </div>

          <Can permission="canManageTeam">
            <Button onClick={() => setInviteDialogOpen(true)}>
              <UserPlus className="mr-2 h-4 w-4" />
              Convidar Membro
            </Button>
          </Can>
        </div>

        {/* Usage Stats */}
        {tenant.max_users && (
          <div className="bg-muted/50 rounded-lg p-4">
            <p className="text-sm text-muted-foreground">
              Membros ativos: <strong>{tenant.current_users}</strong> / {tenant.max_users}
            </p>
          </div>
        )}

        {/* Members Table */}
        <div className="rounded-md border">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Nome</TableHead>
                <TableHead>Email</TableHead>
                <TableHead>Role</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className="w-[50px]"></TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {members.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={5} className="text-center py-8 text-muted-foreground">
                    Nenhum membro encontrado.
                  </TableCell>
                </TableRow>
              ) : (
                members.map((member) => (
                  <TableRow key={member.id}>
                    <TableCell className="font-medium">{member.name}</TableCell>
                    <TableCell>{member.email}</TableCell>
                    <TableCell>{getRoleBadge(member.role)}</TableCell>
                    <TableCell>
                      {member.is_pending ? (
                        <Badge variant="outline" className="gap-1">
                          <Mail className="h-3 w-3" />
                          Convite Pendente
                        </Badge>
                      ) : (
                        <Badge variant="outline" className="bg-green-50 text-green-700 border-green-200">
                          Ativo
                        </Badge>
                      )}
                    </TableCell>
                    <TableCell>
                      <Can permission="canManageTeam">
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="icon">
                              <MoreVertical className="h-4 w-4" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end">
                            <DropdownMenuLabel>Ações</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            {member.role !== 'owner' && (
                              <>
                                <DropdownMenuItem onClick={() => handleUpdateRole(member.id, 'admin')}>
                                  <Shield className="mr-2 h-4 w-4" />
                                  Promover para Admin
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={() => handleUpdateRole(member.id, 'member')}>
                                  <Shield className="mr-2 h-4 w-4" />
                                  Definir como Membro
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={() => handleUpdateRole(member.id, 'guest')}>
                                  <Shield className="mr-2 h-4 w-4" />
                                  Definir como Convidado
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                              </>
                            )}
                            <DropdownMenuItem
                              className="text-destructive"
                              onClick={() => handleRemoveMember(member.id, member.name)}
                            >
                              <Trash2 className="mr-2 h-4 w-4" />
                              Remover do Time
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </Can>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </div>
      </div>

      {/* Invite Dialog */}
      <InviteMemberDialog
        open={inviteDialogOpen}
        onOpenChange={setInviteDialogOpen}
        maxUsersReached={tenant.max_users !== null && tenant.current_users >= tenant.max_users}
      />
    </AppLayout>
  );
}
