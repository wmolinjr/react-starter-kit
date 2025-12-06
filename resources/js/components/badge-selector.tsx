import { useLaravelReactI18n } from 'laravel-react-i18n';
import { X } from 'lucide-react';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { DynamicIcon } from '@/components/dynamic-icon';

export interface BadgePreset {
    value: string;
    label: string;
    icon: string;
    bg: string;
    text: string;
    border: string;
}

interface BadgeSelectorProps {
    label?: string;
    value: string | null;
    onChange: (value: string | null) => void;
    presets: BadgePreset[];
    className?: string;
}

export function BadgeSelector({ label, value, onChange, presets, className }: BadgeSelectorProps) {
    const { t } = useLaravelReactI18n();
    const selectedPreset = presets.find(p => p.value === value);

    return (
        <div className={cn('space-y-3', className)}>
            {label && <Label>{label}</Label>}

            {/* Preview */}
            <div className="flex items-center gap-2">
                <span className="text-sm text-muted-foreground">{t('badges.preview')}:</span>
                {selectedPreset ? (
                    <span
                        className={cn(
                            'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm font-medium',
                            selectedPreset.bg,
                            selectedPreset.text,
                            selectedPreset.border
                        )}
                    >
                        <DynamicIcon name={selectedPreset.icon} className="h-3.5 w-3.5" />
                        {selectedPreset.label}
                    </span>
                ) : (
                    <span className="text-sm text-muted-foreground italic">{t('badges.none')}</span>
                )}
            </div>

            {/* Badge grid */}
            <div className="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6">
                {/* No badge option */}
                <button
                    type="button"
                    onClick={() => onChange(null)}
                    className={cn(
                        'flex flex-col items-center gap-1 rounded-lg border-2 p-3 transition-all',
                        'hover:border-muted-foreground/50',
                        !value
                            ? 'border-primary bg-primary/5'
                            : 'border-transparent bg-muted/50'
                    )}
                >
                    <X className="h-5 w-5 text-muted-foreground" />
                    <span className="text-xs text-muted-foreground">{t('badges.none')}</span>
                </button>

                {/* Badge presets */}
                {presets.map((preset) => (
                    <button
                        key={preset.value}
                        type="button"
                        onClick={() => onChange(preset.value)}
                        className={cn(
                            'flex flex-col items-center gap-1 rounded-lg border-2 p-3 transition-all',
                            'hover:border-muted-foreground/50',
                            value === preset.value
                                ? 'border-primary bg-primary/5'
                                : 'border-transparent bg-muted/50'
                        )}
                    >
                        <span
                            className={cn(
                                'flex h-8 w-8 items-center justify-center rounded-full',
                                preset.bg,
                                preset.text
                            )}
                        >
                            <DynamicIcon name={preset.icon} className="h-4 w-4" />
                        </span>
                        <span className="text-xs text-center leading-tight">{preset.label}</span>
                    </button>
                ))}
            </div>
        </div>
    );
}
