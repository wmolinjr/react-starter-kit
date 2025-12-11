import { useState, type ReactElement } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import type { PageProps, TeamMemberResource, UserInvitationResource } from '@/types';
import type { TeamStats } from '@/types/common';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Users, UserPlus, Mail, MoreVertical, Trash2, Shield } from 'lucide-react';

import AdminLayout from '@/layouts/tenant/admin-layout';
import admin from '@/routes/tenant/admin';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { type BreadcrumbItem } from '@/types';
import type { TenantRole } from '@/types/enums';
import { TENANT_ROLE } from '@/lib/enum-metadata';
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

interface Props {
  members: TeamMemberResource[];
  pendingInvitations: UserInvitationResource[];
  teamStats: TeamStats;
}

function TeamIndex({ members, pendingInvitations, teamStats }: Props) {
  const { t } = useLaravelReactI18n();
  const { tenant: tenantData } = usePage<PageProps>().props;
  const [inviteDialogOpen, setInviteDialogOpen] = useState(false);

  const breadcrumbs: BreadcrumbItem[] = [
    { title: t('dashboard.page.title'), href: admin.dashboard.url() },
    { title: t('team.page.title'), href: admin.team.index.url() },
  ];

  useSetBreadcrumbs(breadcrumbs);

  const getRoleBadge = (role: string | null) => {
    if (!role) return <Badge variant="outline">-</Badge>;
    // Use enum metadata for system roles, fallback for custom roles
    const metadata = TENANT_ROLE[role as TenantRole];
    if (metadata) {
      return <Badge variant={metadata.badge_variant}>{metadata.label}</Badge>;
    }
    // Fallback for custom roles (e.g., 'guest' or user-created roles)
    return <Badge variant="outline">{t(`tenant.team.role_${role}`, { default: role })}</Badge>;
  };

  const handleUpdateRole = (userId: string, newRole: string) => {
    if (confirm(t('team.confirm_role_change', { role: newRole }))) {
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
    if (confirm(t('team.confirm_remove', { name: userName }))) {
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
      <Head title={t('team.page.page_title')} />

      <Page>
        <PageHeader>
          <PageHeaderContent>
            <PageTitle icon={Users}>{t('team.page.page_title')}</PageTitle>
            <PageDescription>
              {t('team.description', { name: tenantData?.name ?? '' })}
            </PageDescription>
          </PageHeaderContent>
          <PageHeaderActions>
            <Can permission="team:invite">
              <Button onClick={() => setInviteDialogOpen(true)}>
                <UserPlus className="mr-2 h-4 w-4" />
                {t('team.page.invite_member')}
              </Button>
            </Can>
          </PageHeaderActions>
        </PageHeader>

        <PageContent>
          {/* Usage Stats */}
          {teamStats.max_users && (
            <div className="bg-muted/50 rounded-lg p-4">
              <p className="text-sm text-muted-foreground">
                {t('team.page.active_members')}: <strong>{teamStats.current_users}</strong> / {teamStats.max_users}
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
                {members.length === 0 && pendingInvitations.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={5} className="text-center py-8 text-muted-foreground">
                      {t('team.page.no_members')}
                    </TableCell>
                  </TableRow>
                ) : (
                  <>
                    {/* Active Members */}
                    {members.map((member) => (
                      <TableRow key={member.id}>
                        <TableCell className="font-medium">{member.name}</TableCell>
                        <TableCell>{member.email}</TableCell>
                        <TableCell>{getRoleBadge(member.role)}</TableCell>
                        <TableCell>
                          <Badge variant="outline" className="bg-green-50 text-green-700 border-green-200">
                            {t('team.page.status_active')}
                          </Badge>
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
                                      {t('team.page.promote_to_admin')}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem onClick={() => handleUpdateRole(member.id, 'member')}>
                                      <Shield className="mr-2 h-4 w-4" />
                                      {t('team.page.set_as_member')}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem onClick={() => handleUpdateRole(member.id, 'guest')}>
                                      <Shield className="mr-2 h-4 w-4" />
                                      {t('team.page.set_as_guest')}
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                  </>
                                )}
                                <DropdownMenuItem
                                  className="text-destructive"
                                  onClick={() => handleRemoveMember(member.id, member.name)}
                                >
                                  <Trash2 className="mr-2 h-4 w-4" />
                                  {t('team.page.remove_from_team')}
                                </DropdownMenuItem>
                              </DropdownMenuContent>
                            </DropdownMenu>
                          </Can>
                        </TableCell>
                      </TableRow>
                    ))}

                    {/* Pending Invitations */}
                    {pendingInvitations.map((invitation) => (
                      <TableRow key={`inv-${invitation.id}`} className="bg-muted/30">
                        <TableCell className="font-medium text-muted-foreground">
                          {t('team.page.pending_user')}
                        </TableCell>
                        <TableCell>{invitation.email}</TableCell>
                        <TableCell>{getRoleBadge(invitation.role)}</TableCell>
                        <TableCell>
                          <Badge variant="outline" className="gap-1">
                            <Mail className="h-3 w-3" />
                            {invitation.is_expired ? (
                              <span className="text-destructive">{t('team.page.invite_expired')}</span>
                            ) : (
                              <>
                                {t('team.page.pending_invite')}
                                {invitation.expires_in_days !== null && (
                                  <span className="text-muted-foreground ml-1">
                                    ({invitation.expires_in_days}d)
                                  </span>
                                )}
                              </>
                            )}
                          </Badge>
                        </TableCell>
                        <TableCell>
                          <Can permission="team:invite">
                            <DropdownMenu>
                              <DropdownMenuTrigger asChild>
                                <Button variant="ghost" size="icon">
                                  <MoreVertical className="h-4 w-4" />
                                </Button>
                              </DropdownMenuTrigger>
                              <DropdownMenuContent align="end">
                                <DropdownMenuLabel>{t('common.actions')}</DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem>
                                  <Mail className="mr-2 h-4 w-4" />
                                  {t('team.page.resend_invite')}
                                </DropdownMenuItem>
                                <DropdownMenuItem className="text-destructive">
                                  <Trash2 className="mr-2 h-4 w-4" />
                                  {t('team.page.cancel_invite')}
                                </DropdownMenuItem>
                              </DropdownMenuContent>
                            </DropdownMenu>
                          </Can>
                        </TableCell>
                      </TableRow>
                    ))}
                  </>
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
