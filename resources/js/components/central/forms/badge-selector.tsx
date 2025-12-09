import { useLaravelReactI18n } from 'laravel-react-i18n';
import { X } from 'lucide-react';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { DynamicIcon } from '@/components/shared/icons/dynamic-icon';
import { BADGE_PRESET } from '@/lib/enum-metadata';
import type { BadgePreset } from '@/types/enums';

interface BadgeSelectorProps {
    label?: string;
    value: BadgePreset | null;
    onChange: (value: BadgePreset | null) => void;
    className?: string;
}

/**
 * Badge preset selector component.
 *
 * Uses BADGE_PRESET metadata from enum-metadata.ts (single source of truth).
 * No need to pass presets as props - data is available client-side.
 */
export function BadgeSelector({ label, value, onChange, className }: BadgeSelectorProps) {
    const { t } = useLaravelReactI18n();
    const presets = Object.values(BADGE_PRESET);
    const selectedPreset = value ? BADGE_PRESET[value] : null;

    return (
        <div className={cn('space-y-3', className)}>
            {label && <Label>{label}</Label>}

            {/* Preview */}
            <div className="flex items-center gap-2">
                <span className="text-muted-foreground text-sm">{t('badges.preview')}:</span>
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
                    <span className="text-muted-foreground text-sm italic">{t('badges.none')}</span>
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
                            : 'bg-muted/50 border-transparent'
                    )}
                >
                    <X className="text-muted-foreground h-5 w-5" />
                    <span className="text-muted-foreground text-xs">{t('badges.none')}</span>
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
                                : 'bg-muted/50 border-transparent'
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
                        <span className="text-center text-xs leading-tight">{preset.label}</span>
                    </button>
                ))}
            </div>
        </div>
    );
}
