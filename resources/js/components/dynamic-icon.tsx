import * as LucideIcons from 'lucide-react';
import { Package } from 'lucide-react';

/**
 * Map color names to Tailwind text color classes
 */
export const iconColorClasses: Record<string, string> = {
    slate: 'text-slate-500',
    gray: 'text-gray-500',
    zinc: 'text-zinc-500',
    neutral: 'text-neutral-500',
    stone: 'text-stone-500',
    red: 'text-red-500',
    orange: 'text-orange-500',
    amber: 'text-amber-500',
    yellow: 'text-yellow-500',
    lime: 'text-lime-500',
    green: 'text-green-500',
    emerald: 'text-emerald-500',
    teal: 'text-teal-500',
    cyan: 'text-cyan-500',
    sky: 'text-sky-500',
    blue: 'text-blue-500',
    indigo: 'text-indigo-500',
    violet: 'text-violet-500',
    purple: 'text-purple-500',
    fuchsia: 'text-fuchsia-500',
    pink: 'text-pink-500',
    rose: 'text-rose-500',
};

/**
 * Get Tailwind class for icon color
 * Returns empty string for null/undefined to use system color
 */
export function getIconColorClass(color: string | null | undefined): string {
    if (!color) return '';
    return iconColorClasses[color] ?? '';
}

interface DynamicIconProps {
    /** Lucide icon name (e.g., "Package", "Zap", "Users") */
    name: string | null | undefined;
    /** Optional CSS class name */
    className?: string;
    /** Optional color name (e.g., "blue", "amber", "purple") */
    color?: string | null;
    /** Fallback icon component when name is not found */
    fallback?: React.ComponentType<{ className?: string }>;
}

/**
 * Renders a Lucide icon dynamically by name
 *
 * @example
 * // Basic usage
 * <DynamicIcon name="Package" className="h-5 w-5" />
 *
 * @example
 * // With color
 * <DynamicIcon name="Zap" className="h-5 w-5" color="amber" />
 *
 * @example
 * // With custom fallback
 * <DynamicIcon name={iconName} fallback={HelpCircle} />
 */
export function DynamicIcon({
    name,
    className,
    color,
    fallback: Fallback = Package,
}: DynamicIconProps) {
    if (!name) {
        return <Fallback className={className} />;
    }

    const icons = LucideIcons as unknown as Record<
        string,
        React.ComponentType<{ className?: string }>
    >;
    const Icon = icons[name];

    if (!Icon) {
        return <Fallback className={className} />;
    }

    // If color is provided, combine with className
    const colorClass = color ? getIconColorClass(color) : '';
    const combinedClassName = colorClass
        ? `${colorClass} ${className ?? ''}`
        : className;

    return <Icon className={combinedClassName} />;
}
