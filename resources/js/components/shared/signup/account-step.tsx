import { useState } from 'react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { toast } from 'sonner';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import InputError from '@/components/shared/feedback/input-error';
import { store as storeAccount } from '@/routes/central/signup/account';
import { email as validateEmailRoute } from '@/routes/central/signup/validate';
import type { PendingSignupResource } from '@/types/resources';

interface AccountStepProps {
    onSuccess: (signup: PendingSignupResource) => void;
}

export function AccountStep({ onSuccess }: AccountStepProps) {
    const { t } = useLaravelReactI18n();
    const [isLoading, setIsLoading] = useState(false);
    const [isValidatingEmail, setIsValidatingEmail] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [formData, setFormData] = useState({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const validateEmailAsync = async (email: string) => {
        if (!email || !email.includes('@')) return;

        setIsValidatingEmail(true);
        try {
            const response = await fetch(validateEmailRoute.url(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ email }),
            });

            const data = await response.json();
            if (!data.available) {
                setErrors((prev) => ({
                    ...prev,
                    email: data.message || t('signup.errors.email_already_registered'),
                }));
            } else {
                setErrors((prev) => {
                    const { email, ...rest } = prev;
                    return rest;
                });
            }
        } catch (error) {
            // Ignore validation errors
        } finally {
            setIsValidatingEmail(false);
        }
    };

    const handleEmailBlur = () => {
        validateEmailAsync(formData.email);
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsLoading(true);
        setErrors({});

        try {
            const response = await fetch(storeAccount.url(), {
                method: 'POST',
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
                <form onSubmit={handleSubmit} className="space-y-4">
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
                            required
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
                            onBlur={handleEmailBlur}
                            placeholder={t('signup.account.email_placeholder')}
                            required
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
                            required
                            minLength={8}
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
                            required
                        />
                        <InputError message={errors.password_confirmation} />
                    </div>

                    <Button
                        type="submit"
                        className="w-full"
                        disabled={isLoading || isValidatingEmail || !!errors.email}
                    >
                        {isLoading && <Spinner className="mr-2" />}
                        {t('signup.account.continue', { default: 'Continue' })}
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}
