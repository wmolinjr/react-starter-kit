import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Shield, LogOut, KeyRound } from 'lucide-react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { useState } from 'react';
import impersonate from '@/routes/tenant/impersonate';

/**
 * Impersonation data from Stancl/Tenancy v4 native UserImpersonation + Admin Mode.
 *
 * TENANT-ONLY ARCHITECTURE (Option C):
 * - isImpersonating: true when in ANY impersonation mode
 * - isAdminMode: true when in Admin Mode (no specific user impersonated)
 */
interface ImpersonationData {
  isImpersonating: boolean;
  isAdminMode: boolean;
}

export function ImpersonationBanner() {
  const { t } = useLaravelReactI18n();
  const page = usePage();
  const [stopping, setStopping] = useState(false);

  // Check if impersonation data exists in page props (Stancl/Tenancy v4 native)
  const impersonation = (page.props as Record<string, unknown>).impersonation as ImpersonationData | undefined;

  if (!impersonation?.isImpersonating) {
    return null;
  }

  const isAdminMode = impersonation.isAdminMode;

  const handleStopImpersonation = () => {
    setStopping(true);

    // Use tenant route for stopping impersonation (session is tenant-scoped)
    router.post(impersonate.stop.url(), {}, {
      onFinish: () => setStopping(false),
    });
  };

  // Admin Mode uses amber/orange styling
  // User impersonation uses yellow styling
  const bgColor = isAdminMode
    ? 'bg-amber-50 dark:bg-amber-950 border-amber-300 dark:border-amber-800'
    : 'bg-yellow-50 dark:bg-yellow-950 border-yellow-300 dark:border-yellow-800';

  const textColor = isAdminMode
    ? 'text-amber-800 dark:text-amber-200'
    : 'text-yellow-800 dark:text-yellow-200';

  const iconColor = isAdminMode
    ? 'text-amber-600 dark:text-amber-400'
    : 'text-yellow-600 dark:text-yellow-400';

  const buttonClass = isAdminMode
    ? 'border-amber-400 hover:bg-amber-100 dark:hover:bg-amber-900'
    : 'border-yellow-400 hover:bg-yellow-100 dark:hover:bg-yellow-900';

  return (
    <Alert className={`${bgColor} items-center`} data-impersonation-banner>
      {isAdminMode ? (
        <KeyRound className={`h-4 w-4 ${iconColor}`} data-admin-mode-indicator />
      ) : (
        <Shield className={`h-4 w-4 ${iconColor}`} />
      )}
      <AlertDescription className="flex items-center justify-between">
        <span className={`${textColor} font-medium pt-1`}>
          {isAdminMode ? (
            <>
              {t('impersonation.admin.active')}
              {' '}{t('impersonation.admin.notice')}
            </>
          ) : (
            <>
              {t('impersonation.session.active')}
              {' '}{t('impersonation.session.actions_restricted')}
            </>
          )}
        </span>
        <Button
          variant="outline"
          size="sm"
          onClick={handleStopImpersonation}
          disabled={stopping}
          className={`ml-4 ${buttonClass}`}
        >
          <LogOut className="mr-2 h-4 w-4" />
          {stopping ? t('impersonation.session.stopping') : t('impersonation.session.stop')}
        </Button>
      </AlertDescription>
    </Alert>
  );
}
