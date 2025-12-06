# Controllers & Form Requests

This document describes the Controller patterns, Form Requests, and middleware configuration used in this project.

## Overview

Controllers follow a **thin controller** pattern, delegating business logic to Services. Form Requests handle validation and authorization.

## Directory Structure

```
app/Http/
├── Controllers/
│   ├── Controller.php              # Base controller
│   ├── Billing/
│   │   └── AddonWebhookController.php
│   ├── Central/
│   │   ├── Admin/                  # Central Admin Panel
│   │   │   ├── AddonCatalogController.php
│   │   │   ├── AddonManagementController.php
│   │   │   ├── BundleCatalogController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── ImpersonationController.php
│   │   │   ├── PlanCatalogController.php
│   │   │   ├── RoleManagementController.php
│   │   │   ├── TenantManagementController.php
│   │   │   └── UserManagementController.php
│   │   ├── Auth/
│   │   │   ├── AdminLoginController.php
│   │   │   └── AdminLogoutController.php
│   │   └── Panel/
│   │       ├── DashboardController.php
│   │       └── TenantAccessController.php
│   ├── Tenant/
│   │   ├── Admin/                  # Tenant Admin Panel
│   │   │   ├── AddonController.php
│   │   │   ├── AuditLogController.php
│   │   │   ├── BillingController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── ProjectController.php
│   │   │   ├── TeamActivityController.php
│   │   │   ├── TeamController.php
│   │   │   ├── TenantRoleController.php
│   │   │   └── TenantSettingsController.php
│   │   ├── Api/
│   │   │   └── ProjectController.php
│   │   └── ApiTokenController.php
│   └── Shared/
│       └── Settings/               # Works in both contexts
│           ├── PasswordController.php
│           ├── ProfileController.php
│           └── TwoFactorAuthenticationController.php
│
└── Requests/
    ├── Central/
    │   ├── StorePlanRequest.php
    │   ├── UpdatePlanRequest.php
    │   ├── StoreRoleRequest.php
    │   └── UpdateRoleRequest.php
    └── Tenant/
        ├── AcceptInvitationRequest.php
        ├── AddDomainRequest.php
        ├── CheckoutRequest.php
        ├── InviteMemberRequest.php
        ├── StoreProjectRequest.php
        ├── StoreRoleRequest.php
        ├── UpdateBrandingRequest.php
        ├── UpdateMemberRoleRequest.php
        ├── UpdateProjectRequest.php
        ├── UpdateRoleRequest.php
        └── UploadFileRequest.php
    └── Shared/
        └── Settings/
            ├── ProfileUpdateRequest.php
            └── TwoFactorAuthenticationRequest.php
```

## Controller Patterns

### Thin Controller Pattern

Controllers handle HTTP concerns only. Business logic lives in Services.

```php
<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\InviteMemberRequest;
use App\Services\Tenant\TeamService;
use Illuminate\Http\RedirectResponse;

class TeamController extends Controller
{
    public function __construct(
        protected TeamService $teamService
    ) {}

    public function invite(InviteMemberRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $this->teamService->inviteMember(
                tenant: tenant(),
                email: $validated['email'],
                role: $validated['role'],
                invitedBy: $request->user()
            );

            return back()->with('success', __('flash.team.invite_sent'));
        } catch (TeamException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
```

### Controller Middleware (Laravel 11+)

Use `HasMiddleware` interface for per-action permissions:

```php
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class TeamController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:'.TenantPermission::TEAM_VIEW->value, only: ['index']),
            new Middleware('permission:'.TenantPermission::TEAM_INVITE->value, only: ['invite']),
            new Middleware('permission:'.TenantPermission::TEAM_MANAGE_ROLES->value, only: ['updateRole']),
            new Middleware('permission:'.TenantPermission::TEAM_REMOVE->value, only: ['remove']),
        ];
    }
}
```

### Inertia Responses

Controllers return Inertia responses with typed props:

```php
use Inertia\Inertia;
use Inertia\Response;

public function index(): Response
{
    $tenant = tenant();

    return Inertia::render('tenant/admin/team/index', [
        'members' => $this->teamService->getTeamMembers(),
        'pendingInvitations' => $this->teamService->getPendingInvitations($tenant),
        'teamStats' => $this->teamService->getTeamStats($tenant),
    ]);
}
```

### Exception Handling

Handle service exceptions gracefully:

```php
public function updateRole(UpdateMemberRoleRequest $request, User $user): RedirectResponse
{
    $validated = $request->validated();

    try {
        $this->teamService->updateMemberRole(
            target: $user,
            newRole: $validated['role'],
            currentUser: $request->user()
        );

        return back()->with('success', __('flash.team.role_updated'));
    } catch (TeamAuthorizationException $e) {
        abort(403, $e->getMessage());  // Authorization failure
    } catch (TeamException $e) {
        return back()->with('error', $e->getMessage());  // Business rule violation
    }
}
```

---

## Controller Namespaces

### Central Controllers

**Admin** (`App\Http\Controllers\Central\Admin\`):
Platform administration for super admins.

| Controller | Purpose |
|-----------|---------|
| `DashboardController` | Admin dashboard |
| `PlanCatalogController` | Manage subscription plans |
| `AddonCatalogController` | Manage add-on catalog |
| `TenantManagementController` | Manage tenants |
| `UserManagementController` | Manage central admins |
| `RoleManagementController` | Manage central roles |
| `ImpersonationController` | Admin impersonation |

**Auth** (`App\Http\Controllers\Central\Auth\`):
Central admin authentication (separate from tenant auth).

| Controller | Purpose |
|-----------|---------|
| `AdminLoginController` | Admin login form & processing |
| `AdminLogoutController` | Admin logout |

### Tenant Controllers

**Admin** (`App\Http\Controllers\Tenant\Admin\`):
Tenant administration for owners and admins.

| Controller | Purpose |
|-----------|---------|
| `DashboardController` | Tenant dashboard |
| `TeamController` | Team management |
| `ProjectController` | Project CRUD |
| `BillingController` | Subscription & billing |
| `TenantSettingsController` | Tenant configuration |
| `TenantRoleController` | Custom roles (plan feature) |
| `AuditLogController` | Activity log viewer |
| `AddonController` | Add-on management |

**Api** (`App\Http\Controllers\Tenant\Api\`):
JSON API endpoints for tenant resources.

| Controller | Purpose |
|-----------|---------|
| `ProjectController` | Project API endpoints |

### Universal Controllers

**Settings** (`App\Http\Controllers\Universal\Settings\`):
Work in both central and tenant contexts.

| Controller | Purpose |
|-----------|---------|
| `ProfileController` | User profile management |
| `PasswordController` | Password changes |
| `TwoFactorAuthenticationController` | 2FA setup |

---

## Form Requests

### Form Request Pattern

Form Requests handle validation and authorization:

```php
<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by controller middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(['admin', 'member'])],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'email.required' => __('validation.team.email_required'),
            'role.in' => __('validation.team.invalid_role'),
        ];
    }
}
```

### Form Request with Authorization

```php
class UpdateMemberRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // User must have team management permission
        return $this->user()->can(TenantPermission::TEAM_MANAGE_ROLES->value);
    }

    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in(['owner', 'admin', 'member'])],
        ];
    }
}
```

### Form Request with Custom Validation

```php
class StoreProjectRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (tenant()->hasReachedLimit('projects')) {
                $validator->errors()->add('limit', __('flash.project.limit_reached'));
            }
        });
    }
}
```

### Central Form Requests

| Request | Purpose |
|---------|---------|
| `StorePlanRequest` | Create new plan |
| `UpdatePlanRequest` | Update existing plan |
| `StoreRoleRequest` | Create central role |
| `UpdateRoleRequest` | Update central role |

### Tenant Form Requests

| Request | Purpose |
|---------|---------|
| `InviteMemberRequest` | Invite team member |
| `AcceptInvitationRequest` | Accept team invitation |
| `UpdateMemberRoleRequest` | Change member role |
| `StoreProjectRequest` | Create project |
| `UpdateProjectRequest` | Update project |
| `StoreRoleRequest` | Create custom role |
| `UpdateRoleRequest` | Update custom role |
| `UpdateBrandingRequest` | Update tenant branding |
| `AddDomainRequest` | Add custom domain |
| `CheckoutRequest` | Plan checkout |
| `UploadFileRequest` | File upload |

### Universal Form Requests

| Request | Purpose |
|---------|---------|
| `ProfileUpdateRequest` | Update user profile |
| `TwoFactorAuthenticationRequest` | 2FA configuration |

---

## Route Organization

### Tenant Routes (`routes/tenant.php`)

```php
Route::prefix('admin')
    ->name('tenant.admin.')
    ->middleware(['auth', 'verified'])
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Team Management
        Route::prefix('team')->name('team.')->group(function () {
            Route::get('/', [TeamController::class, 'index'])->name('index');
            Route::post('/invite', [TeamController::class, 'invite'])->name('invite');
            Route::put('/{user}/role', [TeamController::class, 'updateRole'])->name('update-role');
            Route::delete('/{user}', [TeamController::class, 'remove'])->name('remove');
        });
    });
```

### Central Routes (`routes/web.php`)

```php
Route::prefix('admin')
    ->name('central.admin.')
    ->middleware(['auth:admin'])
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Plans Management
        Route::resource('plans', PlanCatalogController::class);
    });
```

---

## Creating New Controllers

### 1. Determine Context

- Central admin operations → `App\Http\Controllers\Central\Admin\`
- Tenant admin operations → `App\Http\Controllers\Tenant\Admin\`
- Works in both contexts → `App\Http\Controllers\Universal\`

### 2. Create Controller

```php
<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Enums\TenantPermission;
use App\Exceptions\ResourceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreResourceRequest;
use App\Services\Tenant\ResourceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class ResourceController extends Controller implements HasMiddleware
{
    public function __construct(
        protected ResourceService $resourceService
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:'.TenantPermission::RESOURCE_VIEW->value, only: ['index', 'show']),
            new Middleware('permission:'.TenantPermission::RESOURCE_CREATE->value, only: ['create', 'store']),
            new Middleware('permission:'.TenantPermission::RESOURCE_EDIT->value, only: ['edit', 'update']),
            new Middleware('permission:'.TenantPermission::RESOURCE_DELETE->value, only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        return Inertia::render('tenant/admin/resources/index', [
            'resources' => $this->resourceService->getAll(),
        ]);
    }

    public function store(StoreResourceRequest $request): RedirectResponse
    {
        try {
            $this->resourceService->create($request->validated());
            return redirect()
                ->route('tenant.admin.resources.index')
                ->with('success', __('flash.resource.created'));
        } catch (ResourceException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
```

### 3. Create Form Request

```php
<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class StoreResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Handled by controller middleware
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:type_a,type_b'],
        ];
    }
}
```

### 4. Add Routes

```php
// routes/tenant.php
Route::resource('resources', ResourceController::class)
    ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
```

---

## Best Practices

### Do's

1. **Inject services via constructor**
2. **Use Form Requests for validation**
3. **Return typed responses** (`Response`, `RedirectResponse`)
4. **Use permission middleware** (not inline checks)
5. **Delegate to services** for business logic
6. **Use flash messages** for user feedback
7. **Handle exceptions** gracefully

### Don'ts

1. **Don't put business logic in controllers**
2. **Don't skip validation** (always use Form Requests)
3. **Don't hardcode permissions** (use enums)
4. **Don't return raw arrays** from Inertia (use typed props)
5. **Don't catch Exception** broadly (catch specific types)

---

## Testing Controllers

```php
use App\Models\Tenant\User;

test('team index requires permission', function () {
    $this->initializeTenancy();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('tenant.admin.team.index'))
        ->assertForbidden();
});

test('owner can view team', function () {
    $this->initializeTenancy();
    $user = User::factory()->create();
    $user->assignRole('owner');

    $this->actingAs($user)
        ->get(route('tenant.admin.team.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant/admin/team/index')
            ->has('members')
            ->has('teamStats')
        );
});

test('invite requires valid email', function () {
    $this->initializeTenancy();
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user)
        ->post(route('tenant.admin.team.invite'), [
            'email' => 'not-an-email',
            'role' => 'member',
        ])
        ->assertSessionHasErrors('email');
});
```

---

## Related Documentation

- [SERVICES.md](SERVICES.md) - Service layer architecture
- [MODELS.md](MODELS.md) - Model architecture
- [PERMISSIONS.md](PERMISSIONS.md) - Permission system and enums
