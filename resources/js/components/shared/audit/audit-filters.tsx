import { useLaravelReactI18n } from 'laravel-react-i18n';
import { format, parse } from 'date-fns';
import { Filter, Search } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DatePicker } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

import type { AuditFilters as AuditFiltersType, AuditFiltersProps } from './types';

export function AuditFilters({
    filters,
    users,
    eventTypes,
    subjectTypes,
    logNames,
    config,
    onFiltersChange,
    onApplyFilters,
    onClearFilters,
}: AuditFiltersProps) {
    const { t } = useLaravelReactI18n();

    const handleFilterChange = (key: keyof AuditFiltersType, value: string | null) => {
        const newFilters = { ...filters, [key]: value === '__all__' ? null : value };
        onFiltersChange(newFilters);
    };

    const handleDateChange = (key: 'date_from' | 'date_to', date: Date | undefined) => {
        const value = date ? format(date, 'yyyy-MM-dd') : null;
        handleFilterChange(key, value);
    };

    const parseDate = (dateString: string | null): Date | undefined => {
        if (!dateString) return undefined;
        return parse(dateString, 'yyyy-MM-dd', new Date());
    };

    const hasActiveFilters = Object.values(filters).some((v) => v !== null && v !== '');

    return (
        <Card>
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center gap-2 text-base">
                    <Filter className="h-4 w-4" />
                    {t('common.filters')}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                    {/* Search */}
                    <div className="space-y-2 lg:col-span-2">
                        <label className="text-sm font-medium">
                            {t('common.search')}
                        </label>
                        <div className="relative">
                            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                placeholder={t(`${config.translationPrefix}.search_placeholder`)}
                                value={filters.search || ''}
                                onChange={(e) => handleFilterChange('search', e.target.value || null)}
                                className="pl-9"
                            />
                        </div>
                    </div>

                    {/* User Filter */}
                    <div className="space-y-2">
                        <label className="text-sm font-medium">
                            {t(config.userLabelKey)}
                        </label>
                        <Select
                            value={filters.user_id || '__all__'}
                            onValueChange={(value) => handleFilterChange('user_id', value)}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder={t('common.all')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__all__">{t('common.all')}</SelectItem>
                                {users.map((user) => (
                                    <SelectItem key={user.id} value={String(user.id)}>
                                        {user.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Event Type Filter */}
                    <div className="space-y-2">
                        <label className="text-sm font-medium">
                            {t(`${config.translationPrefix}.filter_event`)}
                        </label>
                        <Select
                            value={filters.event || '__all__'}
                            onValueChange={(value) => handleFilterChange('event', value)}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder={t('common.all')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__all__">{t('common.all')}</SelectItem>
                                {eventTypes.map((event) => {
                                    const translationKey = `${config.translationPrefix}.event_${event}`;
                                    const translatedEvent = t(translationKey);
                                    return (
                                        <SelectItem key={event} value={event}>
                                            {translatedEvent !== translationKey ? translatedEvent : event}
                                        </SelectItem>
                                    );
                                })}
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Subject Type Filter */}
                    <div className="space-y-2">
                        <label className="text-sm font-medium">
                            {t(`${config.translationPrefix}.filter_subject`)}
                        </label>
                        <Select
                            value={filters.subject_type || '__all__'}
                            onValueChange={(value) => handleFilterChange('subject_type', value)}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder={t('common.all')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__all__">{t('common.all')}</SelectItem>
                                {subjectTypes.map((type) => (
                                    <SelectItem key={type.value} value={type.value}>
                                        {type.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Log Name Filter */}
                    <div className="space-y-2">
                        <label className="text-sm font-medium">
                            {t(`${config.translationPrefix}.filter_log_name`)}
                        </label>
                        <Select
                            value={filters.log_name || '__all__'}
                            onValueChange={(value) => handleFilterChange('log_name', value)}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder={t('common.all')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__all__">{t('common.all')}</SelectItem>
                                {logNames.map((name) => (
                                    <SelectItem key={name} value={name}>
                                        {name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Date From */}
                    <div className="space-y-2">
                        <label className="text-sm font-medium">
                            {t(`${config.translationPrefix}.filter_date_from`)}
                        </label>
                        <DatePicker
                            value={parseDate(filters.date_from)}
                            onChange={(date) => handleDateChange('date_from', date)}
                            placeholder={t('common.select_date')}
                        />
                    </div>

                    {/* Date To */}
                    <div className="space-y-2">
                        <label className="text-sm font-medium">
                            {t(`${config.translationPrefix}.filter_date_to`)}
                        </label>
                        <DatePicker
                            value={parseDate(filters.date_to)}
                            onChange={(date) => handleDateChange('date_to', date)}
                            placeholder={t('common.select_date')}
                        />
                    </div>
                </div>

                <div className="mt-4 flex justify-end gap-2">
                    {hasActiveFilters && (
                        <Button variant="ghost" onClick={onClearFilters}>
                            {t('common.clear_filters')}
                        </Button>
                    )}
                    <Button onClick={onApplyFilters}>
                        {t('common.apply_filters')}
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}
