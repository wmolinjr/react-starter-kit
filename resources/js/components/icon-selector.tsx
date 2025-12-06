import { useLaravelReactI18n } from 'laravel-react-i18n';
import { Search } from 'lucide-react';
import { useState, useMemo } from 'react';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { DynamicIcon } from '@/components/dynamic-icon';

// Curated list of icons useful for bundles and addons
const ICON_PRESETS = [
    // Packages & Products
    'Package', 'PackageOpen', 'Box', 'Boxes', 'Gift', 'ShoppingBag', 'ShoppingCart',
    // Power & Energy
    'Zap', 'ZapOff', 'Battery', 'BatteryFull', 'Bolt', 'Flame', 'Sparkles',
    // Users & Teams
    'Users', 'UserPlus', 'UserCheck', 'UsersRound', 'Contact', 'HeartHandshake',
    // Business & Enterprise
    'Building', 'Building2', 'Briefcase', 'BadgeCheck', 'Award', 'Trophy', 'Crown',
    // Storage & Data
    'HardDrive', 'Database', 'Server', 'Cloud', 'CloudUpload', 'FolderOpen', 'Archive',
    // Analytics & Charts
    'BarChart', 'BarChart2', 'BarChart3', 'LineChart', 'PieChart', 'TrendingUp', 'Activity',
    // Security & Protection
    'Shield', 'ShieldCheck', 'Lock', 'Key', 'Fingerprint', 'Eye', 'EyeOff',
    // Communication
    'Mail', 'MessageSquare', 'MessagesSquare', 'Bell', 'Phone', 'Video', 'Headphones',
    // Tools & Settings
    'Settings', 'Wrench', 'Cog', 'SlidersHorizontal', 'Hammer', 'Paintbrush',
    // Navigation & Actions
    'Rocket', 'Target', 'Compass', 'Map', 'Navigation', 'Send', 'ArrowUpRight', 'Check',
    // Premium & Value
    'Gem', 'Diamond', 'Star', 'Stars', 'Coins', 'Wallet', 'CreditCard', 'DollarSign',
    // Time & Speed
    'Clock', 'Timer', 'Calendar', 'CalendarCheck', 'Hourglass', 'Gauge',
    // Documents & Content
    'FileText', 'Files', 'ClipboardList', 'BookOpen', 'Newspaper', 'ScrollText',
    // Development & Code
    'Code', 'Terminal', 'Braces', 'FileCode', 'GitBranch', 'Blocks', 'Puzzle',
    // Media & Design
    'Image', 'Camera', 'Palette', 'Layers', 'Layout', 'Monitor', 'Smartphone',
    // Nature & Other
    'Globe', 'Leaf', 'Sun', 'Moon', 'Lightbulb', 'Heart', 'ThumbsUp',
] as const;

interface IconSelectorProps {
    label?: string;
    value: string;
    onChange: (value: string) => void;
    iconColor?: string | null;
    className?: string;
}

// Map color names to hex values for inline styles (ensures colors work regardless of Tailwind purging)
function getIconColorHex(color: string): string {
    const colorMap: Record<string, string> = {
        slate: '#64748b',
        gray: '#6b7280',
        zinc: '#71717a',
        red: '#ef4444',
        orange: '#f97316',
        amber: '#f59e0b',
        yellow: '#eab308',
        lime: '#84cc16',
        green: '#22c55e',
        emerald: '#10b981',
        teal: '#14b8a6',
        cyan: '#06b6d4',
        sky: '#0ea5e9',
        blue: '#3b82f6',
        indigo: '#6366f1',
        violet: '#8b5cf6',
        purple: '#a855f7',
        fuchsia: '#d946ef',
        pink: '#ec4899',
        rose: '#f43f5e',
    };
    return colorMap[color] ?? '';
}

export function IconSelector({ label, value, onChange, iconColor, className }: IconSelectorProps) {
    const { t } = useLaravelReactI18n();
    const [search, setSearch] = useState('');
    const colorHex = iconColor ? getIconColorHex(iconColor) : undefined;
    const colorStyle = colorHex ? { color: colorHex } : undefined;

    const filteredIcons = useMemo(() => {
        if (!search.trim()) return ICON_PRESETS;
        const searchLower = search.toLowerCase();
        return ICON_PRESETS.filter(icon =>
            icon.toLowerCase().includes(searchLower)
        );
    }, [search]);

    return (
        <div className={cn('space-y-3', className)}>
            {label && <Label>{label}</Label>}

            {/* Preview */}
            <div className="flex items-center gap-3">
                <span className="text-sm text-muted-foreground">{t('icons.preview')}:</span>
                <div className="flex items-center gap-2 rounded-lg border bg-muted/50 px-3 py-2" style={colorStyle}>
                    <DynamicIcon name={value} className="h-5 w-5" />
                    <span className="text-sm font-medium">{value}</span>
                </div>
            </div>

            {/* Search */}
            <div className="relative">
                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    type="text"
                    placeholder={t('icons.search_placeholder')}
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    className="pl-9"
                />
            </div>

            {/* Icon grid */}
            <div className="max-h-64 overflow-y-auto rounded-lg border p-2">
                <div className="grid grid-cols-6 gap-1 sm:grid-cols-8 md:grid-cols-10">
                    {filteredIcons.map((iconName) => (
                        <button
                            key={iconName}
                            type="button"
                            onClick={() => onChange(iconName)}
                            title={iconName}
                            className={cn(
                                'flex h-10 w-10 items-center justify-center rounded-md transition-all',
                                'hover:bg-accent hover:text-accent-foreground',
                                value === iconName
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-transparent'
                            )}
                        >
                            <DynamicIcon name={iconName} className="h-5 w-5" />
                        </button>
                    ))}
                </div>
                {filteredIcons.length === 0 && (
                    <p className="py-4 text-center text-sm text-muted-foreground">
                        {t('icons.no_results')}
                    </p>
                )}
            </div>

            {/* Custom input */}
            <div className="flex items-center gap-2">
                <span className="text-xs text-muted-foreground">{t('icons.custom_hint')}:</span>
                <Input
                    type="text"
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    className="h-8 max-w-[200px] text-sm"
                    placeholder="IconName"
                />
            </div>
        </div>
    );
}
