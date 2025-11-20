/**
 * Examples of using Permissions in React/Inertia components
 *
 * This file demonstrates best practices for checking permissions in the frontend.
 * All permissions are checked on the backend via Policies - these are just for UI.
 */

import { usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { PageProps } from '@/types';

export function ProjectsExample() {
    const { auth } = usePage<PageProps>().props;
    const { permissions } = auth;

    // ✅ RECOMMENDED: Use granular permissions
    return (
        <div>
            <h1>Projects</h1>

            {/* Show create button only if user can create */}
            {permissions?.projects.create && (
                <Button onClick={handleCreate}>
                    Create Project
                </Button>
            )}

            {/* Show edit button only if user can edit any project OR their own */}
            {(permissions?.projects.edit || permissions?.projects.editOwn) && (
                <Button onClick={handleEdit}>
                    Edit
                </Button>
            )}

            {/* Show delete button only if user can delete */}
            {permissions?.projects.delete && (
                <Button variant="destructive" onClick={handleDelete}>
                    Delete
                </Button>
            )}

            {/* Upload files */}
            {permissions?.projects.upload && (
                <input type="file" onChange={handleUpload} />
            )}
        </div>
    );
}

export function TeamExample() {
    const { auth } = usePage<PageProps>().props;
    const { permissions } = auth;

    return (
        <div>
            <h1>Team Members</h1>

            {/* Invite button - only for users with invite permission */}
            {permissions?.team.invite && (
                <Button onClick={handleInvite}>
                    Invite Member
                </Button>
            )}

            {/* Role management - only for users with manageRoles permission */}
            {permissions?.team.manageRoles && (
                <select onChange={handleRoleChange}>
                    <option value="owner">Owner</option>
                    <option value="admin">Admin</option>
                    <option value="member">Member</option>
                </select>
            )}

            {/* Remove button - only if can remove members */}
            {permissions?.team.remove && (
                <Button variant="destructive" onClick={handleRemove}>
                    Remove
                </Button>
            )}
        </div>
    );
}

export function SettingsExample() {
    const { auth } = usePage<PageProps>().props;
    const { permissions } = auth;

    return (
        <div>
            <h1>Settings</h1>

            {/* Settings form - read-only if user can't edit */}
            <form>
                <input
                    type="text"
                    disabled={!permissions?.settings.edit}
                />

                {permissions?.settings.edit && (
                    <Button type="submit">
                        Save Settings
                    </Button>
                )}
            </form>

            {/* Danger zone - only for users with danger permission */}
            {permissions?.settings.danger && (
                <div className="border-2 border-red-500 p-4">
                    <h2>Danger Zone</h2>
                    <Button variant="destructive">
                        Delete Tenant
                    </Button>
                </div>
            )}
        </div>
    );
}

export function BillingExample() {
    const { auth } = usePage<PageProps>().props;
    const { permissions } = auth;

    // Redirect if user can't view billing
    if (!permissions?.billing.view) {
        return <div>Access Denied</div>;
    }

    return (
        <div>
            <h1>Billing</h1>

            {/* Show subscription details */}
            <div>Current Plan: Premium</div>

            {/* Manage subscription - only if user can manage billing */}
            {permissions?.billing.manage && (
                <>
                    <Button onClick={handleUpgrade}>
                        Upgrade Plan
                    </Button>
                    <Button onClick={handleCancel}>
                        Cancel Subscription
                    </Button>
                </>
            )}

            {/* Download invoices - if user has permission */}
            {permissions?.billing.invoices && (
                <Button onClick={handleDownloadInvoice}>
                    Download Invoice
                </Button>
            )}
        </div>
    );
}

// ❌ DEPRECATED: Avoid using legacy gates
export function LegacyExample() {
    const { auth } = usePage<PageProps>().props;
    const { permissions } = auth;

    return (
        <div>
            {/* ❌ Old way - less granular */}
            {permissions?.canManageTeam && <Button>Manage Team</Button>}

            {/* ✅ New way - more granular */}
            {permissions?.team.invite && <Button>Invite Member</Button>}
            {permissions?.team.remove && <Button>Remove Member</Button>}
            {permissions?.team.manageRoles && <Button>Change Roles</Button>}
        </div>
    );
}

// ⚠️ IMPORTANT: Role checks are for UI display only!
// Never rely on role checks for security - always use permissions
export function RoleDisplayExample() {
    const { auth } = usePage<PageProps>().props;
    const { permissions } = auth;

    return (
        <div>
            {/* ✅ OK: Use role for display/badges */}
            <div className="badge">
                {permissions?.isOwner && <span>Owner</span>}
                {permissions?.isAdmin && <span>Admin</span>}
                {permissions?.role === 'member' && <span>Member</span>}
            </div>

            {/* ❌ WRONG: Don't use role for authorization */}
            {/* This is wrong because roles can have custom permissions */}
            {permissions?.isOwner && <Button>Delete Project</Button>}

            {/* ✅ CORRECT: Use permissions for authorization */}
            {permissions?.projects.delete && <Button>Delete Project</Button>}
        </div>
    );
}

// Complex permission logic
export function ComplexPermissionExample() {
    const { auth } = usePage<PageProps>().props;
    const { permissions } = auth;
    const project = { user_id: 123 }; // current project
    const currentUserId = auth.user?.id;

    // Can edit if:
    // - User has global edit permission, OR
    // - User has edit-own permission AND is the project owner
    const canEdit =
        permissions?.projects.edit ||
        (permissions?.projects.editOwn && project.user_id === currentUserId);

    return (
        <div>
            {canEdit && (
                <Button onClick={handleEdit}>
                    Edit Project
                </Button>
            )}
        </div>
    );
}

// Helper hooks (optional)
export function usePermissions() {
    const { auth } = usePage<PageProps>().props;
    return auth.permissions;
}

export function useHasPermission(permission: string) {
    const permissions = usePermissions();

    // Parse permission path (e.g., 'projects.create')
    const [category, action] = permission.split('.');

    if (!permissions || !category || !action) return false;

    return permissions[category as keyof typeof permissions]?.[action as never] ?? false;
}

// Usage with custom hook
export function ProjectsWithHook() {
    const canCreate = useHasPermission('projects.create');
    const canDelete = useHasPermission('projects.delete');

    return (
        <div>
            {canCreate && <Button>Create</Button>}
            {canDelete && <Button>Delete</Button>}
        </div>
    );
}

// Dummy handlers for examples
const handleCreate = () => console.log('create');
const handleEdit = () => console.log('edit');
const handleDelete = () => console.log('delete');
const handleUpload = () => console.log('upload');
const handleInvite = () => console.log('invite');
const handleRoleChange = () => console.log('role change');
const handleRemove = () => console.log('remove');
const handleUpgrade = () => console.log('upgrade');
const handleCancel = () => console.log('cancel');
const handleDownloadInvoice = () => console.log('download invoice');
