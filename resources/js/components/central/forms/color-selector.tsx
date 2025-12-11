import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Check, Circle } from 'lucide-react';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

// Curated palette of colors that work well for icons
const COLOR_PRESETS = [
    { value: 'slate', label: 'Slate', bg: 'bg-slate-500', text: 'text-slate-500' },
    { value: 'gray', label: 'Gray', bg: 'bg-gray-500', text: 'text-gray-500' },
    { value: 'zinc', label: 'Zinc', bg: 'bg-zinc-500', text: 'text-zinc-500' },
    { value: 'red', label: 'Red', bg: 'bg-red-500', text: 'text-red-500' },
    { value: 'orange', label: 'Orange', bg: 'bg-orange-500', text: 'text-orange-500' },
    { value: 'amber', label: 'Amber', bg: 'bg-amber-500', text: 'text-amber-500' },
    { value: 'yellow', label: 'Yellow', bg: 'bg-yellow-500', text: 'text-yellow-500' },
    { value: 'lime', label: 'Lime', bg: 'bg-lime-500', text: 'text-lime-500' },
    { value: 'green', label: 'Green', bg: 'bg-green-500', text: 'text-green-500' },
    { value: 'emerald', label: 'Emerald', bg: 'bg-emerald-500', text: 'text-emerald-500' },
    { value: 'teal', label: 'Teal', bg: 'bg-teal-500', text: 'text-teal-500' },
    { value: 'cyan', label: 'Cyan', bg: 'bg-cyan-500', text: 'text-cyan-500' },
    { value: 'sky', label: 'Sky', bg: 'bg-sky-500', text: 'text-sky-500' },
    { value: 'blue', label: 'Blue', bg: 'bg-blue-500', text: 'text-blue-500' },
    { value: 'indigo', label: 'Indigo', bg: 'bg-indigo-500', text: 'text-indigo-500' },
    { value: 'violet', label: 'Violet', bg: 'bg-violet-500', text: 'text-violet-500' },
    { value: 'purple', label: 'Purple', bg: 'bg-purple-500', text: 'text-purple-500' },
    { value: 'fuchsia', label: 'Fuchsia', bg: 'bg-fuchsia-500', text: 'text-fuchsia-500' },
    { value: 'pink', label: 'Pink', bg: 'bg-pink-500', text: 'text-pink-500' },
    { value: 'rose', label: 'Rose', bg: 'bg-rose-500', text: 'text-rose-500' },
] as const;

export type IconColor = (typeof COLOR_PRESETS)[number]['value'] | null;

interface ColorSelectorProps {
    label?: string;
    value: string | null;
    onChange: (value: string | null) => void;
    className?: string;
}

export function getIconColorClass(color: string | null | undefined): string {
    if (!color) return '';
    const preset = COLOR_PRESETS.find((p) => p.value === color);
    return preset?.text ?? '';
}

export function ColorSelector({ label, value, onChange, className }: ColorSelectorProps) {
    const { t } = useLaravelReactI18n();

    const selectedPreset = value ? COLOR_PRESETS.find((p) => p.value === value) : null;
    const isSystemColor = !value;

    return (
        <div className={cn('space-y-3', className)}>
            {label && <Label>{label}</Label>}

            {/* Color grid */}
            <div className="flex flex-wrap gap-2">
                {/* System color option */}
                <button
                    type="button"
                    onClick={() => onChange(null)}
                    title={t('components.color_selector.system')}
                    className={cn(
                        'relative flex h-8 w-8 items-center justify-center rounded-full transition-all',
                        'border-2 border-dashed border-muted-foreground/50 bg-background',
                        'hover:ring-2 hover:ring-offset-2 hover:ring-offset-background',
                        isSystemColor
                            ? 'ring-2 ring-offset-2 ring-offset-background ring-primary border-primary'
                            : ''
                    )}
                >
                    {isSystemColor ? (
                        <Check className="h-4 w-4 text-primary" />
                    ) : (
                        <Circle className="h-4 w-4 text-muted-foreground/50" />
                    )}
                </button>

                {COLOR_PRESETS.map((preset) => (
                    <button
                        key={preset.value}
                        type="button"
                        onClick={() => onChange(preset.value)}
                        title={preset.label}
                        className={cn(
                            'relative flex h-8 w-8 items-center justify-center rounded-full transition-all',
                            preset.bg,
                            'hover:ring-2 hover:ring-offset-2 hover:ring-offset-background',
                            value === preset.value
                                ? 'ring-2 ring-offset-2 ring-offset-background ring-primary'
                                : ''
                        )}
                    >
                        {value === preset.value && <Check className="h-4 w-4 text-white" />}
                    </button>
                ))}
            </div>

            {/* Selected color name */}
            <p className="text-xs text-muted-foreground">
                {t('components.color_selector.selected')}:{' '}
                {isSystemColor ? (
                    <span className="font-medium">{t('components.color_selector.system')}</span>
                ) : (
                    <span className={cn('font-medium', selectedPreset?.text)}>{selectedPreset?.label}</span>
                )}
            </p>
        </div>
    );
}

export { COLOR_PRESETS };
