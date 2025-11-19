import { Head, router, usePage } from '@inertiajs/react';
import { AppLayout } from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
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
}

export default function BillingIndex() {
  const { plans, subscription, invoices } = usePage<BillingPageProps>().props;

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
    <AppLayout>
      <Head title="Billing" />

      <div className="space-y-8">
        {/* Header */}
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Billing</h1>
          <p className="text-muted-foreground">
            Manage your subscription and billing information
          </p>
        </div>

        {/* Current Subscription */}
        {subscription && (
          <Card>
            <CardHeader>
              <CardTitle>Current Subscription</CardTitle>
              <CardDescription>
                Manage your current plan and billing settings
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
                    <p className="text-sm font-medium">Trial Ends</p>
                    <p className="text-sm text-muted-foreground">
                      {subscription.trial_ends_at}
                    </p>
                  </div>
                )}
                {subscription.canceled && subscription.ends_at && (
                  <div>
                    <p className="text-sm font-medium">Subscription Ends</p>
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
                Manage Subscription
              </Button>
            </CardFooter>
          </Card>
        )}

        {/* Plans */}
        <div>
          <h2 className="mb-4 text-2xl font-bold">Available Plans</h2>
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
                        <Badge variant="secondary">Current Plan</Badge>
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
                      {plan.features.map((feature, index) => (
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
                        Current Plan
                      </Button>
                    ) : canSubscribe ? (
                      <Button
                        className="w-full"
                        onClick={() => handleSubscribe(key)}
                      >
                        Subscribe
                      </Button>
                    ) : (
                      <Button
                        className="w-full"
                        variant="outline"
                        onClick={() => handleSubscribe(key)}
                      >
                        Change Plan
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
              <CardTitle>Billing History</CardTitle>
              <CardDescription>
                Download your past invoices and receipts
              </CardDescription>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Date</TableHead>
                    <TableHead>Amount</TableHead>
                    <TableHead className="text-right">Action</TableHead>
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
                            Download
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
      </div>
    </AppLayout>
  );
}
