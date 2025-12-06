# Services Architecture

This document describes the Service layer architecture, conventions, and patterns used in this project.

## Overview

Services encapsulate business logic, keeping controllers thin and focused on HTTP concerns. All services are organized into namespaces based on their context.

## Directory Structure

```
app/Services/
├── Central/                    # Central Admin Services (11 files)
│   ├── AddonService.php        # Add-on CRUD and management
│   ├── CheckoutService.php     # Subscription checkout flow
│   ├── ImpersonationService.php # Admin tenant impersonation
│   ├── MeteredBillingService.php # Usage-based billing
│   ├── PlanFeatureResolver.php # Pennant feature resolution
│   ├── PlanPermissionResolver.php # Plan → Permission mapping
│   ├── PlanService.php         # Plan CRUD and Stripe sync
│   ├── PlanSyncService.php     # Batch plan synchronization
│   ├── RoleService.php         # Central admin role management
│   └── StripeSyncService.php   # Stripe webhook handling
│
└── Tenant/                     # Tenant Context Services (5 files)
    ├── AuditLogService.php     # Activity log queries
    ├── BillingService.php      # Tenant billing portal
    ├── RoleService.php         # Tenant role management
    ├── TeamService.php         # Team members & invitations
    └── TenantSettingsService.php # White-label, domains
```

## Service Conventions

### 1. Namespace Organization

- **Central/**: Services that operate on the central database (tenants, plans, addons)
- **Tenant/**: Services that operate within tenant context (team, projects, settings)

### 2. Naming Pattern

Services are named after the domain they manage:
- `TeamService` - Team management
- `PlanService` - Plan management
- `RoleService` - Role management (exists in both Central/ and Tenant/)

### 3. Dependency Injection

Services are injected into controllers via constructor injection:

```php
class TeamController extends Controller
{
    public function __construct(
        protected TeamService $teamService
    ) {}

    public function index()
    {
        $members = $this->teamService->getTeamMembers();
        // ...
    }
}
```

### 4. Exception Handling

Services throw domain-specific exceptions for business rule violations:

```php
// In Service
if ($tenant->hasReachedUserLimit()) {
    throw new TeamException(__('flash.team.user_limit_reached'));
}

// In Controller - handled by Inertia error handling
public function invite(InviteMemberRequest $request)
{
    try {
        $this->teamService->inviteMember(...);
        return back()->with('success', __('flash.team.invited'));
    } catch (TeamException $e) {
        return back()->with('error', $e->getMessage());
    }
}
```

### 5. Return Types

Services return typed data:
- Single entities: Model instances or formatted arrays
- Collections: `Collection<int, array>` with mapped data
- Statistics: Typed arrays with explicit keys

```php
/**
 * @return Collection<int, array{id: string, name: string, email: string, role: string|null}>
 */
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

---

## Central Services

### AddonService

Manages add-on catalog and subscriptions.

```php
use App\Services\Central\AddonService;

$addonService->getAllAddons();           // List all add-ons
$addonService->createAddon($data);       // Create add-on
$addonService->updateAddon($addon, $data); // Update add-on
$addonService->deleteAddon($addon);      // Delete add-on
```

### CheckoutService

Handles subscription checkout flow with Stripe.

```php
use App\Services\Central\CheckoutService;

$checkoutService->createCheckoutSession($tenant, $plan);
$checkoutService->handleSuccessfulCheckout($session);
$checkoutService->cancelSubscription($tenant);
```

### ImpersonationService

Enables central admins to access tenant contexts.

```php
use App\Services\Central\ImpersonationService;

$impersonationService->createImpersonationToken($tenant, $admin);
$impersonationService->validateToken($token);
$impersonationService->endImpersonation();
```

### PlanService

Complete plan lifecycle management including Stripe sync.

```php
use App\Services\Central\PlanService;

$planService->getAllPlans();             // List with tenant counts
$planService->createPlan($data);         // Create + generate permission_map
$planService->updatePlan($plan, $data);  // Update + regenerate permissions
$planService->deletePlan($plan);         // Delete (fails if has tenants)
$planService->syncToStripe($plan);       // Sync single plan to Stripe
$planService->syncAllToStripe();         // Batch sync all plans
```

### PlanFeatureResolver

Resolves Pennant features based on tenant plan.

```php
use App\Services\Central\PlanFeatureResolver;

// Used by Pennant for Feature::for($tenant)->active('customRoles')
$resolver->resolve($feature, $tenant);
```

### PlanPermissionResolver

Maps plan features to Spatie permissions.

```php
use App\Services\Central\PlanPermissionResolver;

$resolver->getEnabledPermissions($plan);
$resolver->isPlanPermissionEnabled($tenant, 'tenant.roles.custom');
```

### RoleService (Central)

Manages central admin roles.

```php
use App\Services\Central\RoleService;

$roleService->getAllRoles();             // List with user counts
$roleService->createRole($data);         // Create role
$roleService->updateRole($role, $data);  // Update role
$roleService->deleteRole($role);         // Delete (fails if has users)
```

### StripeSyncService

Handles Stripe webhook events.

```php
use App\Services\Central\StripeSyncService;

$stripeSyncService->handleSubscriptionUpdated($event);
$stripeSyncService->handlePaymentSucceeded($event);
$stripeSyncService->handleSubscriptionCanceled($event);
```

### MeteredBillingService

Tracks and reports usage-based billing.

```php
use App\Services\Central\MeteredBillingService;

$meteredBillingService->reportUsage($tenant, 'api_calls', 100);
$meteredBillingService->getCurrentUsage($tenant);
```

---

## Tenant Services

### TeamService

Complete team management for tenant context.

```php
use App\Services\Tenant\TeamService;

$teamService->getTeamMembers();          // List members with roles
$teamService->getPendingInvitations($tenant); // Pending invites
$teamService->getTeamStats($tenant);     // {max_users, current_users}

$teamService->inviteMember($tenant, $email, $role, $invitedBy);
$teamService->acceptInvitation($user, $token, $tenantId);
$teamService->updateMemberRole($target, $newRole, $currentUser);
$teamService->removeMember($member, $currentUser);

$teamService->resendInvitation($invitation, $tenant, $invitedBy);
$teamService->cancelInvitation($invitation);
```

### RoleService (Tenant)

Manages custom roles within tenant (when plan feature enabled).

```php
use App\Services\Tenant\RoleService;

$roleService->getAllRoles();             // List tenant roles
$roleService->getAvailablePermissions(); // Permissions from TenantPermission enum
$roleService->createRole($data);         // Create custom role
$roleService->updateRole($role, $data);  // Update role permissions
$roleService->deleteRole($role);         // Delete (fails if has users)
```

### BillingService

Tenant-facing billing operations.

```php
use App\Services\Tenant\BillingService;

$billingService->getCurrentPlan($tenant);
$billingService->getAvailablePlans();
$billingService->createBillingPortalSession($tenant);
$billingService->getInvoiceHistory($tenant);
```

### AuditLogService

Queries activity logs for tenant.

```php
use App\Services\Tenant\AuditLogService;

$auditLogService->getRecentActivity($limit);
$auditLogService->getActivityByUser($user);
$auditLogService->getActivityBySubject($model);
```

### TenantSettingsService

Manages tenant configuration and white-label settings.

```php
use App\Services\Tenant\TenantSettingsService;

$settingsService->getSettings($tenant);
$settingsService->updateBranding($tenant, $data);
$settingsService->addDomain($tenant, $domain);
$settingsService->removeDomain($tenant, $domain);
$settingsService->getTranslationOverrides($tenant);
$settingsService->updateTranslations($tenant, $overrides);
```

---

## Patterns & Best Practices

### 1. Database Transactions

Use transactions for multi-step operations:

```php
public function updateMemberRole(User $target, string $newRole, User $currentUser): void
{
    // Validation logic...

    DB::transaction(function () use ($target, $newRole) {
        $target->syncRoles([$newRole]);
    });
}
```

### 2. Cross-Database Operations

Central services may need to operate on tenant databases:

```php
// In Central service accessing tenant data
tenancy()->central(function () {
    // Operations on central database
});

tenancy()->runForMultiple($tenants, function ($tenant) {
    // Operations in each tenant context
});
```

### 3. Service Composition

Services can depend on other services:

```php
class CheckoutService
{
    public function __construct(
        protected PlanService $planService,
        protected StripeSyncService $stripeService
    ) {}
}
```

### 4. Stripe Integration

Central services handle Stripe operations:

```php
protected StripeClient $stripe;

public function __construct()
{
    $this->stripe = new StripeClient(config('cashier.secret'));
}

public function syncToStripe(Plan $plan): void
{
    $product = $this->stripe->products->create([
        'name' => $plan->trans('name'),
        'metadata' => ['plan_slug' => $plan->slug],
    ]);
}
```

### 5. Logging

Log significant operations and errors:

```php
use Illuminate\Support\Facades\Log;

public function deletePlan(Plan $plan): void
{
    Log::info('Deleting plan', ['plan_id' => $plan->id]);

    try {
        $plan->delete();
    } catch (\Exception $e) {
        Log::error('Failed to delete plan', [
            'plan_id' => $plan->id,
            'error' => $e->getMessage(),
        ]);
        throw $e;
    }
}
```

---

## Creating New Services

### 1. Determine Context

- Central operations (tenants, plans, addons) → `App\Services\Central\`
- Tenant operations (team, projects) → `App\Services\Tenant\`

### 2. Create Service Class

```php
<?php

namespace App\Services\Tenant;

use App\Exceptions\ProjectException;
use App\Models\Tenant\Project;
use Illuminate\Support\Collection;

/**
 * ProjectService
 *
 * Handles all business logic for project management in tenant context.
 */
class ProjectService
{
    /**
     * Get all projects for current user.
     *
     * @return Collection<int, array{id: string, name: string, status: string}>
     */
    public function getUserProjects(): Collection
    {
        return Project::query()
            ->where('user_id', auth()->id())
            ->get()
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status,
            ]);
    }

    /**
     * Create a new project.
     *
     * @param array<string, mixed> $data
     * @throws ProjectException
     */
    public function createProject(array $data): Project
    {
        // Business logic validation
        if (tenant()->hasReachedLimit('projects')) {
            throw new ProjectException(__('flash.project.limit_reached'));
        }

        return Project::create($data);
    }
}
```

### 3. Register Exception (if needed)

```php
// app/Exceptions/ProjectException.php
namespace App\Exceptions;

class ProjectException extends \Exception {}
```

### 4. Inject into Controller

```php
class ProjectController extends Controller
{
    public function __construct(
        protected ProjectService $projectService
    ) {}
}
```

---

## Testing Services

Services are tested independently from controllers:

```php
use App\Services\Tenant\TeamService;
use App\Models\Tenant\User;

test('can get team members', function () {
    // Arrange
    $this->initializeTenancy();
    User::factory()->count(3)->create();

    // Act
    $service = new TeamService();
    $members = $service->getTeamMembers();

    // Assert
    expect($members)->toHaveCount(3);
    expect($members->first())->toHaveKeys(['id', 'name', 'email', 'role']);
});

test('throws exception when user limit reached', function () {
    // Arrange
    $tenant = tenant();
    $tenant->limits = ['users' => 1];
    $tenant->save();
    User::factory()->create();

    // Act & Assert
    $service = new TeamService();
    expect(fn () => $service->inviteMember($tenant, 'new@example.com', 'member', auth()->user()))
        ->toThrow(TeamException::class, 'User limit reached');
});
```

---

## Related Documentation

- [CONTROLLERS.md](CONTROLLERS.md) - Controller patterns and Form Requests
- [MODELS.md](MODELS.md) - Model architecture and namespaces
- [PERMISSIONS.md](PERMISSIONS.md) - Permission system and enums
- [SYSTEM-ARCHITECTURE.md](SYSTEM-ARCHITECTURE.md) - Overall system architecture
