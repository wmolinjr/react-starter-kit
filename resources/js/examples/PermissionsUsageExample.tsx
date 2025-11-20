/**
 * Examples of using Permissions in React/Inertia components
 *
 * This file demonstrates best practices for checking permissions in the frontend.
 * All permissions are checked on the backend via Policies - these are just for UI.
 *
 * Permission system features:
 * - ✅ Type-safe: Auto-generated Permission types with autocomplete
 * - ✅ Dynamic: Only sends permissions user HAS (not all with booleans)
 * - ✅ Scalable: Works with 100+ permissions
 * - ✅ Flexible: Multiple ways to check permissions (hook, component, inline)
 */

import { Button } from '@/components/ui/button';
import { usePermissions, useCan } from '@/hooks/use-permissions';
import { Can } from '@/components/can';

export function ProjectsExample() {
    const { has } = usePermissions();

    // ✅ RECOMMENDED: Use has() method for inline checks
    return (
        <div>
            <h1>Projects</h1>

            {/* Show create button only if user can create */}
            {has('tenant.projects:create') && (
                <Button onClick={handleCreate}>Create Project</Button>
            )}

            {/* Show edit button only if user can edit any project OR their own */}
            {(has('tenant.projects:edit') || has('tenant.projects:editOwn')) && (
                <Button onClick={handleEdit}>Edit</Button>
            )}

            {/* Show delete button only if user can delete */}
            {has('tenant.projects:delete') && (
                <Button variant="destructive" onClick={handleDelete}>
                    Delete
                </Button>
            )}

            {/* Upload files */}
            {has('tenant.projects:upload') && <input type="file" onChange={handleUpload} />}
        </div>
    );
}

export function ProjectsWithComponent() {
    // ✅ ALTERNATIVE: Use <Can> component for cleaner JSX
    return (
        <div>
            <h1>Projects</h1>

            <Can permission="tenant.projects:create">
                <Button onClick={handleCreate}>Create Project</Button>
            </Can>

            {/* OR logic: Show if user has edit OR editOwn */}
            <Can any={['tenant.projects:edit', 'tenant.projects:editOwn']}>
                <Button onClick={handleEdit}>Edit</Button>
            </Can>

            {/* AND logic: Show if user has BOTH view AND download */}
            <Can all={['tenant.projects:view', 'tenant.projects:download']}>
                <Button onClick={handleDownload}>Download</Button>
            </Can>

            {/* With fallback */}
            <Can
                permission="tenant.projects:delete"
                fallback={<p>You need delete permission</p>}
            >
                <Button variant="destructive">Delete</Button>
            </Can>
        </div>
    );
}

export function ProjectsWithHook() {
    // ✅ ALTERNATIVE: Use useCan() hook for single permission
    const canCreate = useCan('tenant.projects:create');
    const canDelete = useCan('tenant.projects:delete');

    return (
        <div>
            <h1>Projects</h1>
            {canCreate && <Button onClick={handleCreate}>Create</Button>}
            {canDelete && <Button variant="destructive">Delete</Button>}
        </div>
    );
}

export function TeamExample() {
    const { has, hasAny } = usePermissions();

    return (
        <div>
            <h1>Team Members</h1>

            {/* Invite button - only for users with invite permission */}
            {has('tenant.team:invite') && (
                <Button onClick={handleInvite}>Invite Member</Button>
            )}

            {/* Role management - only for users with manageRoles permission */}
            {has('tenant.team:manageRoles') && (
                <select onChange={handleRoleChange}>
                    <option value="owner">Owner</option>
                    <option value="admin">Admin</option>
                    <option value="member">Member</option>
                </select>
            )}

            {/* Remove button - only if can remove members */}
            {has('tenant.team:remove') && (
                <Button variant="destructive" onClick={handleRemove}>
                    Remove
                </Button>
            )}

            {/* Multiple permissions check - OR logic */}
            {hasAny('tenant.team:invite', 'tenant.team:manageRoles') && (
                <div>You can manage team members</div>
            )}
        </div>
    );
}

export function SettingsExample() {
    const { has } = usePermissions();

    return (
        <div>
            <h1>Settings</h1>

            {/* Settings form - read-only if user can't edit */}
            <form>
                <input type="text" disabled={!has('tenant.settings:edit')} />

                {has('tenant.settings:edit') && <Button type="submit">Save Settings</Button>}
            </form>

            {/* Danger zone - only for users with danger permission */}
            {has('tenant.settings:danger') && (
                <div className="border-2 border-red-500 p-4">
                    <h2>Danger Zone</h2>
                    <Button variant="destructive">Delete Tenant</Button>
                </div>
            )}
        </div>
    );
}

export function BillingExample() {
    const { has } = usePermissions();

    // Redirect if user can't view billing
    if (!has('tenant.billing:view')) {
        return <div>Access Denied</div>;
    }

    return (
        <div>
            <h1>Billing</h1>

            {/* Show subscription details */}
            <div>Current Plan: Premium</div>

            {/* Manage subscription - only if user can manage billing */}
            {has('tenant.billing:manage') && (
                <>
                    <Button onClick={handleUpgrade}>Upgrade Plan</Button>
                    <Button onClick={handleCancel}>Cancel Subscription</Button>
                </>
            )}

            {/* Download invoices - if user has permission */}
            {has('tenant.billing:invoices') && (
                <Button onClick={handleDownloadInvoice}>Download Invoice</Button>
            )}
        </div>
    );
}

// ⚠️ IMPORTANT: Role checks are for UI display only!
// Never rely on role checks for security - always use permissions
export function RoleDisplayExample() {
    const { role, isOwner, isAdmin } = usePermissions();

    return (
        <div>
            {/* ✅ OK: Use role for display/badges */}
            <div className="badge">
                {isOwner && <span className="bg-purple-500">Owner</span>}
                {isAdmin && <span className="bg-blue-500">Admin</span>}
                {role?.name === 'member' && <span className="bg-gray-500">Member</span>}
            </div>

            <p>Current Role: {role?.name}</p>
        </div>
    );
}

// ❌ WRONG: Don't use role for authorization!
export function WrongRoleUsage() {
    const { isOwner, has } = usePermissions();

    return (
        <div>
            {/* ❌ WRONG: Using role for authorization */}
            {isOwner && <Button onClick={handleDelete}>Delete Project</Button>}

            {/* ✅ CORRECT: Use permissions for authorization */}
            {has('tenant.projects:delete') && (
                <Button onClick={handleDelete}>Delete Project</Button>
            )}
        </div>
    );
}

// Complex permission logic
export function ComplexPermissionExample() {
    const { has, hasAny, hasAll } = usePermissions();

    return (
        <div>
            {/* OR logic: Show if user has ANY of these permissions */}
            {hasAny('tenant.projects:edit', 'tenant.projects:editOwn') && (
                <Button onClick={handleEdit}>Edit Project</Button>
            )}

            {/* AND logic: Show if user has ALL of these permissions */}
            {hasAll('tenant.projects:view', 'tenant.projects:download') && (
                <Button onClick={handleDownload}>Download Files</Button>
            )}

            {/* Complex logic: Combine multiple checks */}
            {has('tenant.projects:view') &&
                (has('tenant.projects:edit') || has('tenant.projects:editOwn')) && (
                    <Button onClick={handleAdvancedEdit}>Advanced Edit</Button>
                )}
        </div>
    );
}

// Get all permissions user has
export function AllPermissionsExample() {
    const { all } = usePermissions();
    const permissions = all();

    return (
        <div>
            <h2>Your Permissions ({permissions.length})</h2>
            <ul>
                {permissions.map((perm) => (
                    <li key={perm}>{perm}</li>
                ))}
            </ul>
        </div>
    );
}

// Dummy handlers for examples
const handleCreate = () => console.log('create');
const handleEdit = () => console.log('edit');
const handleDelete = () => console.log('delete');
const handleUpload = () => console.log('upload');
const handleDownload = () => console.log('download');
const handleInvite = () => console.log('invite');
const handleRoleChange = () => console.log('role change');
const handleRemove = () => console.log('remove');
const handleUpgrade = () => console.log('upgrade');
const handleCancel = () => console.log('cancel');
const handleDownloadInvoice = () => console.log('download invoice');
const handleAdvancedEdit = () => console.log('advanced edit');
