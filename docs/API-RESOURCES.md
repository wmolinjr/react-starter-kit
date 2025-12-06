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
| `RoleResource` | `Role` | List with counts |
| `RoleDetailResource` | `Role` | With permissions/users |
| `RoleEditResource` | `Role` | Edit form |
| `PermissionResource` | `Permission` | Permission info |
