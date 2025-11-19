import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Shield, LogOut } from 'lucide-react';
import { useState } from 'react';

interface ImpersonationData {
  isImpersonating: boolean;
  impersonatingTenant?: string;
  impersonatingUser?: number;
}

export function ImpersonationBanner() {
  const page = usePage();
  const [stopping, setStopping] = useState(false);

  // Check if impersonation data exists in page props
  const impersonation = (page.props as Record<string, unknown>).impersonation as ImpersonationData | undefined;

  if (!impersonation?.isImpersonating) {
    return null;
  }

  const handleStopImpersonation = () => {
    setStopping(true);

    router.post('/admin/impersonate/stop', {}, {
      onFinish: () => setStopping(false),
    });
  };

  return (
    <Alert className="bg-yellow-50 dark:bg-yellow-950 border-yellow-300 dark:border-yellow-800 mb-6">
      <Shield className="h-4 w-4 text-yellow-600 dark:text-yellow-400" />
      <AlertDescription className="flex items-center justify-between">
        <span className="text-yellow-800 dark:text-yellow-200 font-medium">
          You are currently impersonating this {impersonation.impersonatingUser ? 'user' : 'tenant'}.
          {' '}Some actions are restricted during impersonation.
        </span>
        <Button
          variant="outline"
          size="sm"
          onClick={handleStopImpersonation}
          disabled={stopping}
          className="ml-4 border-yellow-400 hover:bg-yellow-100 dark:hover:bg-yellow-900"
        >
          <LogOut className="mr-2 h-4 w-4" />
          {stopping ? 'Stopping...' : 'Stop Impersonating'}
        </Button>
      </AlertDescription>
    </Alert>
  );
}
