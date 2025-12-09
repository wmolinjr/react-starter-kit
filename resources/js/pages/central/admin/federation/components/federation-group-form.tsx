import { useForm } from '@inertiajs/react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type { FormDataConvertible } from '@inertiajs/core';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { AlertCircle, Crown, Info, RefreshCw } from 'lucide-react';

interface Tenant {
    id: string;
    name: string;
    slug: string;
}

interface GroupData {
    id?: string;
    name: string;
    description: string;
    sync_strategy: 'master_wins' | 'last_write_wins' | 'manual_review';
    master_tenant_id: string;
    is_active: boolean;
    settings: {
        sync_password: boolean;
        sync_profile: boolean;
        sync_two_factor: boolean;
        sync_roles: boolean;
        auto_create_on_login: boolean;
        auto_federate_new_users: boolean;
    };
}

interface Props {
    group?: GroupData;
    tenants: Tenant[];
    onSubmit: (data: Omit<GroupData, 'id'>) => void;
}

export function FederationGroupForm({ group, tenants, onSubmit }: Props) {
    const { t } = useLaravelReactI18n();
    const { data, setData, processing, errors } = useForm<Omit<GroupData, 'id'>>({
        name: group?.name ?? '',
        description: group?.description ?? '',
        sync_strategy: group?.sync_strategy ?? 'master_wins',
        master_tenant_id: group?.master_tenant_id ?? '',
        is_active: group?.is_active ?? true,
        settings: {
            sync_password: group?.settings?.sync_password ?? true,
            sync_profile: group?.settings?.sync_profile ?? true,
            sync_two_factor: group?.settings?.sync_two_factor ?? true,
            sync_roles: group?.settings?.sync_roles ?? false,
            auto_create_on_login: group?.settings?.auto_create_on_login ?? true,
            auto_federate_new_users: group?.settings?.auto_federate_new_users ?? false,
        },
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        onSubmit(data);
    };

    const updateSettings = (key: keyof typeof data.settings, value: boolean) => {
        setData('settings', { ...data.settings, [key]: value });
    };

    const syncStrategyOptions = [
        {
            value: 'master_wins',
            label: t('admin.federation.sync_strategy.master_wins'),
            description: t('admin.federation.sync_strategy.master_wins_description'),
        },
        {
            value: 'last_write_wins',
            label: t('admin.federation.sync_strategy.last_write_wins'),
            description: t('admin.federation.sync_strategy.last_write_wins_description'),
        },
        {
            value: 'manual_review',
            label: t('admin.federation.sync_strategy.manual_review'),
            description: t('admin.federation.sync_strategy.manual_review_description'),
        },
    ];

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            {/* Basic Info */}
            <Card>
                <CardHeader>
                    <CardTitle>{t('common.basic_info')}</CardTitle>
                    <CardDescription>{t('admin.federation.form.basic_info_description')}</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="name">{t('common.name')} *</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder={t('admin.federation.form.name_placeholder')}
                                required
                            />
                            {errors.name && <p className="text-destructive text-sm">{errors.name}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="master_tenant_id">{t('admin.federation.master_tenant')} *</Label>
                            <Select
                                value={data.master_tenant_id}
                                onValueChange={(value) => setData('master_tenant_id', value)}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder={t('admin.federation.form.select_master')} />
                                </SelectTrigger>
                                <SelectContent>
                                    {tenants.map((tenant) => (
                                        <SelectItem key={tenant.id} value={tenant.id}>
                                            <span className="flex items-center gap-2">
                                                <Crown className="h-4 w-4 text-yellow-500" />
                                                {tenant.name}
                                            </span>
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.master_tenant_id && (
                                <p className="text-destructive text-sm">{errors.master_tenant_id}</p>
                            )}
                            <p className="text-muted-foreground text-xs">
                                {t('admin.federation.form.master_hint')}
                            </p>
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="description">{t('common.description')}</Label>
                        <Textarea
                            id="description"
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            placeholder={t('admin.federation.form.description_placeholder')}
                            rows={3}
                        />
                        {errors.description && <p className="text-destructive text-sm">{errors.description}</p>}
                    </div>

                    <div className="flex items-center gap-2">
                        <Switch
                            id="is_active"
                            checked={data.is_active}
                            onCheckedChange={(checked) => setData('is_active', checked)}
                        />
                        <Label htmlFor="is_active" className="cursor-pointer">
                            {t('admin.federation.form.is_active')}
                        </Label>
                    </div>
                </CardContent>
            </Card>

            {/* Sync Strategy */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <RefreshCw className="h-5 w-5" />
                        {t('admin.federation.sync_strategy.title')}
                    </CardTitle>
                    <CardDescription>{t('admin.federation.sync_strategy.description')}</CardDescription>
                </CardHeader>
                <CardContent>
                    <RadioGroup
                        value={data.sync_strategy}
                        onValueChange={(value: string) =>
                            setData('sync_strategy', value as 'master_wins' | 'last_write_wins' | 'manual_review')
                        }
                        className="space-y-3"
                    >
                        {syncStrategyOptions.map((option) => (
                            <div
                                key={option.value}
                                className={`flex items-start space-x-3 rounded-lg border p-4 transition-colors ${
                                    data.sync_strategy === option.value
                                        ? 'border-primary bg-primary/5'
                                        : 'border-border'
                                }`}
                            >
                                <RadioGroupItem value={option.value} id={option.value} className="mt-1" />
                                <div className="flex-1">
                                    <Label htmlFor={option.value} className="cursor-pointer font-medium">
                                        {option.label}
                                    </Label>
                                    <p className="text-muted-foreground text-sm">{option.description}</p>
                                </div>
                            </div>
                        ))}
                    </RadioGroup>
                    {errors.sync_strategy && <p className="text-destructive mt-2 text-sm">{errors.sync_strategy}</p>}
                </CardContent>
            </Card>

            {/* Sync Settings */}
            <Card>
                <CardHeader>
                    <CardTitle>{t('admin.federation.form.sync_settings')}</CardTitle>
                    <CardDescription>{t('admin.federation.form.sync_settings_description')}</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="flex items-center justify-between rounded-lg border p-4">
                            <div>
                                <Label className="font-medium">{t('admin.federation.settings.sync_password')}</Label>
                                <p className="text-muted-foreground text-sm">
                                    {t('admin.federation.settings.sync_password_description')}
                                </p>
                            </div>
                            <Switch
                                checked={data.settings.sync_password}
                                onCheckedChange={(checked) => updateSettings('sync_password', checked)}
                            />
                        </div>

                        <div className="flex items-center justify-between rounded-lg border p-4">
                            <div>
                                <Label className="font-medium">{t('admin.federation.settings.sync_profile')}</Label>
                                <p className="text-muted-foreground text-sm">
                                    {t('admin.federation.settings.sync_profile_description')}
                                </p>
                            </div>
                            <Switch
                                checked={data.settings.sync_profile}
                                onCheckedChange={(checked) => updateSettings('sync_profile', checked)}
                            />
                        </div>

                        <div className="flex items-center justify-between rounded-lg border p-4">
                            <div>
                                <Label className="font-medium">{t('admin.federation.settings.sync_two_factor')}</Label>
                                <p className="text-muted-foreground text-sm">
                                    {t('admin.federation.settings.sync_two_factor_description')}
                                </p>
                            </div>
                            <Switch
                                checked={data.settings.sync_two_factor}
                                onCheckedChange={(checked) => updateSettings('sync_two_factor', checked)}
                            />
                        </div>

                        <div className="flex items-center justify-between rounded-lg border p-4">
                            <div>
                                <Label className="font-medium">{t('admin.federation.settings.sync_roles')}</Label>
                                <p className="text-muted-foreground text-sm">
                                    {t('admin.federation.settings.sync_roles_description')}
                                </p>
                            </div>
                            <Switch
                                checked={data.settings.sync_roles}
                                onCheckedChange={(checked) => updateSettings('sync_roles', checked)}
                            />
                        </div>

                        <div className="flex items-center justify-between rounded-lg border p-4 md:col-span-2">
                            <div>
                                <Label className="font-medium">{t('admin.federation.settings.auto_create_on_login')}</Label>
                                <p className="text-muted-foreground text-sm">
                                    {t('admin.federation.settings.auto_create_on_login_description')}
                                </p>
                            </div>
                            <Switch
                                checked={data.settings.auto_create_on_login}
                                onCheckedChange={(checked) => updateSettings('auto_create_on_login', checked)}
                            />
                        </div>

                        <div className="flex items-center justify-between rounded-lg border border-primary/20 bg-primary/5 p-4 md:col-span-2">
                            <div>
                                <Label className="font-medium">{t('admin.federation.settings.auto_federate_new_users')}</Label>
                                <p className="text-muted-foreground text-sm">
                                    {t('admin.federation.settings.auto_federate_new_users_description')}
                                </p>
                            </div>
                            <Switch
                                checked={data.settings.auto_federate_new_users}
                                onCheckedChange={(checked) => updateSettings('auto_federate_new_users', checked)}
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Warning */}
            <Alert>
                <AlertCircle className="h-4 w-4" />
                <AlertDescription>{t('admin.federation.form.warning')}</AlertDescription>
            </Alert>

            {/* Actions */}
            <div className="flex justify-end gap-2">
                <Button type="button" variant="outline" onClick={() => window.history.back()}>
                    {t('common.cancel')}
                </Button>
                <Button type="submit" disabled={processing}>
                    {group?.id ? t('admin.federation.update_group') : t('admin.federation.create_group')}
                </Button>
            </div>
        </form>
    );
}
