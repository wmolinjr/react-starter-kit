import { type ReactElement, useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    CreditCard,
    Crown,
    Pause,
    Play,
    XCircle,
    ArrowRight,
    AlertTriangle,
    CheckCircle2,
    Clock,
    Calendar,
    Loader2,
} from 'lucide-react';

import AppLayout from '@/layouts/app-layout';
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
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Label } from '@/components/ui/label';
import {
    Page,
    PageHeader,
    PageHeaderContent,
    PageTitle,
    PageDescription,
    PageContent,
} from '@/components/shared/layout/page';
import type { BreadcrumbItem } from '@/types';
import type { PlanResource, SubscriptionInfo } from '@/types';

interface Props {
    subscription: SubscriptionInfo | null;
    plan: PlanResource | null;
    plans: PlanResource[];
    canPause: boolean;
    canResume: boolean;
    canCancel: boolean;
    canChangePlan: boolean;
}

function SubscriptionPage({
    subscription,
    plan,
    plans,
    canPause,
    canResume,
    canCancel,
    canChangePlan,
}: Props) {
    const { t } = useLaravelReactI18n();
    const [isProcessing, setIsProcessing] = useState(false);
    const [selectedPlan, setSelectedPlan] = useState<string | null>(null);
    const [cancelImmediately, setCancelImmediately] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('sidebar.group.billing'), href: '/admin/billing' },
        { title: t('billing.subscription_management'), href: '/admin/billing/subscription' },
    ];

    // Format date
    const formatDate = (dateStr: string | null | undefined): string => {
        if (!dateStr) return '-';
        return new Date(dateStr).toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    };

    // Get status badge
    const getStatusBadge = () => {
        if (!subscription) {
            return (
                <Badge variant="outline">
                    {t('billing.no_subscription')}
                </Badge>
            );
        }

        switch (subscription.status) {
            case 'active':
                return (
                    <Badge variant="default" className="gap-1 bg-green-600">
                        <CheckCircle2 className="h-3 w-3" />
                        {t('billing.status.active')}
                    </Badge>
                );
            case 'trialing':
                return (
                    <Badge variant="outline" className="gap-1 border-amber-500 text-amber-600">
                        <Clock className="h-3 w-3" />
                        {t('billing.status.trial')}
                    </Badge>
                );
            case 'paused':
                return (
                    <Badge variant="secondary" className="gap-1">
                        <Pause className="h-3 w-3" />
                        {t('billing.status.paused')}
                    </Badge>
                );
            case 'past_due':
                return (
                    <Badge variant="destructive" className="gap-1">
                        <AlertTriangle className="h-3 w-3" />
                        {t('billing.status.past_due')}
                    </Badge>
                );
            case 'canceled':
                return (
                    <Badge variant="outline" className="gap-1">
                        <XCircle className="h-3 w-3" />
                        {t('billing.status.canceled')}
                    </Badge>
                );
            default:
                return (
                    <Badge variant="outline">
                        {subscription.status}
                    </Badge>
                );
        }
    };

    // Handle pause subscription
    const handlePause = () => {
        setIsProcessing(true);
        router.post('/admin/billing/subscription/pause', {}, {
            onFinish: () => setIsProcessing(false),
        });
    };

    // Handle unpause subscription
    const handleUnpause = () => {
        setIsProcessing(true);
        router.post('/admin/billing/subscription/unpause', {}, {
            onFinish: () => setIsProcessing(false),
        });
    };

    // Handle resume subscription (from grace period)
    const handleResume = () => {
        setIsProcessing(true);
        router.post('/admin/billing/subscription/resume', {}, {
            onFinish: () => setIsProcessing(false),
        });
    };

    // Handle cancel subscription
    const handleCancel = () => {
        setIsProcessing(true);
        router.post('/admin/billing/subscription/cancel', {
            immediately: cancelImmediately,
        }, {
            onFinish: () => setIsProcessing(false),
        });
    };

    // Handle plan change
    const handleChangePlan = () => {
        if (!selectedPlan) return;
        setIsProcessing(true);
        router.post('/admin/billing/plan/change', {
            plan: selectedPlan,
        }, {
            onFinish: () => setIsProcessing(false),
        });
    };

    const isOnGracePeriod = subscription?.endsAt && new Date(subscription.endsAt) > new Date();
    const isPaused = subscription?.status === 'paused';

    return (
        <>
            <Head title={t('billing.subscription_management')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={CreditCard}>
                            {t('billing.subscription_management')}
                        </PageTitle>
                        <PageDescription>
                            {t('billing.subscription_management_description')}
                        </PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    <div className="space-y-6">
                        {/* Current Subscription Status */}
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <CardTitle className="flex items-center gap-2">
                                        <Crown className="h-5 w-5" />
                                        {t('billing.current_subscription')}
                                    </CardTitle>
                                    {getStatusBadge()}
                                </div>
                                <CardDescription>
                                    {plan?.name || t('billing.plan.no_plan')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {subscription && (
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="bg-muted/50 rounded-lg p-4">
                                            <div className="flex items-center gap-2 text-sm text-muted-foreground mb-1">
                                                <Calendar className="h-4 w-4" />
                                                {t('billing.billing_period')}
                                            </div>
                                            <p className="font-medium">
                                                {formatDate(subscription.currentPeriodStart)} - {formatDate(subscription.currentPeriodEnd)}
                                            </p>
                                        </div>

                                        {subscription.trialEndsAt && (
                                            <div className="bg-amber-50 dark:bg-amber-950/20 rounded-lg p-4 border border-amber-200 dark:border-amber-800">
                                                <div className="flex items-center gap-2 text-sm text-amber-600 mb-1">
                                                    <Clock className="h-4 w-4" />
                                                    {t('billing.trial_ends')}
                                                </div>
                                                <p className="font-medium text-amber-700 dark:text-amber-400">
                                                    {formatDate(subscription.trialEndsAt)}
                                                </p>
                                            </div>
                                        )}

                                        {isOnGracePeriod && (
                                            <div className="bg-red-50 dark:bg-red-950/20 rounded-lg p-4 border border-red-200 dark:border-red-800 sm:col-span-2">
                                                <div className="flex items-center gap-2 text-sm text-red-600 mb-1">
                                                    <AlertTriangle className="h-4 w-4" />
                                                    {t('billing.access_ends')}
                                                </div>
                                                <p className="font-medium text-red-700 dark:text-red-400">
                                                    {formatDate(subscription.endsAt)}
                                                </p>
                                                <p className="text-sm text-red-600 mt-2">
                                                    {t('billing.grace_period_message')}
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Subscription Actions */}
                        {subscription && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>{t('billing.subscription_actions')}</CardTitle>
                                    <CardDescription>
                                        {t('billing.subscription_actions_description')}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {/* Pause/Unpause */}
                                    {(canPause || isPaused) && (
                                        <div className="flex items-center justify-between p-4 border rounded-lg">
                                            <div>
                                                <h4 className="font-medium">
                                                    {isPaused
                                                        ? t('billing.resume_subscription')
                                                        : t('billing.pause_subscription')
                                                    }
                                                </h4>
                                                <p className="text-sm text-muted-foreground">
                                                    {isPaused
                                                        ? t('billing.resume_subscription_description')
                                                        : t('billing.pause_subscription_description')
                                                    }
                                                </p>
                                            </div>
                                            <Button
                                                variant={isPaused ? 'default' : 'outline'}
                                                onClick={isPaused ? handleUnpause : handlePause}
                                                disabled={isProcessing}
                                            >
                                                {isProcessing ? (
                                                    <Loader2 className="h-4 w-4 animate-spin" />
                                                ) : isPaused ? (
                                                    <>
                                                        <Play className="mr-2 h-4 w-4" />
                                                        {t('billing.resume')}
                                                    </>
                                                ) : (
                                                    <>
                                                        <Pause className="mr-2 h-4 w-4" />
                                                        {t('billing.pause')}
                                                    </>
                                                )}
                                            </Button>
                                        </div>
                                    )}

                                    {/* Resume from Grace Period */}
                                    {canResume && isOnGracePeriod && (
                                        <div className="flex items-center justify-between p-4 border rounded-lg border-green-200 bg-green-50 dark:bg-green-950/20">
                                            <div>
                                                <h4 className="font-medium text-green-700 dark:text-green-400">
                                                    {t('billing.resume_subscription')}
                                                </h4>
                                                <p className="text-sm text-green-600">
                                                    {t('billing.resume_before_expiry')}
                                                </p>
                                            </div>
                                            <Button
                                                variant="default"
                                                className="bg-green-600 hover:bg-green-700"
                                                onClick={handleResume}
                                                disabled={isProcessing}
                                            >
                                                {isProcessing ? (
                                                    <Loader2 className="h-4 w-4 animate-spin" />
                                                ) : (
                                                    <>
                                                        <Play className="mr-2 h-4 w-4" />
                                                        {t('billing.resume_subscription')}
                                                    </>
                                                )}
                                            </Button>
                                        </div>
                                    )}

                                    {/* Cancel Subscription */}
                                    {canCancel && !isOnGracePeriod && (
                                        <div className="flex items-center justify-between p-4 border rounded-lg border-red-200">
                                            <div>
                                                <h4 className="font-medium">
                                                    {t('billing.cancel_subscription')}
                                                </h4>
                                                <p className="text-sm text-muted-foreground">
                                                    {t('billing.cancel_subscription_description')}
                                                </p>
                                            </div>
                                            <AlertDialog>
                                                <AlertDialogTrigger asChild>
                                                    <Button variant="destructive" disabled={isProcessing}>
                                                        <XCircle className="mr-2 h-4 w-4" />
                                                        {t('common.cancel')}
                                                    </Button>
                                                </AlertDialogTrigger>
                                                <AlertDialogContent>
                                                    <AlertDialogHeader>
                                                        <AlertDialogTitle>
                                                            {t('billing.cancel_subscription_confirm_title')}
                                                        </AlertDialogTitle>
                                                        <AlertDialogDescription>
                                                            {t('billing.cancel_subscription_confirm_description')}
                                                        </AlertDialogDescription>
                                                    </AlertDialogHeader>
                                                    <div className="py-4">
                                                        <RadioGroup
                                                            value={cancelImmediately ? 'immediately' : 'end_of_period'}
                                                            onValueChange={(value) => setCancelImmediately(value === 'immediately')}
                                                        >
                                                            <div className="flex items-start space-x-3 p-3 border rounded-lg">
                                                                <RadioGroupItem value="end_of_period" id="end_of_period" className="mt-1" />
                                                                <Label htmlFor="end_of_period" className="cursor-pointer">
                                                                    <div className="font-medium">{t('billing.cancel_at_period_end')}</div>
                                                                    <div className="text-sm text-muted-foreground">
                                                                        {t('billing.cancel_at_period_end_description')}
                                                                    </div>
                                                                </Label>
                                                            </div>
                                                            <div className="flex items-start space-x-3 p-3 border rounded-lg border-red-200">
                                                                <RadioGroupItem value="immediately" id="immediately" className="mt-1" />
                                                                <Label htmlFor="immediately" className="cursor-pointer">
                                                                    <div className="font-medium text-red-600">{t('billing.cancel_immediately')}</div>
                                                                    <div className="text-sm text-muted-foreground">
                                                                        {t('billing.cancel_immediately_description')}
                                                                    </div>
                                                                </Label>
                                                            </div>
                                                        </RadioGroup>
                                                    </div>
                                                    <AlertDialogFooter>
                                                        <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
                                                        <AlertDialogAction
                                                            onClick={handleCancel}
                                                            className="bg-red-600 hover:bg-red-700"
                                                        >
                                                            {t('billing.confirm_cancellation')}
                                                        </AlertDialogAction>
                                                    </AlertDialogFooter>
                                                </AlertDialogContent>
                                            </AlertDialog>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        )}

                        {/* Change Plan */}
                        {canChangePlan && plans.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>{t('billing.change_plan')}</CardTitle>
                                    <CardDescription>
                                        {t('billing.change_plan_description')}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-4">
                                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                            {plans.map((p) => (
                                                <div
                                                    key={p.slug}
                                                    className={`relative p-4 border rounded-lg cursor-pointer transition-all ${
                                                        selectedPlan === p.slug
                                                            ? 'border-primary ring-2 ring-primary/20'
                                                            : 'hover:border-primary/50'
                                                    } ${plan?.slug === p.slug ? 'bg-muted/50' : ''}`}
                                                    onClick={() => setSelectedPlan(p.slug)}
                                                >
                                                    {plan?.slug === p.slug && (
                                                        <Badge className="absolute -top-2 -right-2" variant="secondary">
                                                            {t('billing.current')}
                                                        </Badge>
                                                    )}
                                                    <h4 className="font-semibold">{p.name}</h4>
                                                    <p className="text-2xl font-bold mt-2">
                                                        {p.formatted_price}
                                                        <span className="text-sm font-normal text-muted-foreground">
                                                            /{p.billing_period === 'yearly' ? t('billing.year') : t('billing.month')}
                                                        </span>
                                                    </p>
                                                    {p.description && (
                                                        <p className="text-sm text-muted-foreground mt-2">
                                                            {p.description}
                                                        </p>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                </CardContent>
                                <CardFooter>
                                    <Button
                                        onClick={handleChangePlan}
                                        disabled={!selectedPlan || selectedPlan === plan?.slug || isProcessing}
                                    >
                                        {isProcessing ? (
                                            <Loader2 className="h-4 w-4 animate-spin mr-2" />
                                        ) : (
                                            <ArrowRight className="h-4 w-4 mr-2" />
                                        )}
                                        {t('billing.change_to_plan')}
                                    </Button>
                                </CardFooter>
                            </Card>
                        )}

                        {/* No Subscription State */}
                        {!subscription && (
                            <Alert>
                                <AlertTriangle className="h-4 w-4" />
                                <AlertTitle>{t('billing.no_subscription')}</AlertTitle>
                                <AlertDescription>
                                    {t('billing.no_subscription_description')}
                                </AlertDescription>
                            </Alert>
                        )}
                    </div>
                </PageContent>
            </Page>
        </>
    );
}

SubscriptionPage.layout = (page: ReactElement<Props>) => (
    <AppLayout>{page}</AppLayout>
);

export default SubscriptionPage;
