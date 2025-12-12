import { useState, useEffect } from 'react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { toast } from 'sonner';
import { ArrowLeft, Check, X, Loader2 } from 'lucide-react';
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
    const [isLoading, setIsLoading] = useState(false);
    const [isValidatingSlug, setIsValidatingSlug] = useState(false);
    const [slugAvailable, setSlugAvailable] = useState<boolean | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [formData, setFormData] = useState({
        workspace_name: signup.workspace_name || '',
        workspace_slug: signup.workspace_slug || '',
        business_sector: signup.business_sector || '',
        plan_id: plans.find((p) => p.slug === selectedPlanSlug)?.id || plans[0]?.id || '',
        billing_period: billingPeriod,
    });

    // Auto-generate slug from workspace name
    useEffect(() => {
        if (formData.workspace_name && !signup.workspace_slug) {
            const newSlug = generateSlug(formData.workspace_name);
            setFormData((prev) => ({ ...prev, workspace_slug: newSlug }));
            setSlugAvailable(null);
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

    const validateSlugAsync = async (slug: string) => {
        if (!slug || slug.length < 3) {
            setSlugAvailable(null);
            return;
        }

        setIsValidatingSlug(true);
        try {
            const response = await fetch('/signup/validate/slug', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ slug, signup_id: signup.id }),
            });

            const data = await response.json();
            setSlugAvailable(data.available);
            if (!data.available) {
                setErrors((prev) => ({
                    ...prev,
                    workspace_slug: data.message || t('signup.errors.slug_already_taken'),
                }));
            } else {
                setErrors((prev) => {
                    const { workspace_slug, ...rest } = prev;
                    return rest;
                });
            }
        } catch (error) {
            // Ignore validation errors
        } finally {
            setIsValidatingSlug(false);
        }
    };

    const handleSlugBlur = () => {
        validateSlugAsync(formData.workspace_slug);
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsLoading(true);
        setErrors({});

        try {
            const response = await fetch(updateWorkspace.url({ signup: signup.id }), {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify(formData),
            });

            const data = await response.json();

            if (!response.ok) {
                if (data.errors) {
                    setErrors(data.errors);
                } else {
                    toast.error(data.message || t('signup.errors.generic'));
                }
                return;
            }

            onSuccess(data.signup);
        } catch (error) {
            toast.error(t('signup.errors.network'));
        } finally {
            setIsLoading(false);
        }
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
                    <form onSubmit={handleSubmit} className="space-y-6">
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
                                required
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
                                <div className="relative flex-1">
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
                                        onBlur={handleSlugBlur}
                                        placeholder="my-company"
                                        required
                                        minLength={3}
                                        maxLength={50}
                                        className="pr-10"
                                    />
                                    <div className="absolute inset-y-0 right-0 flex items-center pr-3">
                                        {isValidatingSlug && (
                                            <Loader2 className="text-muted-foreground h-4 w-4 animate-spin" />
                                        )}
                                        {!isValidatingSlug && slugAvailable === true && (
                                            <Check className="h-4 w-4 text-green-500" />
                                        )}
                                        {!isValidatingSlug && slugAvailable === false && (
                                            <X className="h-4 w-4 text-red-500" />
                                        )}
                                    </div>
                                </div>
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
                            <Button
                                type="submit"
                                className="flex-1"
                                disabled={isLoading || isValidatingSlug || slugAvailable === false}
                            >
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
