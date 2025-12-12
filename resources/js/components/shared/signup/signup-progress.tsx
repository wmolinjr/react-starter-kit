import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Check, User, Building2, CreditCard } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { SignupStepType } from '@/pages/central/signup/index';

interface SignupProgressProps {
    currentStep: SignupStepType;
}

const steps = [
    { key: 'account' as const, icon: User },
    { key: 'workspace' as const, icon: Building2 },
    { key: 'payment' as const, icon: CreditCard },
];

export function SignupProgress({ currentStep }: SignupProgressProps) {
    const { t } = useLaravelReactI18n();

    const stepLabels: Record<string, string> = {
        account: t('signup.steps.account', { default: 'Account' }),
        workspace: t('signup.steps.workspace', { default: 'Workspace' }),
        payment: t('signup.steps.payment', { default: 'Payment' }),
    };

    const getStepStatus = (stepKey: string): 'completed' | 'current' | 'upcoming' => {
        const stepOrder = ['account', 'workspace', 'payment'];
        const currentIndex = stepOrder.indexOf(currentStep);
        const stepIndex = stepOrder.indexOf(stepKey);

        if (currentStep === 'processing' || currentStep === 'success') {
            return 'completed';
        }

        if (stepIndex < currentIndex) return 'completed';
        if (stepIndex === currentIndex) return 'current';
        return 'upcoming';
    };

    return (
        <nav aria-label="Progress">
            <ol className="flex items-center justify-center space-x-4 sm:space-x-8">
                {steps.map((step, index) => {
                    const status = getStepStatus(step.key);
                    const Icon = step.icon;

                    return (
                        <li key={step.key} className="flex items-center">
                            {index > 0 && (
                                <div
                                    className={cn(
                                        '-ml-4 mr-4 hidden h-0.5 w-8 sm:block sm:w-16',
                                        status === 'upcoming'
                                            ? 'bg-muted'
                                            : 'bg-primary'
                                    )}
                                />
                            )}

                            <div className="flex items-center gap-2">
                                <div
                                    className={cn(
                                        'flex h-10 w-10 items-center justify-center rounded-full border-2 transition-colors',
                                        status === 'completed' &&
                                            'border-primary bg-primary text-primary-foreground',
                                        status === 'current' &&
                                            'border-primary bg-background text-primary',
                                        status === 'upcoming' &&
                                            'border-muted bg-muted text-muted-foreground'
                                    )}
                                >
                                    {status === 'completed' ? (
                                        <Check className="h-5 w-5" />
                                    ) : (
                                        <Icon className="h-5 w-5" />
                                    )}
                                </div>

                                <span
                                    className={cn(
                                        'hidden text-sm font-medium sm:block',
                                        status === 'completed' && 'text-primary',
                                        status === 'current' && 'text-foreground',
                                        status === 'upcoming' && 'text-muted-foreground'
                                    )}
                                >
                                    {stepLabels[step.key]}
                                </span>
                            </div>
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
