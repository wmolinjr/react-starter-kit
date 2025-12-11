import { useState, useCallback } from 'react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { CreditCard, User, MapPin, Loader2, AlertCircle, CheckCircle2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';

interface CardData {
    holder_name: string;
    number: string;
    exp_month: string;
    exp_year: string;
    cvv: string;
}

interface HolderData {
    name: string;
    email: string;
    cpf_cnpj: string;
    postal_code: string;
    address_number: string;
    address_complement?: string;
    phone?: string;
}

export interface AsaasCardFormProps {
    /** Purchase ID from the initial cart checkout */
    purchaseId: string;
    /** Amount to charge (in cents) */
    amount: number;
    /** Formatted amount string */
    formattedAmount: string;
    /** Endpoint to submit card payment */
    submitEndpoint: string;
    /** Callback on successful payment */
    onSuccess?: (result: { purchase_id: string; card?: { last_four?: string; brand?: string } }) => void;
    /** Callback on payment error */
    onError?: (error: string) => void;
    /** Pre-filled holder data (optional) */
    defaultHolder?: Partial<HolderData>;
    /** Additional class names */
    className?: string;
}

/**
 * AsaasCardForm - Credit card form for Asaas gateway payments
 *
 * Collects card data and holder information required by Asaas API.
 * Note: This handles sensitive card data - ensure PCI compliance.
 */
export function AsaasCardForm({
    purchaseId,
    amount,
    formattedAmount,
    submitEndpoint,
    onSuccess,
    onError,
    defaultHolder,
    className,
}: AsaasCardFormProps) {
    const { t } = useLaravelReactI18n();
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [success, setSuccess] = useState(false);

    // Card data state
    const [cardData, setCardData] = useState<CardData>({
        holder_name: '',
        number: '',
        exp_month: '',
        exp_year: '',
        cvv: '',
    });

    // Holder data state
    const [holderData, setHolderData] = useState<HolderData>({
        name: defaultHolder?.name || '',
        email: defaultHolder?.email || '',
        cpf_cnpj: defaultHolder?.cpf_cnpj || '',
        postal_code: defaultHolder?.postal_code || '',
        address_number: defaultHolder?.address_number || '',
        address_complement: defaultHolder?.address_complement || '',
        phone: defaultHolder?.phone || '',
    });

    // Format card number with spaces
    const formatCardNumber = (value: string) => {
        const digits = value.replace(/\D/g, '').slice(0, 19);
        return digits.replace(/(\d{4})(?=\d)/g, '$1 ');
    };

    // Format CPF/CNPJ
    const formatCpfCnpj = (value: string) => {
        const digits = value.replace(/\D/g, '');
        if (digits.length <= 11) {
            // CPF: 000.000.000-00
            return digits
                .replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        }
        // CNPJ: 00.000.000/0000-00
        return digits
            .replace(/^(\d{2})(\d)/, '$1.$2')
            .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
            .replace(/\.(\d{3})(\d)/, '.$1/$2')
            .replace(/(\d{4})(\d)/, '$1-$2')
            .slice(0, 18);
    };

    // Format CEP
    const formatPostalCode = (value: string) => {
        const digits = value.replace(/\D/g, '').slice(0, 8);
        return digits.replace(/(\d{5})(\d)/, '$1-$2');
    };

    // Handle card data change
    const handleCardChange = (field: keyof CardData, value: string) => {
        let formattedValue = value;

        if (field === 'number') {
            formattedValue = formatCardNumber(value);
        } else if (field === 'exp_month') {
            formattedValue = value.replace(/\D/g, '').slice(0, 2);
        } else if (field === 'exp_year') {
            formattedValue = value.replace(/\D/g, '').slice(0, 4);
        } else if (field === 'cvv') {
            formattedValue = value.replace(/\D/g, '').slice(0, 4);
        }

        setCardData(prev => ({ ...prev, [field]: formattedValue }));
    };

    // Handle holder data change
    const handleHolderChange = (field: keyof HolderData, value: string) => {
        let formattedValue = value;

        if (field === 'cpf_cnpj') {
            formattedValue = formatCpfCnpj(value);
        } else if (field === 'postal_code') {
            formattedValue = formatPostalCode(value);
        }

        setHolderData(prev => ({ ...prev, [field]: formattedValue }));
    };

    // Validate form
    const validateForm = useCallback((): string | null => {
        // Card validation
        const cardNumber = cardData.number.replace(/\s/g, '');
        if (!cardNumber || cardNumber.length < 13) {
            return t('billing.form.invalid_card_number', { default: 'Invalid card number' });
        }
        if (!cardData.holder_name.trim()) {
            return t('billing.form.holder_name_required', { default: 'Cardholder name is required' });
        }
        if (!cardData.exp_month || parseInt(cardData.exp_month) < 1 || parseInt(cardData.exp_month) > 12) {
            return t('billing.form.invalid_exp_month', { default: 'Invalid expiration month' });
        }
        if (!cardData.exp_year || cardData.exp_year.length !== 4) {
            return t('billing.form.invalid_exp_year', { default: 'Invalid expiration year' });
        }
        if (!cardData.cvv || cardData.cvv.length < 3) {
            return t('billing.form.invalid_cvv', { default: 'Invalid CVV' });
        }

        // Holder validation
        if (!holderData.name.trim()) {
            return t('billing.form.name_required', { default: 'Name is required' });
        }
        if (!holderData.email.trim() || !holderData.email.includes('@')) {
            return t('billing.form.invalid_email', { default: 'Invalid email' });
        }
        const cpfCnpj = holderData.cpf_cnpj.replace(/\D/g, '');
        if (!cpfCnpj || (cpfCnpj.length !== 11 && cpfCnpj.length !== 14)) {
            return t('billing.form.invalid_cpf_cnpj', { default: 'Invalid CPF/CNPJ' });
        }
        const postalCode = holderData.postal_code.replace(/\D/g, '');
        if (!postalCode || postalCode.length !== 8) {
            return t('billing.form.invalid_postal_code', { default: 'Invalid postal code' });
        }
        if (!holderData.address_number.trim()) {
            return t('billing.form.address_number_required', { default: 'Address number is required' });
        }

        return null;
    }, [cardData, holderData, t]);

    // Handle form submission
    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setError(null);

        const validationError = validateForm();
        if (validationError) {
            setError(validationError);
            return;
        }

        setIsSubmitting(true);

        try {
            const response = await fetch(submitEndpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': document.cookie
                        .split('; ')
                        .find(row => row.startsWith('XSRF-TOKEN='))
                        ?.split('=')[1]
                        ?.replace(/%3D/g, '=') || '',
                },
                body: JSON.stringify({
                    purchase_id: purchaseId,
                    card: {
                        holder_name: cardData.holder_name,
                        number: cardData.number.replace(/\s/g, ''),
                        exp_month: cardData.exp_month.padStart(2, '0'),
                        exp_year: cardData.exp_year,
                        cvv: cardData.cvv,
                    },
                    holder: {
                        name: holderData.name,
                        email: holderData.email,
                        cpf_cnpj: holderData.cpf_cnpj.replace(/\D/g, ''),
                        postal_code: holderData.postal_code.replace(/\D/g, ''),
                        address_number: holderData.address_number,
                        address_complement: holderData.address_complement || null,
                        phone: holderData.phone?.replace(/\D/g, '') || null,
                    },
                }),
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || t('billing.errors.card_charge_failed', { default: 'Card payment failed' }));
            }

            setSuccess(true);
            onSuccess?.(result);
        } catch (err) {
            const errorMessage = err instanceof Error
                ? err.message
                : t('billing.errors.card_charge_failed', { default: 'Card payment failed' });
            setError(errorMessage);
            onError?.(errorMessage);
        } finally {
            setIsSubmitting(false);
        }
    };

    // Success state
    if (success) {
        return (
            <div className="flex flex-col items-center justify-center gap-4 py-8 text-center">
                <div className="flex h-16 w-16 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                    <CheckCircle2 className="h-8 w-8 text-green-600 dark:text-green-400" />
                </div>
                <div>
                    <h3 className="text-lg font-semibold">
                        {t('billing.payment_successful', { default: 'Payment Successful!' })}
                    </h3>
                    <p className="text-sm text-muted-foreground">
                        {t('billing.form.payment_processed', { default: 'Your card payment has been processed.' })}
                    </p>
                </div>
            </div>
        );
    }

    return (
        <form onSubmit={handleSubmit} className={cn('space-y-6', className)}>
            {/* Error message */}
            {error && (
                <div className="flex items-center gap-2 rounded-md bg-destructive/10 p-3 text-sm text-destructive">
                    <AlertCircle className="h-4 w-4 shrink-0" />
                    {error}
                </div>
            )}

            {/* Amount display */}
            <div className="rounded-lg bg-muted/50 p-4 text-center">
                <p className="text-sm text-muted-foreground">
                    {t('billing.price.amount_to_pay', { default: 'Amount to pay' })}
                </p>
                <p className="text-2xl font-bold">{formattedAmount}</p>
            </div>

            {/* Card Information Section */}
            <div className="space-y-4">
                <div className="flex items-center gap-2">
                    <CreditCard className="h-4 w-4 text-muted-foreground" />
                    <h4 className="font-medium">
                        {t('billing.form.card_info', { default: 'Card Information' })}
                    </h4>
                </div>

                <div className="space-y-3">
                    <div>
                        <Label htmlFor="card-number">
                            {t('billing.form.card_number', { default: 'Card Number' })}
                        </Label>
                        <Input
                            id="card-number"
                            type="text"
                            inputMode="numeric"
                            placeholder="0000 0000 0000 0000"
                            value={cardData.number}
                            onChange={(e) => handleCardChange('number', e.target.value)}
                            disabled={isSubmitting}
                            className="font-mono"
                        />
                    </div>

                    <div>
                        <Label htmlFor="holder-name">
                            {t('billing.form.cardholder_name', { default: 'Cardholder Name' })}
                        </Label>
                        <Input
                            id="holder-name"
                            type="text"
                            placeholder="John Doe"
                            value={cardData.holder_name}
                            onChange={(e) => handleCardChange('holder_name', e.target.value.toUpperCase())}
                            disabled={isSubmitting}
                            className="uppercase"
                        />
                    </div>

                    <div className="grid grid-cols-3 gap-3">
                        <div>
                            <Label htmlFor="exp-month">
                                {t('billing.form.exp_month', { default: 'Month' })}
                            </Label>
                            <Input
                                id="exp-month"
                                type="text"
                                inputMode="numeric"
                                placeholder="MM"
                                value={cardData.exp_month}
                                onChange={(e) => handleCardChange('exp_month', e.target.value)}
                                disabled={isSubmitting}
                                className="font-mono text-center"
                            />
                        </div>
                        <div>
                            <Label htmlFor="exp-year">
                                {t('billing.form.exp_year', { default: 'Year' })}
                            </Label>
                            <Input
                                id="exp-year"
                                type="text"
                                inputMode="numeric"
                                placeholder="YYYY"
                                value={cardData.exp_year}
                                onChange={(e) => handleCardChange('exp_year', e.target.value)}
                                disabled={isSubmitting}
                                className="font-mono text-center"
                            />
                        </div>
                        <div>
                            <Label htmlFor="cvv">
                                {t('billing.form.cvv', { default: 'CVV' })}
                            </Label>
                            <Input
                                id="cvv"
                                type="text"
                                inputMode="numeric"
                                placeholder="123"
                                value={cardData.cvv}
                                onChange={(e) => handleCardChange('cvv', e.target.value)}
                                disabled={isSubmitting}
                                className="font-mono text-center"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <Separator />

            {/* Holder Information Section */}
            <div className="space-y-4">
                <div className="flex items-center gap-2">
                    <User className="h-4 w-4 text-muted-foreground" />
                    <h4 className="font-medium">
                        {t('billing.form.holder_info', { default: 'Billing Information' })}
                    </h4>
                </div>

                <div className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <Label htmlFor="holder-full-name">
                                {t('billing.form.full_name', { default: 'Full Name' })}
                            </Label>
                            <Input
                                id="holder-full-name"
                                type="text"
                                value={holderData.name}
                                onChange={(e) => handleHolderChange('name', e.target.value)}
                                disabled={isSubmitting}
                            />
                        </div>
                        <div>
                            <Label htmlFor="holder-email">
                                {t('billing.form.email', { default: 'Email' })}
                            </Label>
                            <Input
                                id="holder-email"
                                type="email"
                                value={holderData.email}
                                onChange={(e) => handleHolderChange('email', e.target.value)}
                                disabled={isSubmitting}
                            />
                        </div>
                    </div>

                    <div>
                        <Label htmlFor="cpf-cnpj">
                            {t('billing.form.cpf_cnpj', { default: 'CPF/CNPJ' })}
                        </Label>
                        <Input
                            id="cpf-cnpj"
                            type="text"
                            inputMode="numeric"
                            placeholder="000.000.000-00"
                            value={holderData.cpf_cnpj}
                            onChange={(e) => handleHolderChange('cpf_cnpj', e.target.value)}
                            disabled={isSubmitting}
                            className="font-mono"
                        />
                    </div>
                </div>
            </div>

            <Separator />

            {/* Address Section */}
            <div className="space-y-4">
                <div className="flex items-center gap-2">
                    <MapPin className="h-4 w-4 text-muted-foreground" />
                    <h4 className="font-medium">
                        {t('billing.form.address', { default: 'Address' })}
                    </h4>
                </div>

                <div className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <Label htmlFor="postal-code">
                                {t('billing.form.postal_code', { default: 'Postal Code' })}
                            </Label>
                            <Input
                                id="postal-code"
                                type="text"
                                inputMode="numeric"
                                placeholder="00000-000"
                                value={holderData.postal_code}
                                onChange={(e) => handleHolderChange('postal_code', e.target.value)}
                                disabled={isSubmitting}
                                className="font-mono"
                            />
                        </div>
                        <div>
                            <Label htmlFor="address-number">
                                {t('billing.form.number', { default: 'Number' })}
                            </Label>
                            <Input
                                id="address-number"
                                type="text"
                                value={holderData.address_number}
                                onChange={(e) => handleHolderChange('address_number', e.target.value)}
                                disabled={isSubmitting}
                            />
                        </div>
                    </div>

                    <div>
                        <Label htmlFor="address-complement">
                            {t('billing.form.complement', { default: 'Complement (optional)' })}
                        </Label>
                        <Input
                            id="address-complement"
                            type="text"
                            placeholder={t('billing.form.complement_placeholder', { default: 'Apt, Suite, etc.' })}
                            value={holderData.address_complement}
                            onChange={(e) => handleHolderChange('address_complement', e.target.value)}
                            disabled={isSubmitting}
                        />
                    </div>

                    <div>
                        <Label htmlFor="phone">
                            {t('billing.form.phone', { default: 'Phone (optional)' })}
                        </Label>
                        <Input
                            id="phone"
                            type="tel"
                            placeholder="(00) 00000-0000"
                            value={holderData.phone}
                            onChange={(e) => handleHolderChange('phone', e.target.value)}
                            disabled={isSubmitting}
                        />
                    </div>
                </div>
            </div>

            {/* Submit button */}
            <Button type="submit" size="lg" className="w-full" disabled={isSubmitting}>
                {isSubmitting ? (
                    <>
                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                        {t('billing.payment.processing', { default: 'Processing...' })}
                    </>
                ) : (
                    t('billing.payment.pay_now', { default: 'Pay Now' })
                )}
            </Button>

            {/* Security note */}
            <p className="text-xs text-center text-muted-foreground">
                {t('billing.form.secure_payment', {
                    default: 'Your payment information is securely processed.',
                })}
            </p>
        </form>
    );
}
