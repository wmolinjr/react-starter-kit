import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Minus, Plus } from 'lucide-react';

interface QuantitySelectorProps {
    value: number;
    onChange: (value: number) => void;
    min?: number;
    max?: number;
    disabled?: boolean;
}

export function QuantitySelector({ value, onChange, min = 1, max = 100, disabled }: QuantitySelectorProps) {
    const handleDecrement = () => {
        if (value > min) {
            onChange(value - 1);
        }
    };

    const handleIncrement = () => {
        if (value < max) {
            onChange(value + 1);
        }
    };

    const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const newValue = parseInt(e.target.value, 10);
        if (!isNaN(newValue) && newValue >= min && newValue <= max) {
            onChange(newValue);
        }
    };

    return (
        <div className="flex items-center gap-2">
            <Button
                type="button"
                variant="outline"
                size="icon"
                onClick={handleDecrement}
                disabled={disabled || value <= min}
            >
                <Minus className="h-4 w-4" />
            </Button>
            <Input
                type="number"
                value={value}
                onChange={handleInputChange}
                min={min}
                max={max}
                disabled={disabled}
                className="w-20 text-center"
            />
            <Button
                type="button"
                variant="outline"
                size="icon"
                onClick={handleIncrement}
                disabled={disabled || value >= max}
            >
                <Plus className="h-4 w-4" />
            </Button>
        </div>
    );
}
