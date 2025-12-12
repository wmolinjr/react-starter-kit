import { useState, useEffect } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import AppLogoIcon from '@/components/shared/branding/app-logo-icon';
import { SignupProgress } from '@/components/shared/signup/signup-progress';
import { AccountStep } from '@/components/shared/signup/account-step';
import { WorkspaceStep } from '@/components/shared/signup/workspace-step';
import { PaymentStep } from '@/components/shared/signup/payment-step';
import { ProcessingStep } from '@/components/shared/signup/processing-step';
import { SuccessStep } from '@/components/shared/signup/success-step';
import type { PlanResource, PendingSignupResource } from '@/types/resources';
import type { PaymentConfigResource } from '@/types/resources';

export type SignupStepType = 'account' | 'workspace' | 'payment' | 'processing' | 'success';

interface BusinessSectorOption {
    value: string;
    label: string;
}

/**
 * Customer summary data for logged-in customers.
 * Used in customer-first signup flow.
 */
interface CustomerSummary {
    id: string;
    name: string;
    email: string;
    locale: string;
    email_verified: boolean;
}

interface SignupPageProps {
    plans: PlanResource[];
    selectedPlan?: string;
    signup?: PendingSignupResource | null;
    businessSectors: BusinessSectorOption[];
    paymentConfig: PaymentConfigResource;
    /** Logged-in customer data (customer-first flow) */
    customer?: CustomerSummary | null;
    /** Skip account step when customer is already logged in */
    skipAccountStep?: boolean;
}

export default function SignupPage({
    plans,
    selectedPlan,
    signup: initialSignup,
    businessSectors,
    paymentConfig,
    customer,
    skipAccountStep = false,
}: SignupPageProps) {
    const { t } = useLaravelReactI18n();
    const { name: appName } = usePage<{ name: string }>().props;

    /**
     * Determine initial step based on signup state.
     *
     * Customer-First Flow:
     * - If customer is logged in (skipAccountStep), start at workspace step
     * - If signup has customer_id, the customer already created account, skip to workspace
     */
    const getInitialStep = (): SignupStepType => {
        // No signup yet
        if (!initialSignup) {
            return skipAccountStep ? 'workspace' : 'account';
        }

        // Signup exists - check its state
        if (initialSignup.is_completed) return 'success';
        if (initialSignup.status === 'processing') return 'processing';
        if (initialSignup.has_workspace_data) return 'payment';

        // Signup has customer_id means account step is done (customer-first)
        return initialSignup.customer_id ? 'workspace' : 'account';
    };

    const [currentStep, setCurrentStep] = useState<SignupStepType>(getInitialStep());
    const [signup, setSignup] = useState<PendingSignupResource | null>(initialSignup ?? null);
    const [billingPeriod, setBillingPeriod] = useState<'monthly' | 'yearly'>('monthly');
    const [selectedPlanSlug, setSelectedPlanSlug] = useState<string>(
        selectedPlan || plans[0]?.slug || ''
    );

    // Handle step navigation
    const goToStep = (step: SignupStepType, signupData?: PendingSignupResource | null) => {
        setCurrentStep(step);
        // Update URL without full navigation
        // Format: /signup/{plan}/{signup_id}
        const signupId = signupData?.id ?? signup?.id;
        const planSlug = selectedPlanSlug || selectedPlan || 'starter';
        const newPath = signupId
            ? `/signup/${planSlug}/${signupId}`
            : `/signup/${planSlug}`;
        window.history.replaceState({}, '', newPath);
    };

    // Handle account creation success
    const handleAccountCreated = (newSignup: PendingSignupResource) => {
        setSignup(newSignup);
        goToStep('workspace', newSignup);
    };

    // Handle workspace setup success
    const handleWorkspaceUpdated = (updatedSignup: PendingSignupResource) => {
        setSignup(updatedSignup);
        goToStep('payment', updatedSignup);
    };

    // Handle payment initiation
    const handlePaymentStarted = (result: {
        type: string;
        url?: string;
        signup_id?: string;
        pix?: object;
        boleto?: object;
    }) => {
        if (result.type === 'redirect' && result.url) {
            // Card payment - redirect to Stripe
            window.location.href = result.url;
        } else if (result.type === 'pix' || result.type === 'boleto') {
            // Async payment - show processing
            goToStep('processing');
        }
    };

    // Handle payment completion
    const handlePaymentComplete = (tenantUrl: string) => {
        // Redirect to tenant
        window.location.href = tenantUrl;
    };

    // Get current plan
    const currentPlan = plans.find((p) => p.slug === selectedPlanSlug) || plans[0];

    return (
        <>
            <Head title={t('signup.page.title', { default: 'Sign Up' })} />

            <div className="bg-background min-h-svh">
                {/* Header */}
                <header className="border-b">
                    <div className="mx-auto flex h-16 max-w-4xl items-center justify-center px-4">
                        <div className="flex items-center gap-2">
                            <AppLogoIcon className="text-foreground size-8" />
                            <span className="text-lg font-semibold">
                                {appName}
                            </span>
                        </div>
                    </div>
                </header>

                {/* Progress indicator */}
                {currentStep !== 'success' && (
                    <div className="mx-auto max-w-4xl px-4 py-6">
                        <SignupProgress
                            currentStep={currentStep}
                            skipAccountStep={skipAccountStep}
                        />
                    </div>
                )}

                {/* Step content */}
                <main className="mx-auto max-w-2xl px-4 py-8">
                    {currentStep === 'account' && (
                        <AccountStep
                            existingSignup={signup}
                            onSuccess={handleAccountCreated}
                        />
                    )}

                    {currentStep === 'workspace' && signup && (
                        <WorkspaceStep
                            signup={signup}
                            plans={plans}
                            businessSectors={businessSectors}
                            selectedPlanSlug={selectedPlanSlug}
                            billingPeriod={billingPeriod}
                            onPlanChange={setSelectedPlanSlug}
                            onBillingPeriodChange={setBillingPeriod}
                            onSuccess={handleWorkspaceUpdated}
                            onBack={skipAccountStep ? undefined : () => goToStep('account')}
                        />
                    )}

                    {currentStep === 'payment' && signup && currentPlan && (
                        <PaymentStep
                            signup={signup}
                            plan={currentPlan}
                            billingPeriod={billingPeriod}
                            paymentConfig={paymentConfig}
                            onSuccess={handlePaymentStarted}
                            onBack={() => goToStep('workspace')}
                        />
                    )}

                    {currentStep === 'processing' && signup && (
                        <ProcessingStep
                            signup={signup}
                            onComplete={handlePaymentComplete}
                        />
                    )}

                    {currentStep === 'success' && signup && (
                        <SuccessStep
                            signup={signup}
                            tenantUrl={signup.tenant_url || ''}
                        />
                    )}
                </main>

                {/* Footer */}
                <footer className="border-t py-4">
                    <div className="mx-auto max-w-4xl px-4 text-center">
                        <p className="text-muted-foreground text-sm">
                            {t('signup.footer.secure', { default: 'Secure checkout powered by Stripe' })}
                        </p>
                    </div>
                </footer>
            </div>
        </>
    );
}
