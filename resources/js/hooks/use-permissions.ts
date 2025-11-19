import { usePage } from '@inertiajs/react';

export interface Permissions {
  canManageTeam: boolean;
  canManageBilling: boolean;
  canManageSettings: boolean;
  canCreateResources: boolean;
  role: string | null;
  isOwner: boolean;
  isAdmin: boolean;
  isAdminOrOwner: boolean;
}

export function usePermissions(): Permissions {
  const { auth } = usePage<{ auth: { permissions: Permissions | null } }>().props;

  return auth?.permissions || {
    canManageTeam: false,
    canManageBilling: false,
    canManageSettings: false,
    canCreateResources: false,
    role: null,
    isOwner: false,
    isAdmin: false,
    isAdminOrOwner: false,
  };
}
