# USER INVITATIONS MIGRATION PLAN

## Executive Summary

This plan details the migration of `tenant_invitations` table from the Central database to the Tenant database as `user_invitations`. The key challenge is handling invitation acceptance without tenant context (user clicks link from email).

## Current Architecture Analysis

### Current State
- **Table**: `tenant_invitations` in central database
- **Model**: `App\Models\Central\TenantInvitation`
- **Migration**: `database/migrations/2025_11_20_122412_create_tenant_invitations_table.php`
- **Has**: `tenant_id` FK, `email`, `invited_by_user_id` (UUID reference), `invitation_token`, `role`, `expires_at`
- **Service**: `TeamService`
- **Controller**: `TeamController`

### Current Invitation Flow
1. Admin invites user via `POST /admin/team/invite` (tenant context)
2. `TeamService::inviteMember()` creates `TenantInvitation` in central DB
3. Email sent with URL: `{tenant-domain}/accept-invitation?token={token}`
4. User clicks link, arrives at tenant domain (tenancy initialized by domain)
5. `acceptInvitation()` finds invitation by token + tenant_id in central DB
6. User is assigned role in tenant database

### Why This Works Currently
The URL includes the tenant domain, so tenancy is initialized by `InitializeTenancyByDomain` middleware BEFORE the invitation lookup. The invitation query then uses `tenant('id')` to scope results.

---

## Target Architecture

### Target State
- **Table**: `user_invitations` in tenant database
- **Model**: `App\Models\Tenant\UserInvitation`
- **Migration**: `database/migrations/tenant/` directory
- **NO `tenant_id` column** (isolated by database)
- **Proper FK** on `invited_by_user_id` to `users` table

### Benefits
1. **LGPD Compliance**: Email addresses isolated per tenant database
2. **Data Locality**: All team data in same database
3. **Referential Integrity**: Proper FK constraint on `invited_by_user_id`
4. **Simpler Queries**: No cross-database concerns
5. **Tenant Deletion**: Invitations deleted automatically with tenant database

---

## Key Challenge: Token Resolution Without Tenant Context

### Problem
When user clicks invitation link from email, we need to know which tenant database to query for the invitation. Currently this works because the URL contains the tenant domain.

### Solution: Domain-Based Resolution (Recommended)
The current flow already includes the tenant domain in the invitation URL:
```php
// TeamInvitation.php (Mail)
$domain = $this->tenant->primaryDomain()->domain;
$acceptUrl = "{$protocol}://{$domain}/accept-invitation?token={$this->token}";
```

**Why this works**:
- Tenant domain is resolved by `InitializeTenancyByDomain` middleware
- By the time the request reaches the controller, tenancy is already initialized
- We can query `user_invitations` directly in the tenant database

**No changes needed** to the invitation URL structure.

---

## Implementation Steps

### Phase 1: Create New Tenant Migration

**File**: `database/migrations/tenant/2025_12_06_000001_create_user_invitations_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant Migration: User Invitations Table
 *
 * MULTI-DATABASE TENANCY (Option C):
 * - Invitations isolated per tenant database
 * - Proper FK on invited_by_user_id
 * - Better LGPD compliance (emails isolated)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email');
            $table->foreignUuid('invited_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('role');
            $table->string('invitation_token', 64)->unique();
            $table->timestamp('invited_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            // Indexes
            $table->index(['invitation_token', 'expires_at']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_invitations');
    }
};
```

### Phase 2: Create New Model

**File**: `app/Models/Tenant/UserInvitation.php`

```php
<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UserInvitation
 *
 * MULTI-DATABASE TENANCY (Option C):
 * - Lives in tenant database (isolated per tenant)
 * - Proper FK to users table
 * - Better LGPD compliance
 */
class UserInvitation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'email',
        'invited_by_user_id',
        'role',
        'invitation_token',
        'invited_at',
        'accepted_at',
        'expires_at',
    ];

    protected $casts = [
        'invited_at' => 'datetime',
        'accepted_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at < now();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isPending(): bool
    {
        return !$this->isAccepted() && !$this->isExpired();
    }

    public function scopePending($query)
    {
        return $query->whereNull('accepted_at')
            ->where('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->whereNull('accepted_at')
            ->where('expires_at', '<=', now());
    }

    public function scopeAccepted($query)
    {
        return $query->whereNotNull('accepted_at');
    }

    public static function findByToken(string $token): ?self
    {
        return static::where('invitation_token', $token)
            ->pending()
            ->first();
    }

    public function scopeForEmail($query, string $email)
    {
        return $query->where('email', strtolower($email));
    }
}
```

### Phase 3: Update TeamService

**File**: `app/Services/Tenant/TeamService.php`

**Changes**:
1. Replace `use App\Models\Central\TenantInvitation;` with `use App\Models\Tenant\UserInvitation;`
2. Remove `tenant_id` from all queries (implicit by database)
3. Update method signatures and implementations

```php
// Key changes:

// inviteMember() - Remove tenant_id
$invitation = UserInvitation::create([
    // 'tenant_id' => $tenant->id, // REMOVED
    'email' => $email,
    'invited_by_user_id' => $invitedBy->id,
    'role' => $role,
    'invitation_token' => $invitationToken,
    'invited_at' => now(),
    'expires_at' => now()->addDays(7),
]);

// getPendingInvitations() - Simplify query
public function getPendingInvitations(): Collection
{
    return UserInvitation::query()
        // ->where('tenant_id', $tenant->id) // REMOVED
        ->whereNull('accepted_at')
        ->where('expires_at', '>', now())
        ->get();
}

// acceptInvitation() - Remove tenantId parameter
public function acceptInvitation(User $user, string $token): void
{
    $invitation = UserInvitation::query()
        // ->where('tenant_id', $tenantId) // REMOVED
        ->where('invitation_token', $token)
        ->whereNull('accepted_at')
        ->where('expires_at', '>', now())
        ->first();
    // ... rest of method
}
```

### Phase 4: Update TeamController

**File**: `app/Http/Controllers/Tenant/Admin/TeamController.php`

**Changes**:
1. Update `acceptInvitation()` to not pass tenant_id
2. Update resource references

### Phase 5: Rename Resource

**Rename**: `TenantInvitationResource.php` → `UserInvitationResource.php`
**Path**: `app/Http/Resources/Tenant/UserInvitationResource.php`

### Phase 6: Create Factory

**File**: `database/factories/UserInvitationFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\Tenant\User;
use App\Models\Tenant\UserInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserInvitationFactory extends Factory
{
    protected $model = UserInvitation::class;

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'invited_by_user_id' => null,
            'role' => fake()->randomElement(['admin', 'member']),
            'invitation_token' => Str::random(64),
            'invited_at' => now(),
            'expires_at' => now()->addDays(7),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn() => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn() => [
            'accepted_at' => now(),
        ]);
    }
}
```

---

## Files to Update Summary

| File | Action | Notes |
|------|--------|-------|
| `database/migrations/tenant/XXXX_create_user_invitations_table.php` | Create | New tenant migration |
| `app/Models/Tenant/UserInvitation.php` | Create | New model |
| `database/factories/UserInvitationFactory.php` | Create | For testing |
| `app/Services/Tenant/TeamService.php` | Update | Remove tenant_id, use UserInvitation |
| `app/Http/Controllers/Tenant/Admin/TeamController.php` | Update | Remove tenantId parameter |
| `app/Http/Resources/Tenant/TenantInvitationResource.php` | Rename | To UserInvitationResource |
| `app/Models/Central/TenantInvitation.php` | Delete | After migration |
| `database/migrations/2025_11_20_122412_create_tenant_invitations_table.php` | Delete | After migration |

---

## Testing Strategy

### Update Existing Tests

Update `tests/Feature/TeamTest.php` to use new model and verify tenant isolation.

### Test Cases
1. Can create invitation in tenant database
2. Invitation is isolated per tenant
3. Accept invitation flow works
4. Expired invitations are rejected
5. Duplicate email invitations handled correctly

---

## Deployment Checklist

1. [ ] Create tenant migration file
2. [ ] Create UserInvitation model
3. [ ] Create UserInvitationFactory
4. [ ] Update TeamService
5. [ ] Update TeamController
6. [ ] Rename/Update Resource
7. [ ] Run `sail artisan tenants:migrate` on all tenants
8. [ ] Test invitation flow end-to-end
9. [ ] Delete old central migration
10. [ ] Delete old TenantInvitation model

---

## Risks and Mitigations

| Risk | Mitigation |
|------|------------|
| FK constraint failures | `invited_by_user_id` nullable with `nullOnDelete()` |
| Email delivery issues | No changes to email flow - same URLs |
| Concurrent acceptance | Token uniqueness + transaction in acceptInvitation |

---

## No Data Migration Needed

Since this is a development environment and there are no production pending invitations, we can:
1. Create new structure
2. Delete old structure
3. No data migration required

For production environments with existing data, add a data migration step before deletion.
