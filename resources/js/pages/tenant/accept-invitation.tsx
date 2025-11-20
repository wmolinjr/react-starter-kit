import { useForm } from '@inertiajs/react';
import { Head, Link, usePage } from '@inertiajs/react';
import { Mail, CheckCircle, AlertCircle } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';

interface Props {
  token?: string;
}

export default function AcceptInvitation({ token }: Props) {
  const { auth } = usePage<{ auth: { user: { name: string; email: string } | null } }>().props;
  const isAuthenticated = !!auth.user;

  const { post, processing } = useForm({
    token: token || '',
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
        <Head title="Convite Inválido" />
        <Card className="w-full max-w-md">
          <CardHeader className="text-center">
            <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-destructive/10">
              <AlertCircle className="h-6 w-6 text-destructive" />
            </div>
            <CardTitle>Convite Inválido</CardTitle>
            <CardDescription>O link de convite que você acessou é inválido ou expirou.</CardDescription>
          </CardHeader>
          <CardFooter className="flex justify-center">
            <Button asChild>
              <Link href="/">Voltar para Home</Link>
            </Button>
          </CardFooter>
        </Card>
      </div>
    );
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-50 to-indigo-100 p-4">
      <Head title="Aceitar Convite" />

      <Card className="w-full max-w-md">
        <CardHeader className="text-center">
          <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
            <Mail className="h-6 w-6 text-primary" />
          </div>
          <CardTitle>Você foi convidado!</CardTitle>
          <CardDescription>
            {isAuthenticated
              ? `Olá ${auth.user?.name}, você recebeu um convite para participar de uma organização.`
              : 'Você recebeu um convite para participar de uma organização.'}
          </CardDescription>
        </CardHeader>

        <CardContent className="space-y-4">
          {!isAuthenticated && (
            <Alert>
              <AlertCircle className="h-4 w-4" />
              <AlertDescription>
                Você precisa estar autenticado para aceitar o convite.{' '}
                <Link href={`/login?redirect=/accept-invitation?token=${token}`} className="underline font-medium">
                  Faça login
                </Link>{' '}
                ou{' '}
                <Link href={`/register?redirect=/accept-invitation?token=${token}`} className="underline font-medium">
                  crie uma conta
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
                  <p className="font-medium text-sm">Pronto para começar?</p>
                  <p className="text-sm text-muted-foreground mt-1">
                    Ao aceitar o convite, você terá acesso completo aos recursos da organização de acordo com sua
                    função.
                  </p>
                </div>
              </div>
            </div>
          )}
        </CardContent>

        <CardFooter className="flex gap-2">
          <Button variant="outline" asChild className="flex-1">
            <Link href="/">Cancelar</Link>
          </Button>
          {isAuthenticated && (
            <Button onClick={handleAccept} disabled={processing} className="flex-1">
              {processing ? 'Aceitando...' : 'Aceitar Convite'}
            </Button>
          )}
        </CardFooter>
      </Card>
    </div>
  );
}
