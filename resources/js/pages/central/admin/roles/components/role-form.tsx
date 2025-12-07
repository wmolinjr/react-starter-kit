import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { AlertCircle } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { TranslatableInput, Translations } from '@/components/central/forms/translatable-input';
import { useLocales } from '@/hooks/shared/use-locales';

interface Permission {
    id: string;
    name: string;
    description: string | null;
}

interface CategoryPermissions {
    label: string;
    permissions: Permission[];
}

interface RoleData {
    id?: string;
    name: string;
    display_name: Translations;
    description: Translations;
    permissions: string[];
    is_protected?: boolean;
}

interface Props {
    role?: RoleData;
    permissions: Record<string, CategoryPermissions>;
    onSubmit: (data: Omit<RoleData, 'id' | 'is_protected'>) => void;
}

export function RoleForm({ role, permissions, onSubmit }: Props) {
    const { t } = useLaravelReactI18n();
    const { ensureTranslations } = useLocales();
    const { data, setData, processing, errors } = useForm<Omit<RoleData, 'id' | 'is_protected'>>({
        name: role?.name ?? '',
        display_name: ensureTranslations(role?.display_name),
        description: ensureTranslations(role?.description),
        permissions: role?.permissions ?? [],
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        onSubmit(data);
    };

    const togglePermission = (permissionId: string) => {
        if (data.permissions.includes(permissionId)) {
            setData('permissions', data.permissions.filter((id) => id !== permissionId));
        } else {
            setData('permissions', [...data.permissions, permissionId]);
        }
    };

    const toggleCategory = (perms: Permission[]) => {
        const categoryIds = perms.map((p) => p.id);
        const allSelected = categoryIds.every((id) => data.permissions.includes(id));

        if (allSelected) {
            setData('permissions', data.permissions.filter((id) => !categoryIds.includes(id)));
        } else {
            const newPermissions = [...data.permissions];
            categoryIds.forEach((id) => {
                if (!newPermissions.includes(id)) {
                    newPermissions.push(id);
                }
            });
            setData('permissions', newPermissions);
        }
    };

    const getCategorySelectionState = (perms: Permission[]) => {
        const categoryIds = perms.map((p) => p.id);
        const selectedCount = categoryIds.filter((id) => data.permissions.includes(id)).length;
        if (selectedCount === 0) return 'none';
        if (selectedCount === categoryIds.length) return 'all';
        return 'partial';
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            {role?.is_protected && (
                <Alert>
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>
                        {t('admin.roles.form.protected_alert')}
                    </AlertDescription>
                </Alert>
            )}

            <Card>
                <CardHeader>
                    <CardTitle>{t('common.basic_info')}</CardTitle>
                    <CardDescription>{t('admin.roles.form.basic_info_description')}</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label htmlFor="name">{t('admin.roles.form.name_label')}</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder={t('admin.roles.form.name_placeholder')}
                                disabled={role?.is_protected}
                                required
                            />
                            {errors.name && (
                                <p className="text-destructive text-sm">{errors.name}</p>
                            )}
                            <p className="text-muted-foreground text-xs">
                                {t('admin.roles.form.name_hint')}
                            </p>
                        </div>
                        <TranslatableInput
                            label={t('admin.roles.form.display_name_label')}
                            value={data.display_name}
                            onChange={(v) => setData('display_name', v)}
                            placeholder={{ en: 'Project Manager', pt_BR: 'Gerente de Projetos' }}
                            required
                        />
                    </div>

                    <TranslatableInput
                        label={t('common.description')}
                        value={data.description}
                        onChange={(v) => setData('description', v)}
                        placeholder={{ en: 'Role description...', pt_BR: 'Descrição do papel...' }}
                        multiline
                    />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{t('common.permissions')}</CardTitle>
                    <CardDescription>
                        {t('admin.roles.form.permissions_description')}
                        <Badge variant="secondary" className="ml-2">
                            {t('admin.roles.form.selected', { count: data.permissions.length })}
                        </Badge>
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    {Object.entries(permissions).map(([category, { label, permissions: categoryPerms }]) => {
                        const selectionState = getCategorySelectionState(categoryPerms);
                        return (
                            <div key={category} className="space-y-3">
                                <div className="flex items-center gap-2">
                                    <Switch
                                        id={`category-${category}`}
                                        checked={selectionState === 'all'}
                                        onCheckedChange={() => toggleCategory(categoryPerms)}
                                    />
                                    <Label
                                        htmlFor={`category-${category}`}
                                        className="text-base font-semibold cursor-pointer"
                                    >
                                        {label}
                                    </Label>
                                    <Badge variant="outline" className="text-xs">
                                        {categoryPerms.filter((p) => data.permissions.includes(p.id)).length}/
                                        {categoryPerms.length}
                                    </Badge>
                                </div>
                                <div className="border-muted ml-6 grid gap-2 border-l pl-4">
                                    {categoryPerms.map((permission) => (
                                        <div key={permission.id} className="flex items-start gap-2">
                                            <Switch
                                                id={`permission-${permission.id}`}
                                                checked={data.permissions.includes(permission.id)}
                                                onCheckedChange={() => togglePermission(permission.id)}
                                            />
                                            <div className="grid gap-0.5">
                                                <Label
                                                    htmlFor={`permission-${permission.id}`}
                                                    className="cursor-pointer font-normal"
                                                >
                                                    <code className="text-muted-foreground text-xs">
                                                        {permission.name}
                                                    </code>
                                                </Label>
                                                {permission.description && (
                                                    <p className="text-muted-foreground text-xs">
                                                        {permission.description}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        );
                    })}

                    {Object.keys(permissions).length === 0 && (
                        <p className="text-muted-foreground text-sm">
                            {t('admin.roles.form.no_permissions')}
                        </p>
                    )}
                </CardContent>
            </Card>

            <div className="flex justify-end gap-2">
                <Button type="button" variant="outline" onClick={() => window.history.back()}>
                    {t('common.cancel')}
                </Button>
                <Button type="submit" disabled={processing}>
                    {role?.id ? t('admin.roles.update_role') : t('admin.roles.create_role')}
                </Button>
            </div>
        </form>
    );
}
