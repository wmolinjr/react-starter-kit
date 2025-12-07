import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';

interface BillingPeriodToggleProps {
    value: 'monthly' | 'yearly';
    onChange: (value: 'monthly' | 'yearly') => void;
    monthlyPrice?: string;
    yearlyPrice?: string;
    disabled?: boolean;
}

export function BillingPeriodToggle({
    value,
    onChange,
    monthlyPrice,
    yearlyPrice,
    disabled,
}: BillingPeriodToggleProps) {
    return (
        <ToggleGroup
            type="single"
            value={value}
            onValueChange={(v) => v && onChange(v as 'monthly' | 'yearly')}
            disabled={disabled}
            className="justify-start"
        >
            <ToggleGroupItem value="monthly" className="flex-col items-start px-4 py-2">
                <span className="font-medium">Monthly</span>
                {monthlyPrice && <span className="text-muted-foreground text-xs">{monthlyPrice}/mo</span>}
            </ToggleGroupItem>
            <ToggleGroupItem value="yearly" className="flex-col items-start px-4 py-2">
                <span className="font-medium">Yearly</span>
                {yearlyPrice && <span className="text-muted-foreground text-xs">{yearlyPrice}/yr</span>}
            </ToggleGroupItem>
        </ToggleGroup>
    );
}
