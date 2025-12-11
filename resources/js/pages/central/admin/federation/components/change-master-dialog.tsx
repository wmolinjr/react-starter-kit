import { useForm } from '@inertiajs/react';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { AlertTriangle, Crown } from 'lucide-react';
import admin from '@/routes/central/admin';
import type { FederationGroupTenant } from '@/types';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    groupId: string;
    groupName: string;
    currentMasterId: string;
    tenants: FederationGroupTenant[];
}

export function ChangeMasterDialog({
    open,
    onOpenChange,
    groupId,
    groupName,
    currentMasterId,
    tenants,
}: Props) {
    const { t } = useLaravelReactI18n();
    const { data, setData, post, processing, reset } = useForm({
        new_master_tenant_id: '',
        confirm: false,
    });

    const availableTenants = tenants.filter((tenant) => tenant.id !== currentMasterId);
    const selectedTenant = tenants.find((t) => t.id === data.new_master_tenant_id);

    const handleSubmit = () => {
        post(admin.federation.changeMaster.url(groupId), {
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
        });
    };

    const handleOpenChange = (newOpen: boolean) => {
        if (!newOpen) {
            reset();
        }
        onOpenChange(newOpen);
    };

    return (
        <AlertDialog open={open} onOpenChange={handleOpenChange}>
            <AlertDialogContent className="max-w-lg">
                <AlertDialogHeader>
                    <AlertDialogTitle className="flex items-center gap-2">
                        <Crown className="h-5 w-5 text-yellow-500" />
                        {t('federation.change_master.title')}
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        {t('federation.change_master.description', { group: groupName })}
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <div className="space-y-4 py-4">
                    <Alert variant="destructive">
                        <AlertTriangle className="h-4 w-4" />
                        <AlertDescription>
                            {t('federation.change_master.warning')}
                        </AlertDescription>
                    </Alert>

                    <div className="space-y-2">
                        <Label>{t('federation.change_master.new_master')}</Label>
                        <Select
                            value={data.new_master_tenant_id}
                            onValueChange={(value) => setData('new_master_tenant_id', value)}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder={t('federation.change_master.select_tenant')} />
                            </SelectTrigger>
                            <SelectContent>
                                {availableTenants.map((tenant) => (
                                    <SelectItem key={tenant.id} value={tenant.id}>
                                        <span className="flex items-center gap-2">
                                            <Crown className="h-4 w-4 text-muted-foreground" />
                                            {tenant.name}
                                        </span>
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {selectedTenant && (
                            <p className="text-muted-foreground text-sm">
                                {t('federation.change_master.selected_info', {
                                    tenant: selectedTenant.name,
                                })}
                            </p>
                        )}
                    </div>

                    <div className="flex items-start gap-3 rounded-lg border p-4">
                        <Checkbox
                            id="confirm"
                            checked={data.confirm}
                            onCheckedChange={(checked) => setData('confirm', checked === true)}
                        />
                        <Label htmlFor="confirm" className="cursor-pointer text-sm leading-tight">
                            {t('federation.change_master.confirm_text')}
                        </Label>
                    </div>
                </div>

                <AlertDialogFooter>
                    <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
                    <AlertDialogAction
                        onClick={handleSubmit}
                        disabled={!data.new_master_tenant_id || !data.confirm || processing}
                        className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                    >
                        {processing
                            ? t('common.processing')
                            : t('federation.change_master.confirm_button')}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
