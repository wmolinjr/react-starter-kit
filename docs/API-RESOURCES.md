# API Resources Documentation

## Overview

This project uses Laravel API Resources for consistent data transformation across all Inertia responses. All Resources extend `BaseResource` which provides common helper methods.

## Architecture

### Directory Structure

```
app/Http/Resources/
├── BaseResource.php              # Base class with helper methods
│
├── Central/                      # Resources for central database models
│   ├── TenantResource.php        # Tenant listing view
│   ├── TenantDetailResource.php  # Tenant with full details
│   ├── TenantEditResource.php    # Tenant for edit forms
│   ├── TenantSummaryResource.php # Minimal tenant info
│   ├── TenantCollection.php      # Paginated tenant collection
│   ├── PlanResource.php          # Plan listing view
│   ├── PlanDetailResource.php    # Plan with all details
│   ├── PlanEditResource.php      # Plan for edit forms
│   ├── PlanSummaryResource.php   # Plan for dropdowns
│   └── DomainResource.php        # Domain info
│
├── Tenant/                       # Resources for tenant database models
│   ├── UserResource.php          # Full user info
│   ├── UserSummaryResource.php   # Minimal user (for dropdowns)
│   ├── TeamMemberResource.php    # User with role/permissions
│   ├── ProjectResource.php       # Project listing view
│   ├── ProjectDetailResource.php # Project with media
│   ├── ProjectEditResource.php   # Project for edit forms
│   ├── ActivityResource.php      # Audit log entry
│   ├── MediaResource.php         # Media file info
│   └── UserInvitationResource.php # Pending team invitation
│
└── Shared/                    # Resources for models in both contexts
    ├── RoleResource.php          # Role listing view
    ├── RoleDetailResource.php    # Role with permissions/users
    ├── RoleEditResource.php      # Role for edit forms
    └── PermissionResource.php    # Permission info
```

### Resource Naming Conventions

| Suffix | Purpose | Example |
|--------|---------|---------|
| `Resource` | Listing views | `ProjectResource` |
| `DetailResource` | Show pages with relationships | `ProjectDetailResource` |
| `EditResource` | Edit forms (includes field values) | `ProjectEditResource` |
| `SummaryResource` | Minimal info for dropdowns/references | `PlanSummaryResource` |
| `Collection` | Paginated collections | `TenantCollection` |

## BaseResource

All Resources extend `BaseResource` which provides:

```php
abstract class BaseResource extends JsonResource
{
    // Disable 'data' wrapping for Inertia compatibility
    public static $wrap = null;

    // Get translated value with fallback
    protected function trans(string $key): ?string;

    // Get all translations for a field
    protected function translations(string $key): array;

    // Format date as ISO 8601 string (best for JS Date parsing)
    protected function formatIso($date): ?string;

    // Format date with custom format
    protected function formatDate($date, string $format = 'Y-m-d H:i'): ?string;

    // Format date as human-readable relative string
    protected function formatDiff($date): ?string;

    // Format date as date only (no time)
    protected function formatDateOnly($date): ?string;

    // Format currency value
    protected function formatCurrency(int $cents, string $currency = 'BRL'): string;

    // Get count from loaded relationship or compute it
    protected function countOrCompute(string $relation): int;
}
```

## Usage Examples

### Controller Usage

```php
use App\Http\Resources\Tenant\ProjectResource;
use App\Http\Resources\Tenant\ProjectDetailResource;

class ProjectController extends Controller
{
    public function index()
    {
        $projects = Project::with(['user', 'media'])->latest()->get();

        return Inertia::render('tenant/admin/projects/index', [
            'projects' => ProjectResource::collection($projects),
        ]);
    }

    public function show(Project $project)
    {
        $project->load(['user', 'media']);

        return Inertia::render('tenant/admin/projects/show', [
            'project' => new ProjectDetailResource($project),
        ]);
    }
}
```

### Resource Implementation

```php
<?php

namespace App\Http\Resources\Tenant;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class ProjectResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'created_at' => $this->formatIso($this->created_at),

            // Relationships (only when loaded)
            'user' => $this->when(
                $this->relationLoaded('user'),
                fn () => new UserSummaryResource($this->user)
            ),

            // Computed fields
            'attachments_count' => $this->countOrCompute('media'),
        ];
    }
}
```

### Service Layer Pattern

Services should return Eloquent models/collections, not formatted arrays:

```php
// CORRECT: Service returns models
class TeamService
{
    public function getTeamMembers(): Collection
    {
        return User::with('roles')
            ->orderBy('name')
            ->get();
    }
}

// Controller uses Resources for transformation
public function index()
{
    return Inertia::render('tenant/admin/team/index', [
        'members' => TeamMemberResource::collection(
            $this->teamService->getTeamMembers()
        ),
    ]);
}
```

## Best Practices

### 1. Always use `formatIso()` for dates

```php
// CORRECT: ISO format for JavaScript Date parsing
'created_at' => $this->formatIso($this->created_at),

// AVOID: Custom formats that JS can't parse
'created_at' => $this->created_at->format('d/m/Y'),
```

### 2. Use conditional loading for relationships

```php
// CORRECT: Only include when relationship is loaded
'user' => $this->when(
    $this->relationLoaded('user'),
    fn () => new UserSummaryResource($this->user)
),

// AVOID: Always loading (causes N+1)
'user' => new UserSummaryResource($this->user),
```

### 3. Use `countOrCompute()` for counts

```php
// CORRECT: Uses withCount if available, falls back to count()
'comments_count' => $this->countOrCompute('comments'),

// AVOID: Always computing (ignores withCount optimization)
'comments_count' => $this->comments()->count(),
```

### 4. Use translations for multilingual content

```php
// For displaying translated content
'name' => $this->trans('name'),

// For edit forms (all translations)
'name' => $this->translations('name'),
```

## Inertia Compatibility

API Resources are configured without wrapping for Inertia compatibility:

```php
// AppServiceProvider.php
public function boot(): void
{
    // Disable 'data' wrapping for API Resources (Inertia compatibility)
    JsonResource::withoutWrapping();
}
```

This ensures:
- Single resources return flat arrays: `{ id: 1, name: 'Project' }`
- Collections return arrays: `[{ id: 1 }, { id: 2 }]`
- NOT wrapped: `{ data: [...] }`

## Testing Resources

```php
use App\Http\Resources\Central\TenantResource;
use App\Models\Central\Tenant;

test('TenantResource formats tenant correctly', function () {
    $tenant = Tenant::factory()
        ->has(Domain::factory()->primary())
        ->create(['name' => 'Acme Corp']);

    $tenant->load('domains');

    $resource = new TenantResource($tenant);
    $array = $resource->resolve(request());

    expect($array)->toHaveKeys(['id', 'name', 'slug', 'domains']);
    expect($array['name'])->toBe('Acme Corp');
});
```

## Migration from Legacy Patterns

### Before (Service formatting)

```php
// Service
public function getTeamMembers(): Collection
{
    return User::with('roles')
        ->get()
        ->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->roles->first()?->name,
        ]);
}
```

### After (Resource transformation)

```php
// Service - returns models
public function getTeamMembers(): Collection
{
    return User::with('roles')->orderBy('name')->get();
}

// Controller - uses Resource
'members' => TeamMemberResource::collection($this->teamService->getTeamMembers()),
```

## TypeScript Type Generation

Resources can automatically generate TypeScript types using the `HasTypescriptType` trait. Run `sail artisan types:generate` to regenerate types.

### Basic Usage

```php
use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;

class ProjectResource extends BaseResource
{
    use HasTypescriptType;

    public static function typescriptSchema(): array
    {
        return [
            'id' => 'string',
            'name' => 'string',
            'description' => 'string | null',
            'status' => 'ProjectStatus',  // Reference to enum
            'created_at' => 'string',
        ];
    }
}
```

Generated TypeScript (`resources/js/types/resources.d.ts`):
```typescript
export interface ProjectResource {
    id: string;
    name: string;
    description: string | null;
    status: ProjectStatus;
    created_at: string;
}
```

### Nested Types Pattern

When a Resource contains complex nested objects that aren't full Resources themselves, use the **Nested Types Pattern**.

#### Problem

The type generator only processes `typescriptSchema()`. Complex Resources with inline object structures need auxiliary types that the generator doesn't automatically create.

#### Solution

1. **Define nested types in the Resource** using `typescriptAdditionalTypes()` (for documentation purposes)
2. **Manually add the types to `common.d.ts`** (the generator doesn't process `typescriptAdditionalTypes()`)
3. **Reference the nested types in `typescriptSchema()`**

#### Example: UserFederationInfoResource

**Step 1: Define the Resource with nested type references**

```php
// app/Http/Resources/Tenant/UserFederationInfoResource.php
class UserFederationInfoResource extends BaseResource
{
    use HasTypescriptType;

    public static function typescriptSchema(): array
    {
        return [
            'is_federated' => 'boolean',
            'federation_id' => 'string | null',
            'is_master_user' => 'boolean',
            // Reference types defined in common.d.ts
            'federated_user' => 'UserFederationInfoFederatedUser | null',
            'link' => 'UserFederationInfoLink | null',
            'group' => 'UserFederationInfoGroup | null',
        ];
    }

    // Document nested types (not auto-processed, but useful for reference)
    public static function typescriptAdditionalTypes(): array
    {
        return [
            'UserFederationInfoFederatedUser' => [
                'id' => 'string',
                'email' => 'string',
                'synced_data' => 'Record<string, unknown>',
                'last_synced_at' => 'string | null',
                'created_at' => 'string',
            ],
            'UserFederationInfoLink' => [
                'id' => 'string',
                'status' => 'string',
                'sync_enabled' => 'boolean',
                'last_synced_at' => 'string | null',
                'linked_at' => 'string',
            ],
            'UserFederationInfoGroup' => [
                'id' => 'string',
                'name' => 'string',
                'sync_strategy' => 'FederationSyncStrategy',
            ],
        ];
    }
}
```

**Step 2: Add nested types to common.d.ts**

```typescript
// resources/js/types/common.d.ts

// =============================================================================
// User Federation Info Types (for UserFederationInfoResource)
// =============================================================================

/**
 * Federated user info in user federation detail view
 */
export interface UserFederationInfoFederatedUser {
    id: string;
    email: string;
    synced_data: Record<string, unknown>;
    last_synced_at: string | null;
    created_at: string;
}

/**
 * Federation link info in user federation detail view
 */
export interface UserFederationInfoLink {
    id: string;
    status: string;
    sync_enabled: boolean;
    last_synced_at: string | null;
    linked_at: string;
}

/**
 * Federation group info in user federation detail view
 */
export interface UserFederationInfoGroup {
    id: string;
    name: string;
    sync_strategy: FederationSyncStrategy;
}
```

**Step 3: Use in Frontend**

```typescript
// resources/js/pages/tenant/admin/team/federation-info.tsx
import {
    type TeamMemberResource,
    type UserFederationInfoResource,
} from '@/types';

interface Props {
    user: TeamMemberResource;
    federationInfo: UserFederationInfoResource;
}

function FederationInfoPage({ user, federationInfo }: Props) {
    // TypeScript knows the nested structure
    if (federationInfo.is_federated && federationInfo.link) {
        console.log(federationInfo.link.status);      // ✅ Type-safe
        console.log(federationInfo.group?.name);      // ✅ Type-safe
    }
}
```

### Naming Convention for Nested Types

Follow this pattern: `{ParentResource}{PropertyName}`

| Parent Resource | Property | Nested Type Name |
|-----------------|----------|------------------|
| `UserFederationInfoResource` | `federated_user` | `UserFederationInfoFederatedUser` |
| `UserFederationInfoResource` | `link` | `UserFederationInfoLink` |
| `UserFederationInfoResource` | `group` | `UserFederationInfoGroup` |
| `TenantDetailResource` | `users` | `TenantUser` |
| `ActivityResource` | `causer` | `ActivityCauser` |

### When to Use Nested Types vs. Separate Resources

| Scenario | Approach |
|----------|----------|
| Object used in multiple Resources | Create a separate Resource |
| Object specific to one Resource | Use nested type in `common.d.ts` |
| Object is a simplified view of a model | Consider `SummaryResource` |
| Object is computed/aggregated data | Use nested type in `common.d.ts` |

### Type Files Organization

```
resources/js/types/
├── index.d.ts       # Re-exports all types
├── resources.d.ts   # Auto-generated from Resources (DO NOT EDIT)
├── enums.d.ts       # Auto-generated from Enums (DO NOT EDIT)
├── plan.d.ts        # Auto-generated plan types (DO NOT EDIT)
├── common.d.ts      # Manually maintained nested/shared types
└── global.d.ts      # Global type declarations
```

**Important**: Only edit `common.d.ts` and `global.d.ts` manually. The other files are auto-generated by `sail artisan types:generate`.

## Reference

| Resource | Model | Purpose |
|----------|-------|---------|
| `TenantResource` | `Tenant` | List with domains, plan, user count |
| `TenantDetailResource` | `Tenant` | Show with users, addons, full details |
| `TenantEditResource` | `Tenant` | Edit form with current values |
| `TenantSummaryResource` | `Tenant` | Minimal for dropdowns |
| `PlanResource` | `Plan` | List with tenant count |
| `PlanDetailResource` | `Plan` | Full with features/limits |
| `PlanEditResource` | `Plan` | Edit form with translations |
| `PlanSummaryResource` | `Plan` | For plan selection dropdowns |
| `DomainResource` | `Domain` | Domain info |
| `UserResource` | `User` | Full user info |
| `UserSummaryResource` | `User` | Minimal user |
| `TeamMemberResource` | `User` | User with role/permissions |
| `ProjectResource` | `Project` | List view |
| `ProjectDetailResource` | `Project` | Show with media |
| `ProjectEditResource` | `Project` | Edit form |
| `ActivityResource` | `Activity` | Audit log entry |
| `MediaResource` | `Media` | Media file info |
| `UserInvitationResource` | `UserInvitation` | Pending team invitation |
| `ApiTokenResource` | `PersonalAccessToken` | API token info |
| `RoleResource` | `Role` | List with counts |
| `RoleDetailResource` | `Role` | With permissions/users |
| `RoleEditResource` | `Role` | Edit form |
| `PermissionResource` | `Permission` | Permission info |
| `ImpersonationTenantResource` | `Tenant` | Tenant for impersonation page |
| `ImpersonationUserResource` | `User` | User for impersonation selection |
| `FederationGroupForTenantResource` | `FederationGroup` | Group from tenant perspective |
| `TenantFederationMembershipResource` | `FederationGroupTenant` | Membership pivot data |
| `UserFederationInfoResource` | Array | User federation info (nested types) |
