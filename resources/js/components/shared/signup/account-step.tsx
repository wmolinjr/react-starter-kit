import { useState, useEffect, useCallback } from 'react';
import { router, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import InputError from '@/components/shared/feedback/input-error';
import { store as storeAccount } from '@/routes/central/signup/account';
import type { PendingSignupResource } from '@/types/resources';
import type { PageProps } from '@/types';

interface AccountStepProps {
    onSuccess: (signup: PendingSignupResource) => void;
}

export function AccountStep({ onSuccess }: AccountStepProps) {
    const { t } = useLaravelReactI18n();
    const { props } = usePage<PageProps>();
    const { errors = {} } = props;
    const [isLoading, setIsLoading] = useState(false);
    const [formData, setFormData] = useState({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    // Watch for flash data changes (pendingSignup from server)
    const handleFlashSuccess = useCallback(() => {
        const pendingSignup = props.flash?.pendingSignup as PendingSignupResource | undefined;
        if (pendingSignup && pendingSignup.id) {
            onSuccess(pendingSignup);
        }
    }, [props.flash?.pendingSignup, onSuccess]);

    useEffect(() => {
        handleFlashSuccess();
    }, [handleFlashSuccess]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setIsLoading(true);

        router.post(storeAccount.url(), formData, {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                setIsLoading(false);
            },
        });
    };

    return (
        <Card>
            <CardHeader className="text-center">
                <CardTitle className="text-2xl">
                    {t('signup.account.title', { default: 'Create your account' })}
                </CardTitle>
                <CardDescription>
                    {t('signup.account.description', {
                        default: 'Enter your information to get started',
                    })}
                </CardDescription>
            </CardHeader>

            <CardContent>
                <form onSubmit={handleSubmit} className="space-y-4" noValidate>
                    <div className="space-y-2">
                        <Label htmlFor="name">{t('signup.account.name')}</Label>
                        <Input
                            id="name"
                            type="text"
                            value={formData.name}
                            onChange={(e) =>
                                setFormData((prev) => ({ ...prev, name: e.target.value }))
                            }
                            placeholder={t('signup.account.name_placeholder')}
                            autoFocus
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="email">{t('signup.account.email')}</Label>
                        <Input
                            id="email"
                            type="email"
                            value={formData.email}
                            onChange={(e) =>
                                setFormData((prev) => ({ ...prev, email: e.target.value }))
                            }
                            placeholder={t('signup.account.email_placeholder')}
                        />
                        <InputError message={errors.email} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="password">{t('signup.account.password')}</Label>
                        <Input
                            id="password"
                            type="password"
                            value={formData.password}
                            onChange={(e) =>
                                setFormData((prev) => ({ ...prev, password: e.target.value }))
                            }
                            placeholder={t('signup.account.password_placeholder')}
                        />
                        <InputError message={errors.password} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="password_confirmation">
                            {t('signup.account.password_confirmation')}
                        </Label>
                        <Input
                            id="password_confirmation"
                            type="password"
                            value={formData.password_confirmation}
                            onChange={(e) =>
                                setFormData((prev) => ({
                                    ...prev,
                                    password_confirmation: e.target.value,
                                }))
                            }
                            placeholder={t('signup.account.password_confirmation_placeholder')}
                        />
                        <InputError message={errors.password_confirmation} />
                    </div>

                    <Button type="submit" className="w-full" disabled={isLoading}>
                        {isLoading && <Spinner className="mr-2" />}
                        {t('signup.account.continue', { default: 'Continue' })}
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}
