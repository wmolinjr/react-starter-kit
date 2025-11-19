import { useCallback, useEffect, useState } from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

interface ColorPickerProps {
    label: string;
    description?: string;
    value?: string | null;
    onChange: (color: string | null) => void;
}

export function ColorPicker({
    label,
    description,
    value,
    onChange,
}: ColorPickerProps) {
    const [color, setColor] = useState(value || '#000000');
    const [hexInput, setHexInput] = useState(value || '#000000');
    const [error, setError] = useState<string | null>(null);

    // Sync with external value changes
    useEffect(() => {
        if (value && value !== color) {
            setColor(value);
            setHexInput(value);
        }
    }, [value]);

    const validateHex = useCallback((hex: string): boolean => {
        const hexRegex = /^#[0-9A-F]{6}$/i;
        return hexRegex.test(hex);
    }, []);

    const handleColorChange = useCallback(
        (newColor: string) => {
            setColor(newColor);
            setHexInput(newColor);
            setError(null);
            onChange(newColor);
        },
        [onChange]
    );

    const handleHexInputChange = useCallback(
        (e: React.ChangeEvent<HTMLInputElement>) => {
            let value = e.target.value;

            // Add # if not present
            if (value && !value.startsWith('#')) {
                value = '#' + value;
            }

            setHexInput(value);

            // Validate and update if valid
            if (validateHex(value)) {
                setColor(value.toUpperCase());
                setError(null);
                onChange(value.toUpperCase());
            } else if (value.length >= 7) {
                setError('Invalid HEX color format (e.g., #FF5733)');
            } else {
                setError(null);
            }
        },
        [onChange, validateHex]
    );

    const handleHexInputBlur = useCallback(() => {
        // On blur, if the value is invalid, revert to the last valid color
        if (!validateHex(hexInput)) {
            setHexInput(color);
            setError(null);
        }
    }, [color, hexInput, validateHex]);

    return (
        <div className="space-y-2">
            <div>
                <Label htmlFor="color-picker">{label}</Label>
                {description && (
                    <p className="text-sm text-muted-foreground mt-1">{description}</p>
                )}
            </div>

            <div className="flex gap-3">
                {/* Visual Color Picker */}
                <div className="relative">
                    <input
                        type="color"
                        value={color}
                        onChange={(e) => handleColorChange(e.target.value.toUpperCase())}
                        className="w-14 h-10 rounded-md cursor-pointer border border-input"
                        title="Pick a color"
                    />
                    <div
                        className="absolute inset-0 rounded-md border-2 border-background pointer-events-none"
                        style={{ backgroundColor: color }}
                    />
                </div>

                {/* HEX Input */}
                <div className="flex-1">
                    <div className="relative">
                        <Input
                            id="color-picker"
                            type="text"
                            value={hexInput}
                            onChange={handleHexInputChange}
                            onBlur={handleHexInputBlur}
                            placeholder="#000000"
                            maxLength={7}
                            className={cn(
                                'font-mono uppercase',
                                error && 'border-destructive focus-visible:ring-destructive'
                            )}
                        />
                        <div
                            className="absolute right-3 top-1/2 -translate-y-1/2 w-6 h-6 rounded border border-input shadow-sm"
                            style={{ backgroundColor: validateHex(hexInput) ? hexInput : color }}
                        />
                    </div>
                    {error && (
                        <p className="text-sm text-destructive mt-1">{error}</p>
                    )}
                </div>
            </div>

            {/* Color Presets */}
            <div className="flex gap-2 pt-2">
                <p className="text-xs text-muted-foreground leading-8">Quick colors:</p>
                {[
                    '#000000', // Black
                    '#3B82F6', // Blue
                    '#10B981', // Green
                    '#F59E0B', // Orange
                    '#EF4444', // Red
                    '#8B5CF6', // Purple
                ].map((preset) => (
                    <button
                        key={preset}
                        type="button"
                        onClick={() => handleColorChange(preset)}
                        className={cn(
                            'w-8 h-8 rounded border-2 transition-all hover:scale-110',
                            color === preset ? 'border-foreground ring-2 ring-offset-2 ring-foreground' : 'border-input'
                        )}
                        style={{ backgroundColor: preset }}
                        title={preset}
                    />
                ))}
            </div>
        </div>
    );
}
