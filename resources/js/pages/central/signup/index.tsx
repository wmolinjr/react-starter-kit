import { useState, useEffect } from 'react';
import { usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import MarketingLayout from '@/layouts/marketing-layout';
import type { PageProps, PaymentResult } from '@/types';
import { SignupProgress } from '@/components/shared/signup/signup-progress';
import { AccountStep } from '@/components/shared/signup/account-step';
import { WorkspaceStep } from '@/components/shared/signup/workspace-step';
import { PaymentStep } from '@/components/shared/signup/payment-step';
import { ProcessingStep } from '@/components/shared/signup/processing-step';
import { SuccessStep } from '@/components/shared/signup/success-step';
import { AsaasCardStep } from '@/components/shared/signup/asaas-card-step';
import type { PlanResource, PendingSignupResource } from '@/types/resources';
import type { PaymentConfigResource } from '@/types/resources';

export type SignupStepType = 'account' | 'workspace' | 'payment' | 'asaas-card' | 'processing' | 'success';

interface AsaasCardPaymentResult {
    type: 'asaas_card';
    signup_id: string;
    amount: number;
    gateway: string;
    requires_card_data: boolean;
}

interface PixPaymentData {
    qr_code: string;
    qr_code_base64?: string;
    qr_code_text?: string;
    copy_paste?: string;
    expires_at: string;
}

interface BoletoPaymentData {
    barcode: string;
    pdf_url: string;
    due_date: string;
}

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
    const [asaasCardResult, setAsaasCardResult] = useState<AsaasCardPaymentResult | null>(null);
    const [pixData, setPixData] = useState<PixPaymentData | null>(null);
    const [boletoData, setBoletoData] = useState<BoletoPaymentData | null>(null);

    // Get Inertia page props for flash data
    const { props } = usePage<PageProps>();

    // Read flash data when it changes (handles payment redirect)
    // This is critical because:
    // 1. When signup.status === 'processing', we skip PaymentStep and go directly to ProcessingStep
    // 2. With preserveState: true in Inertia, the component doesn't remount
    // So we need to watch for flash data changes, not just mount.
    useEffect(() => {
        const paymentResult = props.flash?.paymentResult as PaymentResult | undefined;
        if (paymentResult) {
            if (paymentResult.type === 'pix' && paymentResult.pix) {
                setPixData(paymentResult.pix as PixPaymentData);
                setCurrentStep('processing');
            } else if (paymentResult.type === 'boleto' && paymentResult.boleto) {
                setBoletoData(paymentResult.boleto as BoletoPaymentData);
                setCurrentStep('processing');
            }
        }
    }, [props.flash?.paymentResult]); // Run when flash data changes

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
    const handlePaymentStarted = (result: PaymentResult) => {
        if (result.type === 'redirect' && result.url) {
            // Card payment - redirect to Stripe
            window.location.href = result.url;
        } else if (result.type === 'asaas_card') {
            // Asaas card payment - show card form
            setAsaasCardResult(result as AsaasCardPaymentResult);
            goToStep('asaas-card');
        } else if (result.type === 'pix' && result.pix) {
            // PIX payment - store data and show processing with QR code
            setPixData(result.pix);
            goToStep('processing');
        } else if (result.type === 'boleto' && result.boleto) {
            // Boleto payment - store data and show processing with barcode
            setBoletoData(result.boleto);
            goToStep('processing');
        }
    };

    // Handle Asaas card payment success
    const handleAsaasCardSuccess = (tenantUrl: string) => {
        window.location.href = tenantUrl;
    };

    // Handle payment completion
    const handlePaymentComplete = (tenantUrl: string) => {
        // Redirect to tenant
        window.location.href = tenantUrl;
    };

    // Get current plan
    const currentPlan = plans.find((p) => p.slug === selectedPlanSlug) || plans[0];

    return (
        <MarketingLayout
            title={t('signup.page.title', { default: 'Sign Up' })}
            showHeaderCta={false}
        >
            <div className="py-8">
                {/* Progress indicator */}
                {currentStep !== 'success' && (
                    <div className="mx-auto max-w-4xl px-4 pb-6">
                        <SignupProgress
                            currentStep={currentStep}
                            skipAccountStep={skipAccountStep}
                        />
                    </div>
                )}

                {/* Step content */}
                <div className="mx-auto max-w-2xl px-4">
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

                    {currentStep === 'asaas-card' && signup && asaasCardResult && currentPlan && (
                        <AsaasCardStep
                            signup={signup}
                            plan={currentPlan}
                            amount={asaasCardResult.amount}
                            onSuccess={handleAsaasCardSuccess}
                            onBack={() => goToStep('payment')}
                        />
                    )}

                    {currentStep === 'processing' && signup && (
                        <ProcessingStep
                            signup={signup}
                            pixData={pixData}
                            boletoData={boletoData}
                            onComplete={handlePaymentComplete}
                        />
                    )}

                    {currentStep === 'success' && signup && (
                        <SuccessStep
                            signup={signup}
                            tenantUrl={signup.tenant_url || ''}
                        />
                    )}
                </div>
            </div>
        </MarketingLayout>
    );
}
