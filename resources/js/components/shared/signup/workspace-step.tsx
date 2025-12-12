import { useState, useEffect, useCallback } from 'react';
import { router, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ArrowLeft } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import InputError from '@/components/shared/feedback/input-error';
import { PricingToggle } from '@/components/shared/billing/primitives/pricing-toggle';
import { PlanCard } from '@/components/shared/billing/plans/plan-card';
import { update as updateWorkspace } from '@/routes/central/signup/workspace';
import type { PlanResource, PendingSignupResource } from '@/types/resources';
import type { PageProps } from '@/types';
import { cn } from '@/lib/utils';

interface BusinessSectorOption {
    value: string;
    label: string;
}

interface WorkspaceStepProps {
    signup: PendingSignupResource;
    plans: PlanResource[];
    businessSectors: BusinessSectorOption[];
    selectedPlanSlug: string;
    billingPeriod: 'monthly' | 'yearly';
    onPlanChange: (slug: string) => void;
    onBillingPeriodChange: (period: 'monthly' | 'yearly') => void;
    onSuccess: (signup: PendingSignupResource) => void;
    onBack: () => void;
}

// Generate slug from workspace name
function generateSlug(name: string): string {
    return name
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '') // Remove accents
        .replace(/[^a-z0-9]+/g, '-') // Replace non-alphanumeric with -
        .replace(/^-+|-+$/g, '') // Trim leading/trailing -
        .substring(0, 50);
}

export function WorkspaceStep({
    signup,
    plans,
    businessSectors,
    selectedPlanSlug,
    billingPeriod,
    onPlanChange,
    onBillingPeriodChange,
    onSuccess,
    onBack,
}: WorkspaceStepProps) {
    const { t } = useLaravelReactI18n();
    const { props } = usePage<PageProps>();
    const { errors = {} } = props;
    const [isLoading, setIsLoading] = useState(false);
    const [formData, setFormData] = useState({
        workspace_name: signup.workspace_name || '',
        workspace_slug: signup.workspace_slug || '',
        business_sector: signup.business_sector || '',
        plan_id: plans.find((p) => p.slug === selectedPlanSlug)?.id || plans[0]?.id || '',
        billing_period: billingPeriod,
    });

    // Watch for flash data changes (pendingSignup from server)
    const handleFlashSuccess = useCallback(() => {
        const pendingSignup = props.flash?.pendingSignup as PendingSignupResource | undefined;
        // Only trigger if we have a signup with workspace data (meaning workspace step was completed)
        if (pendingSignup && pendingSignup.id && pendingSignup.workspace_slug) {
            onSuccess(pendingSignup);
        }
    }, [props.flash?.pendingSignup, onSuccess]);

    useEffect(() => {
        handleFlashSuccess();
    }, [handleFlashSuccess]);

    // Auto-generate slug from workspace name
    useEffect(() => {
        if (formData.workspace_name && !signup.workspace_slug) {
            const newSlug = generateSlug(formData.workspace_name);
            setFormData((prev) => ({ ...prev, workspace_slug: newSlug }));
        }
    }, [formData.workspace_name, signup.workspace_slug]);

    // Update plan_id when selection changes
    useEffect(() => {
        const plan = plans.find((p) => p.slug === selectedPlanSlug);
        if (plan) {
            setFormData((prev) => ({ ...prev, plan_id: plan.id }));
        }
    }, [selectedPlanSlug, plans]);

    // Update billing period
    useEffect(() => {
        setFormData((prev) => ({ ...prev, billing_period: billingPeriod }));
    }, [billingPeriod]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setIsLoading(true);

        router.patch(updateWorkspace.url({ signup: signup.id }), formData, {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                setIsLoading(false);
            },
        });
    };

    return (
        <div className="space-y-8">
            {/* Workspace Form */}
            <Card>
                <CardHeader className="text-center">
                    <CardTitle className="text-2xl">
                        {t('signup.workspace.title', { default: 'Set up your workspace' })}
                    </CardTitle>
                    <CardDescription>
                        {t('signup.workspace.description', {
                            default: 'Configure your workspace details',
                        })}
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <form onSubmit={handleSubmit} className="space-y-6" noValidate>
                        <div className="space-y-2">
                            <Label htmlFor="workspace_name">
                                {t('signup.workspace.name', { default: 'Workspace Name' })}
                            </Label>
                            <Input
                                id="workspace_name"
                                type="text"
                                value={formData.workspace_name}
                                onChange={(e) =>
                                    setFormData((prev) => ({
                                        ...prev,
                                        workspace_name: e.target.value,
                                    }))
                                }
                                placeholder={t('signup.workspace.name_placeholder', {
                                    default: 'My Company',
                                })}
                            />
                            <InputError message={errors.workspace_name} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="workspace_slug">
                                {t('signup.workspace.slug', { default: 'Workspace URL' })}
                            </Label>
                            <div className="flex items-center gap-2">
                                <div className="bg-muted text-muted-foreground rounded-l-md border border-r-0 px-3 py-2 text-sm">
                                    {window.location.host.replace('app.', '')}
                                    {'/'}
                                </div>
                                <Input
                                    id="workspace_slug"
                                    type="text"
                                    value={formData.workspace_slug}
                                    onChange={(e) =>
                                        setFormData((prev) => ({
                                            ...prev,
                                            workspace_slug: e.target.value
                                                .toLowerCase()
                                                .replace(/[^a-z0-9-]/g, ''),
                                        }))
                                    }
                                    placeholder="my-company"
                                    className="flex-1"
                                />
                            </div>
                            <InputError message={errors.workspace_slug} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="business_sector">
                                {t('signup.workspace.business_sector', { default: 'Business Sector' })}
                            </Label>
                            <Select
                                value={formData.business_sector}
                                onValueChange={(value) =>
                                    setFormData((prev) => ({ ...prev, business_sector: value }))
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue
                                        placeholder={t('signup.workspace.business_sector_placeholder', {
                                            default: 'Select your industry',
                                        })}
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    {businessSectors.map((sector) => (
                                        <SelectItem key={sector.value} value={sector.value}>
                                            {sector.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.business_sector} />
                        </div>

                        {/* Plan Selection */}
                        <div className="space-y-4">
                            <div className="flex items-center justify-between">
                                <Label>
                                    {t('signup.workspace.select_plan', { default: 'Select Plan' })}
                                </Label>
                                <PricingToggle
                                    value={billingPeriod}
                                    onChange={onBillingPeriodChange}
                                    savings={t('pricing.toggle.savings', { default: 'Save 20%' })}
                                />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-3">
                                {plans.slice(0, 3).map((plan) => (
                                    <div
                                        key={plan.id}
                                        className={cn(
                                            'cursor-pointer rounded-lg border-2 transition-colors',
                                            selectedPlanSlug === plan.slug
                                                ? 'border-primary'
                                                : 'border-transparent hover:border-muted'
                                        )}
                                        onClick={() => onPlanChange(plan.slug)}
                                    >
                                        <PlanCard
                                            plan={plan}
                                            billingPeriod={billingPeriod}
                                            currentPlanSlug={selectedPlanSlug}
                                            showFeatures={false}
                                            ctaLabel={
                                                selectedPlanSlug === plan.slug
                                                    ? t('signup.workspace.selected', { default: 'Selected' })
                                                    : t('signup.workspace.select', { default: 'Select' })
                                            }
                                        />
                                    </div>
                                ))}
                            </div>
                            <InputError message={errors.plan_id} />
                        </div>

                        {/* Actions */}
                        <div className="flex gap-4">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={onBack}
                                className="flex-1"
                            >
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                {t('common.back', { default: 'Back' })}
                            </Button>
                            <Button type="submit" className="flex-1" disabled={isLoading}>
                                {isLoading && <Spinner className="mr-2" />}
                                {t('signup.workspace.continue', { default: 'Continue to Payment' })}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}
