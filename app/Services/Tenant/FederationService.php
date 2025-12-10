<?php

namespace App\Services\Tenant;

use App\Enums\FederatedUserLinkSyncStatus;
use App\Enums\FederatedUserStatus;
use App\Enums\FederationSyncStrategy;
use App\Exceptions\Central\FederationException;
use App\Models\Central\FederatedUser;
use App\Models\Central\FederatedUserLink;
use App\Models\Central\FederationGroup;
use App\Models\Central\FederationGroupTenant;
use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use App\Services\Central\FederationCacheService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * FederationService (Tenant)
 *
 * Handles federation operations from the tenant's perspective.
 * This service runs in tenant context and manages:
 * - User federation within the tenant
 * - Syncing user data to/from federation
 * - Creating federated users when they don't exist locally
 */
class FederationService
{
    public function __construct(
        protected FederationCacheService $cacheService
    ) {}

    // =========================================================================
    // Federation Status
    // =========================================================================

    /**
     * Get the federation group for the current tenant.
     */
    public function getCurrentGroup(): ?FederationGroup
    {
        $tenant = tenant();
        if (! $tenant) {
            return null;
        }

        return $this->cacheService->getTenantGroup($tenant->id);
    }

    /**
     * Check if the current tenant is federated.
     */
    public function isFederated(): bool
    {
        return $this->getCurrentGroup() !== null;
    }

    /**
     * Check if the current tenant is the master of its federation group.
     */
    public function isMaster(): bool
    {
        $group = $this->getCurrentGroup();
        $tenant = tenant();

        return $group && $tenant && $group->isMaster($tenant);
    }

    /**
     * Get the current tenant's membership details.
     */
    public function getMembership(): ?FederationGroupTenant
    {
        $tenant = tenant();
        if (! $tenant) {
            return null;
        }

        return FederationGroupTenant::where('tenant_id', $tenant->id)
            ->whereNull('left_at')
            ->first();
    }

    // =========================================================================
    // Federated Users
    // =========================================================================

    /**
     * Get all federated users in the current tenant.
     */
    public function getFederatedUsers(): Collection
    {
        return User::federated()->with('roles')->get();
    }

    /**
     * Get local users that are NOT federated.
     */
    public function getLocalOnlyUsers(): Collection
    {
        return User::notFederated()->with('roles')->get();
    }

    /**
     * Check if a user is federated.
     */
    public function isUserFederated(User $user): bool
    {
        return $user->isFederated();
    }

    /**
     * Get federation info for a user.
     */
    public function getUserFederationInfo(User $user): ?array
    {
        if (! $user->isFederated()) {
            return null;
        }

        $federatedUser = $user->getFederatedUser();
        if (! $federatedUser) {
            return null;
        }

        $group = $federatedUser->federationGroup;

        return [
            'federated_user_id' => $federatedUser->id,
            'global_email' => $federatedUser->global_email,
            'group_name' => $group->name,
            'is_master_user' => $user->isMasterUser(),
            'linked_tenants_count' => $federatedUser->links()->count(),
            'last_synced_at' => $federatedUser->last_synced_at,
            'sync_version' => $federatedUser->sync_version,
        ];
    }

    // =========================================================================
    // User Federation
    // =========================================================================

    /**
     * Federate multiple users at once.
     *
     * @param  Collection<int, User>  $users
     * @return array{success: int, failed: int, errors: array<string, string>}
     */
    public function federateUsers(Collection $users): array
    {
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];

        foreach ($users as $user) {
            try {
                $this->federateUser($user);
                $results['success']++;
            } catch (FederationException $e) {
                $results['failed']++;
                $results['errors'][$user->email] = $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Federate an existing local user.
     * Creates a FederatedUser record and links this user to it.
     *
     * @throws FederationException
     */
    public function federateUser(User $user): FederatedUser
    {
        $group = $this->getCurrentGroup();
        $tenant = tenant();

        if (! $group || ! $tenant) {
            throw FederationException::notInFederationGroup();
        }

        if ($user->isFederated()) {
            throw FederationException::userAlreadyFederated($user->email);
        }

        // Check if email already exists in federation
        $existingFederatedUser = FederatedUser::findByEmailInGroup($user->email, $group->id);

        if ($existingFederatedUser) {
            // Link to existing federated user
            return $this->linkUserToFederatedUser($user, $existingFederatedUser);
        }

        // Create new federated user
        // Use central connection for transaction since FederatedUser/Link are in central database
        $centralConnection = config('tenancy.database.central_connection');

        return DB::connection($centralConnection)->transaction(function () use ($user, $group, $tenant) {
            $syncedData = $user->toFederationSyncData();

            $federatedUser = FederatedUser::create([
                'federation_group_id' => $group->id,
                'global_email' => strtolower($user->email),
                'synced_data' => $syncedData,
                'master_tenant_id' => $tenant->id,
                'master_tenant_user_id' => $user->id,
                'last_synced_at' => now(),
                'last_sync_source' => $tenant->id,
                'sync_version' => 1,
                'status' => FederatedUserStatus::ACTIVE,
            ]);

            // Create link
            FederatedUserLink::create([
                'federated_user_id' => $federatedUser->id,
                'tenant_id' => $tenant->id,
                'tenant_user_id' => $user->id,
                'sync_status' => FederatedUserLinkSyncStatus::SYNCED,
                'last_synced_at' => now(),
                'metadata' => [
                    'created_via' => FederatedUserLink::CREATED_VIA_MANUAL_LINK,
                    'is_master' => true,
                ],
            ]);

            // Update local user
            $user->federated_user_id = $federatedUser->id;
            $user->save();

            // Invalidate cache
            $this->cacheService->invalidateUserByEmail($user->email, $group->id);

            return $federatedUser;
        });
    }

    /**
     * Link a local user to an existing FederatedUser.
     */
    protected function linkUserToFederatedUser(User $user, FederatedUser $federatedUser): FederatedUser
    {
        $tenant = tenant();

        // Check if already linked
        if ($federatedUser->hasLinkToTenant($tenant)) {
            throw FederationException::userAlreadyLinked($user->email);
        }

        DB::transaction(function () use ($user, $federatedUser, $tenant) {
            // Create link
            FederatedUserLink::create([
                'federated_user_id' => $federatedUser->id,
                'tenant_id' => $tenant->id,
                'tenant_user_id' => $user->id,
                'sync_status' => FederatedUserLinkSyncStatus::SYNCED,
                'last_synced_at' => now(),
                'metadata' => [
                    'created_via' => FederatedUserLink::CREATED_VIA_MANUAL_LINK,
                ],
            ]);

            // Update local user
            $user->federated_user_id = $federatedUser->id;
            $user->save();

            // Apply synced data from federation
            $user->applyFederationSyncData($federatedUser->synced_data);
            $user->save();

            // Invalidate caches
            $this->cacheService->invalidateUserLinks($federatedUser->id);
        });

        return $federatedUser;
    }

    /**
     * Unfederate a user (remove from federation but keep local user).
     *
     * @throws FederationException
     */
    public function unfederateUser(User $user): void
    {
        if (! $user->isFederated()) {
            throw FederationException::userNotFederated($user->email);
        }

        $federatedUser = $user->getFederatedUser();
        $tenant = tenant();

        DB::transaction(function () use ($user, $federatedUser, $tenant) {
            // Remove link
            FederatedUserLink::where('federated_user_id', $federatedUser->id)
                ->where('tenant_id', $tenant->id)
                ->delete();

            // Remove federation reference from local user
            $user->federated_user_id = null;
            $user->save();

            // Invalidate caches
            $this->cacheService->invalidateUserLinks($federatedUser->id);
        });
    }

    // =========================================================================
    // User Sync
    // =========================================================================

    /**
     * Sync a local user's data to the federation.
     * Only syncs if this tenant is master or strategy allows.
     */
    public function syncUserToFederation(User $user): void
    {
        if (! $user->isFederated()) {
            return;
        }

        $federatedUser = $user->getFederatedUser();
        $group = $federatedUser->federationGroup;
        $tenant = tenant();

        // Check if we can sync (master_wins strategy)
        if ($group->sync_strategy === FederationSyncStrategy::MASTER_WINS) {
            if (! $group->isMaster($tenant)) {
                // Non-master tenants can't initiate sync with master_wins
                return;
            }
        }

        // Check debounce
        if (! $this->cacheService->shouldSync($federatedUser->id, 'profile')) {
            return;
        }

        // Get updated data
        $newData = $user->toFederationSyncData();

        // Update federated user
        $federatedUser->updateSyncedData($newData, $tenant->id);

        // Clear debounce
        $this->cacheService->syncCompleted($federatedUser->id, 'profile');
    }

    /**
     * Sync password change to federation.
     */
    public function syncPasswordToFederation(User $user, string $hashedPassword): void
    {
        if (! $user->isFederated()) {
            return;
        }

        $federatedUser = $user->getFederatedUser();
        $group = $federatedUser->federationGroup;
        $tenant = tenant();

        // Check if we can sync
        if ($group->sync_strategy === FederationSyncStrategy::MASTER_WINS) {
            if (! $group->isMaster($tenant)) {
                return;
            }
        }

        // Update password in synced data
        $federatedUser->updateSyncedField('password_hash', $hashedPassword);
        $federatedUser->updateSyncedField('password_changed_at', now()->toIso8601String());
    }

    /**
     * Sync 2FA changes to federation.
     */
    public function syncTwoFactorToFederation(User $user): void
    {
        if (! $user->isFederated()) {
            return;
        }

        $federatedUser = $user->getFederatedUser();
        $group = $federatedUser->federationGroup;
        $tenant = tenant();

        // Check if we can sync
        if ($group->sync_strategy === FederationSyncStrategy::MASTER_WINS) {
            if (! $group->isMaster($tenant)) {
                return;
            }
        }

        // Update 2FA data
        $enabled = $user->two_factor_confirmed_at !== null;

        $federatedUser->updateSyncedData([
            'two_factor_enabled' => $enabled,
            'two_factor_secret' => $user->two_factor_secret,
            'two_factor_recovery_codes' => $user->two_factor_recovery_codes,
            'two_factor_confirmed_at' => $user->two_factor_confirmed_at?->toIso8601String(),
        ], $tenant->id);
    }

    /**
     * Apply federation data to a local user.
     * Called when syncing from federation to local.
     */
    public function applyFederationDataToUser(User $user): void
    {
        if (! $user->isFederated()) {
            return;
        }

        $federatedUser = $user->getFederatedUser();

        $user->applyFederationSyncData($federatedUser->synced_data);
        $user->save();

        // Update link status
        $link = $user->getFederationLink();
        if ($link) {
            $link->markAsSynced();
        }
    }

    // =========================================================================
    // Auto-Create on Login
    // =========================================================================

    /**
     * Find or create a local user from federation data.
     * Used during login when a federated user doesn't exist locally.
     */
    public function findOrCreateFromFederation(string $email): ?User
    {
        $group = $this->getCurrentGroup();
        if (! $group) {
            return null;
        }

        // Check if auto-create is enabled
        if (! $group->shouldAutoCreateOnLogin()) {
            return null;
        }

        // Find federated user
        $federatedUser = FederatedUser::findByEmailInGroup($email, $group->id);
        if (! $federatedUser) {
            return null;
        }

        // Check if user already exists locally
        $existingUser = User::where('email', $email)->first();
        if ($existingUser) {
            // Link if not already linked
            if (! $existingUser->isFederated()) {
                $this->linkUserToFederatedUser($existingUser, $federatedUser);
            }

            return $existingUser;
        }

        // Create local user from federation data
        return $this->createLocalUserFromFederation($federatedUser);
    }

    /**
     * Create a local user from FederatedUser data.
     */
    public function createLocalUserFromFederation(FederatedUser $federatedUser): User
    {
        $tenant = tenant();
        $membership = $this->getMembership();
        $defaultRole = $membership?->getDefaultRole() ?? 'member';

        $syncedData = $federatedUser->synced_data;
        $centralConnection = config('tenancy.database.central_connection');

        // Create user in tenant database
        $user = DB::transaction(function () use ($federatedUser, $syncedData, $defaultRole) {
            // Create user (federated_user_id is guarded, so we set it separately)
            $user = User::create([
                'name' => $syncedData['name'] ?? 'User',
                'email' => $federatedUser->global_email,
                'password' => $syncedData['password_hash'] ?? Hash::make(str()->random(32)),
                'locale' => $syncedData['locale'] ?? 'en',
                'email_verified_at' => now(),
            ]);

            // Set federated_user_id (guarded field, use forceFill)
            $user->forceFill(['federated_user_id' => $federatedUser->id])->save();

            // Apply 2FA if enabled
            if (! empty($syncedData['two_factor_secret'])) {
                $user->two_factor_secret = $syncedData['two_factor_secret'];
                $user->two_factor_recovery_codes = $syncedData['two_factor_recovery_codes'] ?? null;
                $user->two_factor_confirmed_at = isset($syncedData['two_factor_confirmed_at'])
                    ? \Carbon\Carbon::parse($syncedData['two_factor_confirmed_at'])
                    : null;
                $user->save();
            }

            // Assign default role
            $user->assignRole($defaultRole);

            return $user;
        });

        // Create link in central database (use direct DB insert to bypass tenant connection issues)
        DB::connection($centralConnection)->table('federated_user_links')->insert([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'federated_user_id' => $federatedUser->id,
            'tenant_id' => $tenant->id,
            'tenant_user_id' => $user->id,
            'sync_status' => FederatedUserLinkSyncStatus::SYNCED->value,
            'last_synced_at' => now(),
            'metadata' => json_encode([
                'created_via' => FederatedUserLink::CREATED_VIA_LOGIN,
                'default_role' => $defaultRole,
            ]),
            'sync_attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Invalidate caches
        $this->cacheService->invalidateUserLinks($federatedUser->id);

        return $user;
    }

    // =========================================================================
    // Statistics
    // =========================================================================

    /**
     * Get federation statistics for the current tenant.
     */
    public function getStats(): array
    {
        $group = $this->getCurrentGroup();

        if (! $group) {
            return [
                'is_federated' => false,
            ];
        }

        return [
            'is_federated' => true,
            'is_master' => $this->isMaster(),
            'group_name' => $group->name,
            'federated_users_count' => User::federated()->count(),
            'local_users_count' => User::notFederated()->count(),
            'total_group_tenants' => $group->activeTenants()->count(),
            'sync_strategy' => $group->sync_strategy,
        ];
    }
}
