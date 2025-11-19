import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { TenantRole } from '@/types';

interface MemberRoleSelectProps {
    value: TenantRole;
    onChange: (value: TenantRole) => void;
    disabled?: boolean;
}

const roleDescriptions: Record<TenantRole, { label: string; description: string }> = {
    owner: {
        label: 'Owner',
        description: 'Full access and workspace ownership',
    },
    admin: {
        label: 'Admin',
        description: 'Can manage workspace and members',
    },
    member: {
        label: 'Member',
        description: 'Can view and edit content',
    },
};

export function MemberRoleSelect({ value, onChange, disabled }: MemberRoleSelectProps) {
    return (
        <Select value={value} onValueChange={onChange} disabled={disabled}>
            <SelectTrigger className="w-[180px]">
                <SelectValue placeholder="Select a role" />
            </SelectTrigger>
            <SelectContent>
                {Object.entries(roleDescriptions).map(([role, { label, description }]) => (
                    <SelectItem key={role} value={role}>
                        <div className="flex flex-col">
                            <span className="font-medium">{label}</span>
                            <span className="text-xs text-muted-foreground">{description}</span>
                        </div>
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
