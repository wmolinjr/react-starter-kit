import { useForm } from '@inertiajs/react';
import { Head, Link, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Mail, CheckCircle, AlertCircle } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';

interface Props {
  token?: string;
}

export default function AcceptInvitation({ token }: Props) {
  const { t } = useLaravelReactI18n();
  const { auth } = usePage<{ auth: { user: { name: string; email: string } | null } }>().props;
  const isAuthenticated = !!auth.user;

  const { post, processing } = useForm<{ token: string }>({
    token: token ?? '',
  });

  const handleAccept = () => {
    post('/accept-invitation', {
      onSuccess: () => {
        // Redirect will be handled by controller
      },
    });
  };

  if (!token) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-50 to-gray-100 p-4">
        <Head title={t('tenant.invitation.invalid_title')} />
        <Card className="w-full max-w-md">
          <CardHeader className="text-center">
            <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-destructive/10">
              <AlertCircle className="h-6 w-6 text-destructive" />
            </div>
            <CardTitle>{t('tenant.invitation.invalid_title')}</CardTitle>
            <CardDescription>{t('tenant.invitation.invalid_description')}</CardDescription>
          </CardHeader>
          <CardFooter className="flex justify-center">
            <Button asChild>
              <Link href="/">{t('tenant.invitation.back_home')}</Link>
            </Button>
          </CardFooter>
        </Card>
      </div>
    );
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-50 to-indigo-100 p-4">
      <Head title={t('tenant.invitation.accept_title')} />

      <Card className="w-full max-w-md">
        <CardHeader className="text-center">
          <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
            <Mail className="h-6 w-6 text-primary" />
          </div>
          <CardTitle>{t('tenant.invitation.you_were_invited')}</CardTitle>
          <CardDescription>
            {isAuthenticated
              ? t('tenant.invitation.hello_user', { name: auth.user?.name ?? '' })
              : t('tenant.invitation.received_invite')}
          </CardDescription>
        </CardHeader>

        <CardContent className="space-y-4">
          {!isAuthenticated && (
            <Alert>
              <AlertCircle className="h-4 w-4" />
              <AlertDescription>
                {t('tenant.invitation.auth_required')}{' '}
                <Link href={`/login?redirect=/accept-invitation?token=${token}`} className="underline font-medium">
                  {t('tenant.invitation.login')}
                </Link>{' '}
                {t('tenant.invitation.or')}{' '}
                <Link href={`/register?redirect=/accept-invitation?token=${token}`} className="underline font-medium">
                  {t('tenant.invitation.create_account')}
                </Link>
                .
              </AlertDescription>
            </Alert>
          )}

          {isAuthenticated && (
            <div className="bg-muted rounded-lg p-4">
              <div className="flex items-start gap-3">
                <CheckCircle className="h-5 w-5 text-green-600 mt-0.5" />
                <div className="flex-1">
                  <p className="font-medium text-sm">{t('tenant.invitation.ready_to_start')}</p>
                  <p className="text-sm text-muted-foreground mt-1">
                    {t('tenant.invitation.access_info')}
                  </p>
                </div>
              </div>
            </div>
          )}
        </CardContent>

        <CardFooter className="flex gap-2">
          <Button variant="outline" asChild className="flex-1">
            <Link href="/">{t('common.cancel')}</Link>
          </Button>
          {isAuthenticated && (
            <Button onClick={handleAccept} disabled={processing} className="flex-1">
              {processing ? t('tenant.invitation.accepting') : t('tenant.invitation.accept_button')}
            </Button>
          )}
        </CardFooter>
      </Card>
    </div>
  );
}
