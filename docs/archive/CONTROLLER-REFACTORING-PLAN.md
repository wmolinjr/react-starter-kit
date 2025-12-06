# Controller Refactoring Plan

## Executive Summary

After analyzing 28 controllers in this Laravel + Inertia.js multi-tenant application, several patterns of Single Responsibility Principle (SRP) violations were identified. The codebase already has a good foundation with existing services (`AddonService`, `StripeSyncService`, `CheckoutService`), but many controllers still contain business logic, complex queries, and data transformation that should be extracted.

---

## Priority Legend
- **HIGH**: Controllers with significant business logic, external API calls, or complex state management
- **MEDIUM**: Controllers with moderate business logic or data transformation
- **LOW**: Controllers that are mostly thin but have minor improvements possible

---

## 1. HIGH PRIORITY REFACTORING

### 1.1 PlanCatalogController (Central/Admin)
**File**: `app/Http/Controllers/Central/Admin/PlanCatalogController.php`

**Issues Identified**:
| Line | Issue | Type |
|------|-------|------|
| 36-39 | StripeClient instantiation in constructor | Business Logic |
| 88-133 | Complex store logic with permission map generation | Business Logic |
| 174-216 | Complex update logic with permission map regeneration | Business Logic |
| 231-269 | Stripe sync logic with product/price creation | External API |
| 271-286 | Batch sync logic | Business Logic |
| 288-317 | Protected syncPlanToStripe method | External API |

**Proposed Solution**:
Create `App\Services\Central\PlanService`:
```php
- createPlan(array $data): Plan
- updatePlan(Plan $plan, array $data): Plan
- deletePlan(Plan $plan): void
- syncToStripe(Plan $plan): void
- syncAllToStripe(): int
```

---

### 1.2 ImpersonationController (Central/Admin)
**File**: `app/Http/Controllers/Central/Admin/ImpersonationController.php`

**Issues Identified**:
| Line | Issue | Type |
|------|-------|------|
| 46-86 | Complex user query with tenancy context switch | Business Logic |
| 99-119 | Admin mode token creation logic | Business Logic |
| 131-153 | User impersonation with validation | Business Logic |
| 159-178 | Legacy impersonation method | Business Logic |
| 204-219 | Admin authentication helper | Business Logic |
| 224-237 | Tenant access validation | Business Logic |

**Proposed Solution**:
Create `App\Services\Central\ImpersonationService`:
```php
- getTenantUsers(Tenant $tenant): Collection
- createAdminModeToken(Tenant $tenant): ImpersonationToken
- createUserImpersonationToken(Tenant $tenant, string $userId): ImpersonationToken
- getAuthenticatedAdmin(): ?User
- canAccessTenant(User $admin, Tenant $tenant): bool
- buildImpersonationUrl(Tenant $tenant, ImpersonationToken $token): string
```

---

### 1.3 TeamController (Tenant/Admin)
**File**: `app/Http/Controllers/Tenant/Admin/TeamController.php`

**Issues Identified**:
| Line | Issue | Type |
|------|-------|------|
| 47-91 | Complex index query with data transformation | Query/Transform |
| 98-158 | Invitation creation with email sending | Business Logic |
| 165-215 | Invitation acceptance with transaction | Business Logic |
| 220-256 | Role update with complex validation | Business Logic |
| 263-281 | Member removal with owner protection | Business Logic |

**Proposed Solution**:
Create `App\Services\Tenant\TeamService`:
```php
- getTeamMembers(): Collection
- getPendingInvitations(): Collection
- inviteMember(string $email, string $role): TenantInvitation
- acceptInvitation(User $user, string $token): void
- updateMemberRole(User $target, string $newRole): void
- removeMember(User $member): void
```

---

### 1.4 TenantRoleController (Tenant/Admin)
**File**: `app/Http/Controllers/Tenant/Admin/TenantRoleController.php`

**Issues Identified**:
| Line | Issue | Type |
|------|-------|------|
| 46-52 | Direct DB query for user count | Query Logic |
| 60-74 | Permission formatting helper | Data Transform |
| 76-108 | Complex index with plan info calculation | Query/Transform |
| 140-204 | Store with permission validation and filtering | Business Logic |
| 282-336 | Update with plan-based permission validation | Business Logic |

**Proposed Solution**:
Create `App\Services\Tenant\RoleService`:
```php
- getRolesWithStats(): Collection
- getPlanInfo(): array
- getAllowedPermissions(): array
- formatPermissionsByCategory(Collection $permissions): array
- createRole(array $data): Role
- updateRole(Role $role, array $data): Role
- deleteRole(Role $role): void
```

---

### 1.5 AuditLogController (Tenant/Admin)
**File**: `app/Http/Controllers/Tenant/Admin/AuditLogController.php`

**Issues Identified**:
| Line | Issue | Type |
|------|-------|------|
| 35-143 | Massive index method with filtering, querying, and transformation | Query/Transform |
| 166-271 | Export method with CSV generation and streaming | Business Logic |
| 276-307 | formatActivity helper | Data Transform |
| 312-340 | getSubjectName helper | Data Transform |

**Proposed Solution**:
Create `App\Services\Tenant\AuditLogService`:
```php
- getActivities(array $filters): LengthAwarePaginator
- getFilterOptions(): array
- formatActivity(Activity $activity, bool $detailed = false): array
- exportToCsv(array $filters): StreamedResponse
```

---

### 1.6 BillingController (Tenant/Admin)
**File**: `app/Http/Controllers/Tenant/Admin/BillingController.php`

**Issues Identified**:
| Line | Issue | Type |
|------|-------|------|
| 30-64 | Complex index with plan transformation and invoice mapping | Query/Transform |
| 69-90 | Checkout creation with Stripe | External API |
| 95-115 | Success handler with limit updates | Business Logic |
| 134-159 | Invoice listing with transformation | Data Transform |

**Proposed Solution**:
Create `App\Services\Tenant\BillingService`:
```php
- getBillingOverview(): array
- getPlansForDisplay(): Collection
- createCheckout(string $planSlug): Checkout
- handleSuccessfulCheckout(): void
- getPortalUrl(): string
- getInvoices(): Collection
```

---

## 2. MEDIUM PRIORITY REFACTORING

### 2.1 TenantManagementController (Central/Admin)
**Proposed Solution**: Create `App\Http\Resources\Central\TenantResource`

### 2.2 RoleManagementController (Central/Admin)
**Proposed Solution**: Create `App\Services\Central\RoleService`

### 2.3 TenantSettingsController (Tenant/Admin)
**Proposed Solution**: Create `App\Services\Tenant\TenantSettingsService`

### 2.4 AddonCatalogController (Central/Admin)
**Proposed Solution**: Create `App\Http\Resources\Central\AddonResource`

### 2.5 BundleCatalogController (Central/Admin)
**Proposed Solution**: Create `App\Http\Resources\Central\BundleResource`

### 2.6 DashboardController (Central/Admin)
**Proposed Solution**: Create `App\Services\Central\DashboardService`

### 2.7 TeamActivityController (Tenant/Admin)
**Proposed Solution**: Reuse `AuditLogService` (code duplication with AuditLogController)

---

## 3. LOW PRIORITY (Already Thin)

These controllers follow good practices and need no changes:
- `DashboardController` (Tenant/Admin) - 17 lines
- `AdminLogoutController` (Central/Auth) - 35 lines
- `PasswordController` (Universal/Settings) - 39 lines
- `TwoFactorAuthenticationController` (Universal/Settings) - 38 lines
- `ProjectController` (Tenant/Admin) - Clean CRUD

---

## 4. FORM REQUESTS TO CREATE

| Request Class | Controller | Methods |
|--------------|------------|---------|
| `Central\StorePlanRequest` | PlanCatalogController | store |
| `Central\UpdatePlanRequest` | PlanCatalogController | update |
| `Central\StoreRoleRequest` | RoleManagementController | store |
| `Central\UpdateRoleRequest` | RoleManagementController | update |
| `Central\StoreBundleRequest` | BundleCatalogController | store |
| `Central\UpdateBundleRequest` | BundleCatalogController | update |
| `Central\StoreAddonRequest` | AddonCatalogController | store |
| `Central\UpdateAddonRequest` | AddonCatalogController | update |
| `Tenant\InviteMemberRequest` | TeamController | invite |
| `Tenant\UpdateMemberRoleRequest` | TeamController | updateRole |
| `Tenant\StoreRoleRequest` | TenantRoleController | store |
| `Tenant\UpdateRoleRequest` | TenantRoleController | update |
| `Tenant\AuditLogFilterRequest` | AuditLogController | index, export |
| `Tenant\UpdateBrandingRequest` | TenantSettingsController | updateBranding |
| `Tenant\AddDomainRequest` | TenantSettingsController | addDomain |
| `Tenant\StoreProjectRequest` | ProjectController | store |
| `Tenant\UpdateProjectRequest` | ProjectController | update |

---

## 5. RESOURCES TO CREATE

| Resource Class | Purpose |
|---------------|---------|
| `Central\TenantResource` | Tenant list/show transformation |
| `Central\TenantCollection` | Paginated tenant listing |
| `Central\AddonResource` | Addon transformation for catalog |
| `Central\BundleResource` | Bundle transformation for catalog |
| `Central\PlanResource` | Plan transformation |
| `Tenant\TeamMemberResource` | Team member transformation |
| `Tenant\ActivityResource` | Activity log entry transformation |
| `Tenant\RoleResource` | Role transformation |

---

## 6. IMPLEMENTATION ORDER

### Phase 1: Foundation (High Impact, Shared Services)
1. Create `App\Services\Tenant\AuditLogService` (used by 2 controllers)
2. Create `App\Services\Central\ImpersonationService`
3. Create `App\Services\Tenant\TeamService`

### Phase 2: Business Logic Extraction
4. Create `App\Services\Central\PlanService`
5. Create `App\Services\Tenant\RoleService`
6. Create `App\Services\Central\RoleService`
7. Create `App\Services\Tenant\BillingService`
8. Create `App\Services\Tenant\TenantSettingsService`

### Phase 3: Form Requests (Batch)
9. Create all Form Requests

### Phase 4: Resources (Batch)
10. Create all Resource classes

### Phase 5: Cleanup
11. Refactor controllers to use new services
12. Add tests for new services
13. Update documentation

---

## 7. SERVICES TO CREATE (Summary)

### Central Services
| Service | Purpose |
|---------|---------|
| `PlanService` | Plan CRUD + Stripe sync |
| `ImpersonationService` | Admin impersonation logic |
| `RoleService` | Central role management |
| `DashboardService` | Dashboard stats |

### Tenant Services
| Service | Purpose |
|---------|---------|
| `AuditLogService` | Activity log queries + export |
| `TeamService` | Team member + invitation management |
| `RoleService` | Tenant role management |
| `BillingService` | Subscription + invoice handling |
| `TenantSettingsService` | Branding + domain management |

---

## 8. DIRECTORY STRUCTURE (After Refactoring)

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Central/
│   │   │   ├── Admin/           # Thin controllers
│   │   │   └── Auth/
│   │   ├── Tenant/
│   │   │   ├── Admin/           # Thin controllers
│   │   │   └── Auth/
│   │   └── Universal/
│   │       └── Settings/
│   ├── Requests/
│   │   ├── Central/             # NEW
│   │   │   ├── StorePlanRequest.php
│   │   │   ├── UpdatePlanRequest.php
│   │   │   └── ...
│   │   ├── Tenant/              # NEW
│   │   │   ├── InviteMemberRequest.php
│   │   │   ├── StoreRoleRequest.php
│   │   │   └── ...
│   │   └── Universal/
│   │       └── Settings/
│   └── Resources/
│       ├── Central/             # NEW
│       │   ├── TenantResource.php
│       │   ├── AddonResource.php
│       │   └── ...
│       └── Tenant/              # NEW
│           ├── TeamMemberResource.php
│           ├── ActivityResource.php
│           └── ...
├── Services/
│   ├── Central/
│   │   ├── AddonService.php     # Existing
│   │   ├── CheckoutService.php  # Existing
│   │   ├── PlanService.php      # NEW
│   │   ├── ImpersonationService.php  # NEW
│   │   ├── RoleService.php      # NEW
│   │   └── DashboardService.php # NEW
│   ├── Tenant/                  # NEW namespace
│   │   ├── AuditLogService.php
│   │   ├── TeamService.php
│   │   ├── RoleService.php
│   │   ├── BillingService.php
│   │   └── TenantSettingsService.php
│   └── Universal/
│       └── .gitkeep
```

---

## 9. ESTIMATED EFFORT

| Phase | Items | Complexity | Estimate |
|-------|-------|------------|----------|
| Phase 1 | 3 services | High | 3-4 hours |
| Phase 2 | 5 services | Medium | 4-5 hours |
| Phase 3 | 17 requests | Low | 2-3 hours |
| Phase 4 | 8 resources | Low | 1-2 hours |
| Phase 5 | Refactoring | Medium | 3-4 hours |
| **Total** | | | **13-18 hours** |

---

## 10. SUCCESS CRITERIA

After refactoring, controllers should:
- Have no more than ~50 lines per method
- Only handle HTTP concerns (request → service → response)
- Use Form Requests for validation
- Use Resources for data transformation
- Inject services via constructor
- Not contain any direct database queries (except simple finds)
- Not contain business logic or complex conditionals
