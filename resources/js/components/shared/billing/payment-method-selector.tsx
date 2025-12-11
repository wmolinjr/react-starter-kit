import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Card, CardContent } from '@/components/ui/card';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Label } from '@/components/ui/label';
import { CreditCard, QrCode, FileText } from 'lucide-react';
import { cn } from '@/lib/utils';

export type PaymentMethod = 'card' | 'pix' | 'boleto';

export interface PaymentMethodSelectorProps {
    /** Currently selected payment method */
    value: PaymentMethod;
    /** Callback when selection changes */
    onChange: (method: PaymentMethod) => void;
    /** Available payment methods (default: all) */
    availableMethods?: PaymentMethod[];
    /** Disable selection */
    disabled?: boolean;
}

const methodIcons: Record<PaymentMethod, typeof CreditCard> = {
    card: CreditCard,
    pix: QrCode,
    boleto: FileText,
};

/**
 * Payment Method Selector Component
 *
 * Radio group for selecting between card, PIX, and boleto payment methods.
 * Used in checkout flows to choose payment type.
 */
export function PaymentMethodSelector({
    value,
    onChange,
    availableMethods = ['card', 'pix', 'boleto'],
    disabled = false,
}: PaymentMethodSelectorProps) {
    const { t } = useLaravelReactI18n();

    return (
        <RadioGroup
            value={value}
            onValueChange={(v) => onChange(v as PaymentMethod)}
            disabled={disabled}
            className="grid gap-3"
        >
            {availableMethods.map((method) => {
                const Icon = methodIcons[method];
                const label = t(`billing.payment_methods.${method}`);
                const description = t(`billing.payment_methods.${method}.description`);
                const isSelected = value === method;

                return (
                    <Label
                        key={method}
                        htmlFor={method}
                        className={cn(
                            'cursor-pointer',
                            disabled && 'cursor-not-allowed opacity-50',
                        )}
                    >
                        <Card
                            className={cn(
                                'transition-colors',
                                isSelected && 'border-primary bg-primary/5',
                            )}
                            data-testid={`payment-method-${method}`}
                        >
                            <CardContent className="flex items-center gap-4 p-4">
                                <RadioGroupItem value={method} id={method} />
                                <Icon
                                    className={cn(
                                        'h-6 w-6',
                                        isSelected
                                            ? 'text-primary'
                                            : 'text-muted-foreground',
                                    )}
                                />
                                <div className="flex-1">
                                    <p className="font-medium">{label}</p>
                                    <p className="text-sm text-muted-foreground">
                                        {description}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </Label>
                );
            })}
        </RadioGroup>
    );
}
