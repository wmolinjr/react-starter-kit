import { useState } from 'react';
import {
    Page,
    PageHeader,
    PageHeaderContent,
    PageTitle,
    PageDescription,
    PageContent,
} from '@/components/shared/layout/page';
import CustomerLayout from '@/layouts/customer-layout';
import { useSetBreadcrumbs } from '@/contexts/breadcrumb-context';
import InputError from '@/components/shared/feedback/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { PlanCard } from '@/components/shared/billing';
import { PricingToggle } from '@/components/shared/billing/primitives';
import { BillingPeriodProvider, useBillingPeriod } from '@/hooks/billing';
import customer from '@/routes/central/account';
import { type BreadcrumbItem, type PlanResource } from '@/types';
import { useForm, Head, Link } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { AlertCircle, Plus, ArrowLeft, ArrowRight, Check, Building2, CreditCard } from 'lucide-react';
import { type ReactElement } from 'react';
import { cn } from '@/lib/utils';

interface TenantCreateProps {
    hasPaymentMethod: boolean;
    plans: PlanResource[];
}

type Step = 'workspace' | 'plan';

function TenantCreateContent({ hasPaymentMethod, plans }: TenantCreateProps) {
    const { t } = useLaravelReactI18n();
    const { period, setPeriod } = useBillingPeriod();
    const [currentStep, setCurrentStep] = useState<Step>('workspace');
    const [selectedPlanId, setSelectedPlanId] = useState<string>('');

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        slug: '',
        plan_id: '',
        billing_period: 'monthly' as 'monthly' | 'yearly',
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('customer.dashboard.title'), href: customer.dashboard.url() },
        { title: t('customer.workspace.title'), href: customer.tenants.index.url() },
        { title: t('customer.workspace.create'), href: customer.tenants.create.url() },
    ];
    useSetBreadcrumbs(breadcrumbs);

    const handleWorkspaceSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (data.name && data.slug) {
            setCurrentStep('plan');
        }
    };

    const handlePlanSelect = (planSlug: string) => {
        const plan = plans.find(p => p.slug === planSlug);
        if (plan) {
            setSelectedPlanId(plan.id);
            setData('plan_id', plan.id);
        }
    };

    const handleFinalSubmit = () => {
        setData('billing_period', period);
        post(customer.tenants.store.url());
    };

    const generateSlug = (name: string) => {
        return name
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .substring(0, 63);
    };

    const handleNameChange = (value: string) => {
        setData('name', value);
        if (!data.slug || data.slug === generateSlug(data.name)) {
            setData('slug', generateSlug(value));
        }
    };

    const steps = [
        { id: 'workspace', label: t('customer.workspace.details'), icon: Building2 },
        { id: 'plan', label: t('customer.subscription.select_plan'), icon: CreditCard },
    ];

    return (
        <>
            <Head title={t('customer.workspace.create')} />

            <Page>
                <PageHeader>
                    <PageHeaderContent>
                        <PageTitle icon={Plus}>
                            {t('customer.workspace.create')}
                        </PageTitle>
                        <PageDescription>
                            {t('customer.workspace.create_description')}
                        </PageDescription>
                    </PageHeaderContent>
                </PageHeader>

                <PageContent>
                    {/* Progress Steps */}
                    <div className="mb-8">
                        <div className="flex items-center justify-center">
                            {steps.map((step, index) => (
                                <div key={step.id} className="flex items-center">
                                    <div
                                        className={cn(
                                            'flex items-center justify-center w-10 h-10 rounded-full border-2 transition-colors',
                                            currentStep === step.id
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : steps.findIndex(s => s.id === currentStep) > index
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : 'border-muted-foreground/30 text-muted-foreground'
                                        )}
                                    >
                                        {steps.findIndex(s => s.id === currentStep) > index ? (
                                            <Check className="h-5 w-5" />
                                        ) : (
                                            <step.icon className="h-5 w-5" />
                                        )}
                                    </div>
                                    <span
                                        className={cn(
                                            'ml-2 text-sm font-medium',
                                            currentStep === step.id
                                                ? 'text-foreground'
                                                : 'text-muted-foreground'
                                        )}
                                    >
                                        {step.label}
                                    </span>
                                    {index < steps.length - 1 && (
                                        <div
                                            className={cn(
                                                'w-16 h-0.5 mx-4',
                                                steps.findIndex(s => s.id === currentStep) > index
                                                    ? 'bg-primary'
                                                    : 'bg-muted-foreground/30'
                                            )}
                                        />
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Step 1: Workspace Details */}
                    {currentStep === 'workspace' && (
                        <div className="max-w-2xl mx-auto">
                            {!hasPaymentMethod && (
                                <Card className="border-warning bg-warning/10 mb-6">
                                    <CardContent className="flex items-center gap-4 py-4">
                                        <AlertCircle className="h-5 w-5 text-warning" />
                                        <div>
                                            <p className="font-medium">{t('customer.payment.method_required')}</p>
                                            <p className="text-sm text-muted-foreground">
                                                {t('customer.payment.add_method_first')}
                                            </p>
                                        </div>
                                        <Button asChild variant="outline" className="ml-auto">
                                            <Link href={customer.paymentMethods.create.url()}>
                                                {t('customer.payment.add_method')}
                                            </Link>
                                        </Button>
                                    </CardContent>
                                </Card>
                            )}

                            <Card>
                                <CardHeader>
                                    <CardTitle>{t('customer.workspace.details')}</CardTitle>
                                    <CardDescription>
                                        {t('customer.workspace.details_description')}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <form onSubmit={handleWorkspaceSubmit} className="space-y-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="name">{t('customer.workspace.name')}</Label>
                                            <Input
                                                id="name"
                                                value={data.name}
                                                onChange={(e) => handleNameChange(e.target.value)}
                                                required
                                                autoFocus
                                                placeholder={t('customer.workspace.name_placeholder')}
                                            />
                                            <InputError message={errors?.name} />
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="slug">{t('customer.workspace.slug')}</Label>
                                            <div className="flex items-center">
                                                <Input
                                                    id="slug"
                                                    value={data.slug}
                                                    onChange={(e) => setData('slug', e.target.value)}
                                                    required
                                                    placeholder="my-company"
                                                    className="rounded-r-none"
                                                />
                                                <span className="inline-flex items-center px-3 border border-l-0 border-input bg-muted text-muted-foreground rounded-r-md text-sm">
                                                    .{window.location.hostname.split('.').slice(-2).join('.')}
                                                </span>
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                {t('customer.workspace.slug_help')}
                                            </p>
                                            <InputError message={errors?.slug} />
                                        </div>

                                        <div className="flex gap-4 pt-4">
                                            <Button
                                                type="submit"
                                                disabled={!data.name || !data.slug || !hasPaymentMethod}
                                            >
                                                {t('common.next')}
                                                <ArrowRight className="ml-2 h-4 w-4" />
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() => window.history.back()}
                                            >
                                                {t('common.cancel')}
                                            </Button>
                                        </div>
                                    </form>
                                </CardContent>
                            </Card>
                        </div>
                    )}

                    {/* Step 2: Plan Selection */}
                    {currentStep === 'plan' && (
                        <div className="space-y-6">
                            {/* Back Button */}
                            <div className="flex items-center gap-4">
                                <Button
                                    variant="ghost"
                                    onClick={() => setCurrentStep('workspace')}
                                >
                                    <ArrowLeft className="mr-2 h-4 w-4" />
                                    {t('common.back')}
                                </Button>
                                <div className="text-sm text-muted-foreground">
                                    {t('customer.workspace.creating_for', { name: data.name })}
                                </div>
                            </div>

                            {/* Billing Period Toggle */}
                            <div className="flex justify-center">
                                <PricingToggle
                                    value={period}
                                    onChange={setPeriod}
                                    savings={t('billing.price.yearly_savings', { default: 'Save 20%' })}
                                />
                            </div>

                            {/* Plan Cards Grid */}
                            <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                                {plans.map((plan) => (
                                    <PlanCard
                                        key={plan.slug}
                                        plan={plan}
                                        billingPeriod={period}
                                        currentPlanSlug={selectedPlanId === plan.id ? plan.slug : undefined}
                                        onSelect={handlePlanSelect}
                                        ctaLabel={selectedPlanId === plan.id
                                            ? t('billing.plan.selected')
                                            : t('billing.plan.select')
                                        }
                                        showFeatures
                                    />
                                ))}
                            </div>

                            {/* Continue Button */}
                            {selectedPlanId && (
                                <div className="flex justify-center pt-4">
                                    <Button
                                        size="lg"
                                        onClick={handleFinalSubmit}
                                        disabled={processing}
                                    >
                                        {processing && <Spinner className="mr-2" />}
                                        {t('billing.page.continue_to_checkout')}
                                        <ArrowRight className="ml-2 h-4 w-4" />
                                    </Button>
                                </div>
                            )}
                        </div>
                    )}
                </PageContent>
            </Page>
        </>
    );
}

function TenantCreate(props: TenantCreateProps) {
    return (
        <BillingPeriodProvider>
            <TenantCreateContent {...props} />
        </BillingPeriodProvider>
    );
}

TenantCreate.layout = (page: ReactElement) => <CustomerLayout>{page}</CustomerLayout>;

export default TenantCreate;
