import { Head, router, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import AdminLayout from '@/layouts/tenant/admin-layout';
import admin from '@/routes/tenant/admin';
import { Button } from '@/components/ui/button';
import { Page, PageHeader, PageHeaderContent, PageTitle, PageDescription, PageContent } from '@/components/shared/layout/page';
import { type BreadcrumbItem } from '@/types';
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Check, Download, ExternalLink } from 'lucide-react';

import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import { type ReactElement } from 'react';

interface Plan {
  name: string;
  price_id: string;
  price: string;
  interval: string;
  features: string[];
  limits: {
    max_users: number | null;
    max_projects: number | null;
    storage_mb: number;
  };
}

interface Plans {
  starter: Plan;
  professional: Plan;
  enterprise: Plan;
}

interface Subscription {
  name: string;
  status: string;
  trial_ends_at: string | null;
  ends_at: string | null;
  on_trial: boolean;
  on_grace_period: boolean;
  canceled: boolean;
}

interface Invoice {
  id: string;
  date: string;
  total: string;
  download_url: string;
}

interface BillingPageProps {
  plans: Plans;
  subscription: Subscription | null;
  invoices: Invoice[];
  [key: string]: unknown;
}

function BillingIndex() {
  const { t } = useLaravelReactI18n();
  const { plans, subscription, invoices } = usePage<BillingPageProps>().props;

  const breadcrumbs: BreadcrumbItem[] = [
    { title: t('breadcrumbs.dashboard'), href: admin.dashboard.url() },
    { title: t('tenant.billing.title'), href: admin.billing.index.url() },
  ];

  const handleSubscribe = (planKey: string) => {
    router.post('/billing/checkout', {
      plan: planKey,
    });
  };

  const handleManageSubscription = () => {
    router.get('/billing/portal');
  };

  const getStatusBadge = (status: string) => {
    const statusColors: Record<string, string> = {
      active: 'bg-green-500',
      trialing: 'bg-blue-500',
      past_due: 'bg-yellow-500',
      canceled: 'bg-red-500',
      incomplete: 'bg-gray-500',
    };

    return (
      <Badge className={statusColors[status] || 'bg-gray-500'}>
        {status.charAt(0).toUpperCase() + status.slice(1)}
      </Badge>
    );
  };

  const getCurrentPlan = (): string | null => {
    if (!subscription) return null;

    const planKey = Object.keys(plans).find(
      (key) => plans[key as keyof Plans].price_id === subscription.name
    );

    return planKey || null;
  };

  const currentPlanKey = getCurrentPlan();

  return (
    <>
      <Head title={t('tenant.billing.title')} />

      <Page>
        <PageHeader>
          <PageHeaderContent>
            <PageTitle>{t('tenant.billing.title')}</PageTitle>
            <PageDescription>
              {t('tenant.billing.description')}
            </PageDescription>
          </PageHeaderContent>
        </PageHeader>

        <PageContent>

        {/* Current Subscription */}
        {subscription && (
          <Card>
            <CardHeader>
              <CardTitle>{t('tenant.billing.current_subscription')}</CardTitle>
              <CardDescription>
                {t('tenant.billing.manage_plan')}
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium">Status</p>
                  <div className="mt-1">{getStatusBadge(subscription.status)}</div>
                </div>
                {subscription.on_trial && subscription.trial_ends_at && (
                  <div>
                    <p className="text-sm font-medium">{t('tenant.billing.trial_ends')}</p>
                    <p className="text-sm text-muted-foreground">
                      {subscription.trial_ends_at}
                    </p>
                  </div>
                )}
                {subscription.canceled && subscription.ends_at && (
                  <div>
                    <p className="text-sm font-medium">{t('tenant.billing.subscription_ends')}</p>
                    <p className="text-sm text-muted-foreground">
                      {subscription.ends_at}
                    </p>
                  </div>
                )}
              </div>
            </CardContent>
            <CardFooter>
              <Button onClick={handleManageSubscription} variant="outline">
                <ExternalLink className="mr-2 h-4 w-4" />
                {t('tenant.billing.manage_subscription')}
              </Button>
            </CardFooter>
          </Card>
        )}

        {/* Plans */}
        <div>
          <h2 className="mb-4 text-2xl font-bold">{t('tenant.billing.available_plans')}</h2>
          <div className="grid gap-6 md:grid-cols-3">
            {Object.entries(plans).map(([key, plan]) => {
              const isCurrentPlan = currentPlanKey === key;
              const canSubscribe = !subscription || subscription.canceled;

              return (
                <Card
                  key={key}
                  className={isCurrentPlan ? 'border-primary' : ''}
                >
                  <CardHeader>
                    <div className="flex items-center justify-between">
                      <CardTitle>{plan.name}</CardTitle>
                      {isCurrentPlan && (
                        <Badge variant="secondary">{t('tenant.billing.current_plan')}</Badge>
                      )}
                    </div>
                    <CardDescription>
                      <span className="text-3xl font-bold">{plan.price}</span>
                      <span className="text-muted-foreground">
                        /{plan.interval}
                      </span>
                    </CardDescription>
                  </CardHeader>
                  <CardContent>
                    <ul className="space-y-2">
                      {plan.features.map((feature: string, index: number) => (
                        <li key={index} className="flex items-start">
                          <Check className="mr-2 h-4 w-4 text-primary" />
                          <span className="text-sm">{feature}</span>
                        </li>
                      ))}
                    </ul>
                  </CardContent>
                  <CardFooter>
                    {isCurrentPlan ? (
                      <Button className="w-full" disabled>
                        {t('tenant.billing.current_plan')}
                      </Button>
                    ) : canSubscribe ? (
                      <Button
                        className="w-full"
                        onClick={() => handleSubscribe(key)}
                      >
                        {t('tenant.billing.subscribe')}
                      </Button>
                    ) : (
                      <Button
                        className="w-full"
                        variant="outline"
                        onClick={() => handleSubscribe(key)}
                      >
                        {t('tenant.billing.change_plan')}
                      </Button>
                    )}
                  </CardFooter>
                </Card>
              );
            })}
          </div>
        </div>

        {/* Invoices */}
        {invoices.length > 0 && (
          <Card>
            <CardHeader>
              <CardTitle>{t('tenant.billing.billing_history')}</CardTitle>
              <CardDescription>
                {t('tenant.billing.download_invoices')}
              </CardDescription>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t('tenant.billing.date')}</TableHead>
                    <TableHead>{t('tenant.billing.amount')}</TableHead>
                    <TableHead className="text-right">{t('common.actions')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {invoices.map((invoice) => (
                    <TableRow key={invoice.id}>
                      <TableCell>{invoice.date}</TableCell>
                      <TableCell>{invoice.total}</TableCell>
                      <TableCell className="text-right">
                        <Button
                          variant="ghost"
                          size="sm"
                          asChild
                        >
                          <a href={invoice.download_url}>
                            <Download className="mr-2 h-4 w-4" />
                            {t('tenant.billing.download')}
                          </a>
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        )}
        </PageContent>
      </Page>
    </>
  );
}

BillingIndex.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default BillingIndex;
