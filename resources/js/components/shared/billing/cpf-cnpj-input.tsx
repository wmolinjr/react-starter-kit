import { forwardRef, useCallback } from 'react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCpfCnpj, isValidCpfCnpjLength } from '@/lib/formatters';
import { cn } from '@/lib/utils';

export interface CpfCnpjInputProps {
    /** Current value (formatted or unformatted) */
    value: string;
    /** Callback when value changes (returns formatted value) */
    onChange: (value: string) => void;
    /** Input ID for accessibility */
    id?: string;
    /** Show label (default: true) */
    showLabel?: boolean;
    /** Custom label text */
    label?: string;
    /** Placeholder text */
    placeholder?: string;
    /** Disable input */
    disabled?: boolean;
    /** Show validation error */
    error?: string | boolean;
    /** Required field */
    required?: boolean;
    /** Additional class names for container */
    className?: string;
    /** Additional class names for input */
    inputClassName?: string;
    /** Help text shown below input */
    helpText?: string;
}

/**
 * CPF/CNPJ Input Component
 *
 * Reusable input with auto-formatting for Brazilian tax IDs.
 * Automatically detects CPF (11 digits) vs CNPJ (14 digits).
 *
 * Used in:
 * - Signup checkout (PIX/Boleto)
 * - Tenant checkout (PIX/Boleto)
 * - Card payment forms (holder data)
 */
export const CpfCnpjInput = forwardRef<HTMLInputElement, CpfCnpjInputProps>(
    (
        {
            value,
            onChange,
            id = 'cpf-cnpj',
            showLabel = true,
            label,
            placeholder,
            disabled = false,
            error,
            required = false,
            className,
            inputClassName,
            helpText,
        },
        ref,
    ) => {
        const { t } = useLaravelReactI18n();

        const handleChange = useCallback(
            (e: React.ChangeEvent<HTMLInputElement>) => {
                const formatted = formatCpfCnpj(e.target.value);
                onChange(formatted);
            },
            [onChange],
        );

        const displayLabel = label ?? t('billing.form.cpf_cnpj', { default: 'CPF/CNPJ' });
        const displayPlaceholder = placeholder ?? '000.000.000-00';
        const displayHelpText =
            helpText ?? t('billing.form.cpf_cnpj_help', { default: 'Required for PIX and Boleto payments' });

        const hasError = Boolean(error);
        const errorMessage = typeof error === 'string' ? error : undefined;

        // Check if value is valid length for showing inline validation
        const isValid = value && isValidCpfCnpjLength(value);

        return (
            <div className={cn('space-y-1.5', className)}>
                {showLabel && (
                    <Label htmlFor={id} className={cn(hasError && 'text-destructive')}>
                        {displayLabel}
                        {required && <span className="ml-1 text-destructive">*</span>}
                    </Label>
                )}
                <Input
                    ref={ref}
                    id={id}
                    type="text"
                    inputMode="numeric"
                    placeholder={displayPlaceholder}
                    value={value}
                    onChange={handleChange}
                    disabled={disabled}
                    aria-invalid={hasError}
                    aria-describedby={helpText ? `${id}-help` : undefined}
                    className={cn(
                        'font-mono',
                        hasError && 'border-destructive focus-visible:ring-destructive',
                        isValid && !hasError && 'border-green-500 focus-visible:ring-green-500',
                        inputClassName,
                    )}
                />
                {errorMessage && <p className="text-xs text-destructive">{errorMessage}</p>}
                {helpText && !errorMessage && (
                    <p id={`${id}-help`} className="text-xs text-muted-foreground">
                        {displayHelpText}
                    </p>
                )}
            </div>
        );
    },
);

CpfCnpjInput.displayName = 'CpfCnpjInput';

/**
 * Re-export utilities from formatters for convenience.
 */
export { unformatCpfCnpj, isValidCpfCnpjLength } from '@/lib/formatters';
