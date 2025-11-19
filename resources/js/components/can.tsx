import { type ReactNode } from 'react';

import { type Permissions, usePermissions } from '@/hooks/use-permissions';

interface CanProps {
  permission: keyof Permissions;
  children: ReactNode;
  fallback?: ReactNode;
}

export function Can({ permission, children, fallback = null }: CanProps) {
  const permissions = usePermissions();

  if (permissions[permission]) {
    return <>{children}</>;
  }

  return <>{fallback}</>;
}
