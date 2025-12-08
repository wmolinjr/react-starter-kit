import { useState, type ReactElement } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Users, UserPlus, Mail, MoreVertical, Trash2, Shield } from 'lucide-react';

import AdminLayout from '@/layouts/tenant/admin-layout';
import admin from '@/routes/tenant/admin';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { type BreadcrumbItem } from '@/types';
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
import { InviteMemberDialog } from '@/components/tenant/dialogs/invite-member-dialog';
import { Can } from '@/components/shared/auth/can';
import { Page, PageHeader, PageHeaderContent, PageHeaderActions, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';

interface Member {
  id: string;
  name: string;
  email: string;
  role: 'owner' | 'admin' | 'member' | 'guest';
  invited_at: string;
  joined_at: string | null;
  is_pending: boolean;
}

interface TeamStats {
  max_users: number | null;
  current_users: number;
}

interface Props {
  members: Member[];
  teamStats: TeamStats;
}

function TeamIndex({ members, teamStats }: Props) {
  const { t } = useLaravelReactI18n();
  const { tenant: tenantData } = usePage<PageProps>().props;
  const [inviteDialogOpen, setInviteDialogOpen] = useState(false);

  const breadcrumbs: BreadcrumbItem[] = [
    { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
    { title: t('tenant.team.title'), href: admin.team.index.url() },
  ];

  useSetBreadcrumbs(breadcrumbs);

  const getRoleBadge = (role: string) => {
    const variants: Record<string, { variant: 'default' | 'secondary' | 'destructive' | 'outline'; labelKey: string }> = {
      owner: { variant: 'default', labelKey: 'tenant.team.role_owner' },
      admin: { variant: 'secondary', labelKey: 'tenant.team.role_admin' },
      member: { variant: 'outline', labelKey: 'tenant.team.role_member' },
      guest: { variant: 'outline', labelKey: 'tenant.team.role_guest' },
    };

    const config = variants[role] || variants.guest;
    return <Badge variant={config.variant}>{t(config.labelKey)}</Badge>;
  };

  const handleUpdateRole = (userId: string, newRole: string) => {
    if (confirm(t('tenant.team.confirm_role_change', { role: newRole }))) {
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

  const handleRemoveMember = (userId: string, userName: string) => {
    if (confirm(t('tenant.team.confirm_remove', { name: userName }))) {
      router.delete(`/team/${userId}`, {
        preserveScroll: true,
        onSuccess: () => {
          // Success message will be shown via flash message
        },
      });
    }
  };

  return (
    <>
      <Head title={t('tenant.team.page_title')} />

      <Page>
        <PageHeader>
          <PageHeaderContent>
            <PageTitle icon={Users}>{t('tenant.team.page_title')}</PageTitle>
            <PageDescription>
              {t('tenant.team.description', { name: tenantData?.name ?? '' })}
            </PageDescription>
          </PageHeaderContent>
          <PageHeaderActions>
            <Can permission="team:invite">
              <Button onClick={() => setInviteDialogOpen(true)}>
                <UserPlus className="mr-2 h-4 w-4" />
                {t('tenant.team.invite_member')}
              </Button>
            </Can>
          </PageHeaderActions>
        </PageHeader>

        <PageContent>
          {/* Usage Stats */}
          {teamStats.max_users && (
            <div className="bg-muted/50 rounded-lg p-4">
              <p className="text-sm text-muted-foreground">
                {t('tenant.team.active_members')}: <strong>{teamStats.current_users}</strong> / {teamStats.max_users}
              </p>
            </div>
          )}

          {/* Members Table */}
          <div className="rounded-md border">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t('common.name')}</TableHead>
                  <TableHead>{t('common.email')}</TableHead>
                  <TableHead>Role</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead className="w-[50px]"></TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {members.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={5} className="text-center py-8 text-muted-foreground">
                      {t('tenant.team.no_members')}
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
                            {t('tenant.team.pending_invite')}
                          </Badge>
                        ) : (
                          <Badge variant="outline" className="bg-green-50 text-green-700 border-green-200">
                            {t('tenant.team.status_active')}
                          </Badge>
                        )}
                      </TableCell>
                      <TableCell>
                        <Can any={["team:manageRoles", "team:remove"]}>
                          <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                              <Button variant="ghost" size="icon">
                                <MoreVertical className="h-4 w-4" />
                              </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                              <DropdownMenuLabel>{t('common.actions')}</DropdownMenuLabel>
                              <DropdownMenuSeparator />
                              {member.role !== 'owner' && (
                                <>
                                  <DropdownMenuItem onClick={() => handleUpdateRole(member.id, 'admin')}>
                                    <Shield className="mr-2 h-4 w-4" />
                                    {t('tenant.team.promote_to_admin')}
                                  </DropdownMenuItem>
                                  <DropdownMenuItem onClick={() => handleUpdateRole(member.id, 'member')}>
                                    <Shield className="mr-2 h-4 w-4" />
                                    {t('tenant.team.set_as_member')}
                                  </DropdownMenuItem>
                                  <DropdownMenuItem onClick={() => handleUpdateRole(member.id, 'guest')}>
                                    <Shield className="mr-2 h-4 w-4" />
                                    {t('tenant.team.set_as_guest')}
                                  </DropdownMenuItem>
                                  <DropdownMenuSeparator />
                                </>
                              )}
                              <DropdownMenuItem
                                className="text-destructive"
                                onClick={() => handleRemoveMember(member.id, member.name)}
                              >
                                <Trash2 className="mr-2 h-4 w-4" />
                                {t('tenant.team.remove_from_team')}
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
        </PageContent>
      </Page>

      {/* Invite Dialog */}
      <InviteMemberDialog
        open={inviteDialogOpen}
        onOpenChange={setInviteDialogOpen}
        maxUsersReached={teamStats.max_users !== null && teamStats.current_users >= teamStats.max_users}
      />
    </>
  );
}

TeamIndex.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default TeamIndex;
