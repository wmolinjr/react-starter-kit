import { Head, router } from '@inertiajs/react';
import CentralLayout from '@/layouts/central-layout';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Users, LogIn, Shield } from 'lucide-react';
import { useState } from 'react';

interface User {
  id: number;
  name: string;
  email: string;
}

interface Tenant {
  id: string;
  name: string;
  domain: string;
  created_at: string;
  users_count: number;
  users: User[];
}

interface PaginatedTenants {
  data: Tenant[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

interface AdminDashboardProps {
  tenants: PaginatedTenants;
  isImpersonating: boolean;
  impersonatingTenant: string | null;
  impersonatingUser: number | null;
}

export default function AdminDashboard({
  tenants,
  isImpersonating,
}: AdminDashboardProps) {
  const [impersonating, setImpersonating] = useState<string | null>(null);

  const handleImpersonate = (tenantId: string, userId?: number) => {
    setImpersonating(tenantId);

    const url = userId
      ? `/admin/impersonate/tenant/${tenantId}/user/${userId}`
      : `/admin/impersonate/tenant/${tenantId}`;

    router.post(url, {}, {
      onFinish: () => setImpersonating(null),
    });
  };

  return (
    <CentralLayout>
      <Head title="Admin Dashboard" />

      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight flex items-center gap-2">
              <Shield className="h-8 w-8" />
              Super Admin Dashboard
            </h1>
            <p className="text-muted-foreground">
              Manage tenants and impersonate users
            </p>
          </div>

          {isImpersonating && (
            <Badge variant="destructive" className="text-sm">
              Currently Impersonating
            </Badge>
          )}
        </div>

        {/* Stats Card */}
        <Card>
          <CardHeader>
            <CardTitle>System Overview</CardTitle>
            <CardDescription>Total tenants and users</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <p className="text-2xl font-bold">{tenants.total}</p>
                <p className="text-sm text-muted-foreground">Total Tenants</p>
              </div>
              <div>
                <p className="text-2xl font-bold">
                  {tenants.data.reduce((sum, t) => sum + t.users_count, 0)}
                </p>
                <p className="text-sm text-muted-foreground">Total Users</p>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Tenants List */}
        <Card>
          <CardHeader>
            <CardTitle>Tenants</CardTitle>
            <CardDescription>
              Click impersonate to access a tenant's account
            </CardDescription>
          </CardHeader>
          <CardContent>
            {tenants.data.length === 0 ? (
              <p className="text-center text-muted-foreground py-8">
                No tenants found.
              </p>
            ) : (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Name</TableHead>
                    <TableHead>Domain</TableHead>
                    <TableHead>Users</TableHead>
                    <TableHead>Created</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {tenants.data.map((tenant) => (
                    <TableRow key={tenant.id}>
                      <TableCell className="font-medium">{tenant.name}</TableCell>
                      <TableCell>
                        <code className="text-xs">{tenant.domain}</code>
                      </TableCell>
                      <TableCell>
                        <div className="flex items-center gap-2">
                          <Users className="h-4 w-4" />
                          {tenant.users_count}
                        </div>
                      </TableCell>
                      <TableCell>{tenant.created_at}</TableCell>
                      <TableCell className="text-right">
                        <div className="flex justify-end gap-2">
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => handleImpersonate(tenant.id)}
                            disabled={impersonating === tenant.id}
                          >
                            <LogIn className="mr-2 h-4 w-4" />
                            {impersonating === tenant.id
                              ? 'Impersonating...'
                              : 'Impersonate Tenant'}
                          </Button>

                          {tenant.users.length > 0 && (
                            <div className="flex gap-1">
                              {tenant.users.slice(0, 3).map((user) => (
                                <Button
                                  key={user.id}
                                  variant="ghost"
                                  size="sm"
                                  onClick={() => handleImpersonate(tenant.id, user.id)}
                                  disabled={impersonating === tenant.id}
                                  title={`Impersonate ${user.name}`}
                                >
                                  <LogIn className="mr-1 h-3 w-3" />
                                  {user.name.split(' ')[0]}
                                </Button>
                              ))}
                            </div>
                          )}
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}

            {/* Pagination */}
            {tenants.last_page > 1 && (
              <div className="flex items-center justify-between mt-4">
                <p className="text-sm text-muted-foreground">
                  Showing {tenants.data.length} of {tenants.total} tenants
                </p>
                <div className="flex gap-2">
                  {tenants.current_page > 1 && (
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() =>
                        router.get(`/admin/dashboard?page=${tenants.current_page - 1}`)
                      }
                    >
                      Previous
                    </Button>
                  )}
                  {tenants.current_page < tenants.last_page && (
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() =>
                        router.get(`/admin/dashboard?page=${tenants.current_page + 1}`)
                      }
                    >
                      Next
                    </Button>
                  )}
                </div>
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </CentralLayout>
  );
}
