import { useLaravelReactI18n } from 'laravel-react-i18n';
import { CheckCircle, ArrowRight, Rocket } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import type { PendingSignupResource } from '@/types/resources';

interface SuccessStepProps {
    signup: PendingSignupResource;
    tenantUrl: string;
}

export function SuccessStep({ signup, tenantUrl }: SuccessStepProps) {
    const { t } = useLaravelReactI18n();

    return (
        <Card className="overflow-hidden">
            {/* Success Header */}
            <div className="bg-gradient-to-r from-green-500 to-emerald-600 p-8 text-center text-white">
                <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-white/20">
                    <CheckCircle className="h-10 w-10" />
                </div>
                <h1 className="text-2xl font-bold">
                    {t('signup.success.title', { default: 'Welcome to your new workspace!' })}
                </h1>
                <p className="mt-2 text-green-100">
                    {t('signup.success.subtitle', {
                        default: 'Your account has been created successfully.',
                    })}
                </p>
            </div>

            <CardContent className="p-8">
                {/* Workspace Info */}
                <div className="mb-8 rounded-lg border bg-muted/30 p-6 text-center">
                    <p className="text-muted-foreground text-sm">
                        {t('signup.success.workspace_name', { default: 'Your workspace' })}
                    </p>
                    <p className="text-xl font-semibold">{signup.workspace_name}</p>
                    <p className="text-muted-foreground mt-1 text-sm">{tenantUrl}</p>
                </div>

                {/* Next Steps */}
                <div className="mb-8 space-y-4">
                    <h3 className="text-center font-medium">
                        {t('signup.success.next_steps', { default: 'Next steps' })}
                    </h3>
                    <ul className="space-y-3">
                        <li className="flex items-start gap-3">
                            <div className="bg-primary/10 text-primary flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-sm font-medium">
                                1
                            </div>
                            <span className="text-muted-foreground text-sm">
                                {t('signup.success.step_1', {
                                    default: 'Access your workspace and complete your profile',
                                })}
                            </span>
                        </li>
                        <li className="flex items-start gap-3">
                            <div className="bg-primary/10 text-primary flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-sm font-medium">
                                2
                            </div>
                            <span className="text-muted-foreground text-sm">
                                {t('signup.success.step_2', {
                                    default: 'Invite your team members to collaborate',
                                })}
                            </span>
                        </li>
                        <li className="flex items-start gap-3">
                            <div className="bg-primary/10 text-primary flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-sm font-medium">
                                3
                            </div>
                            <span className="text-muted-foreground text-sm">
                                {t('signup.success.step_3', {
                                    default: 'Start building and growing your business',
                                })}
                            </span>
                        </li>
                    </ul>
                </div>

                {/* CTA */}
                <Button
                    className="w-full"
                    size="lg"
                    onClick={() => (window.location.href = tenantUrl)}
                >
                    <Rocket className="mr-2 h-5 w-5" />
                    {t('signup.success.go_to_workspace', { default: 'Go to Workspace' })}
                    <ArrowRight className="ml-2 h-5 w-5" />
                </Button>

                {/* Email Notice */}
                <p className="text-muted-foreground mt-4 text-center text-sm">
                    {t('signup.success.email_sent', {
                        default: 'We sent a confirmation email to',
                    })}{' '}
                    <strong>{signup.email}</strong>
                </p>
            </CardContent>
        </Card>
    );
}
