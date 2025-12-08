<?php

namespace App\Services\Central;

use App\Models\Central\FederatedUser;
use App\Models\Central\FederationGroup;
use Illuminate\Support\Facades\Cache;

/**
 * FederationCacheService
 *
 * Handles caching for federation-related data to improve performance.
 */
class FederationCacheService
{
    /**
     * Cache TTL in seconds (1 hour).
     */
    private const CACHE_TTL = 3600;

    /**
     * Cache key prefixes.
     */
    private const PREFIX_TENANT_GROUP = 'federation:tenant_group:';
    private const PREFIX_GROUP_TENANTS = 'federation:group_tenants:';
    private const PREFIX_USER_LINKS = 'federation:user_links:';
    private const PREFIX_USER_BY_EMAIL = 'federation:user_email:';

    // =========================================================================
    // Tenant Group Cache
    // =========================================================================

    /**
     * Get federation group for a tenant (cached).
     */
    public function getTenantGroup(string $tenantId): ?FederationGroup
    {
        return Cache::remember(
            self::PREFIX_TENANT_GROUP . $tenantId,
            self::CACHE_TTL,
            function () use ($tenantId) {
                return FederationGroup::whereHas('tenants', function ($query) use ($tenantId) {
                    $query->where('tenants.id', $tenantId)
                        ->whereNull('federation_group_tenants.left_at');
                })->first();
            }
        );
    }

    /**
     * Invalidate tenant's group cache.
     */
    public function invalidateTenant(string $tenantId): void
    {
        Cache::forget(self::PREFIX_TENANT_GROUP . $tenantId);
    }

    // =========================================================================
    // Group Tenants Cache
    // =========================================================================

    /**
     * Get all active tenant IDs in a group (cached).
     */
    public function getGroupTenantIds(string $groupId): array
    {
        return Cache::remember(
            self::PREFIX_GROUP_TENANTS . $groupId,
            self::CACHE_TTL,
            function () use ($groupId) {
                $group = FederationGroup::find($groupId);
                if (!$group) {
                    return [];
                }

                return $group->activeTenants()->pluck('tenants.id')->toArray();
            }
        );
    }

    /**
     * Invalidate group's tenant list cache.
     */
    public function invalidateGroup(string $groupId): void
    {
        Cache::forget(self::PREFIX_GROUP_TENANTS . $groupId);
    }

    // =========================================================================
    // User Links Cache
    // =========================================================================

    /**
     * Get all tenant IDs a federated user is linked to (cached).
     */
    public function getUserLinkedTenantIds(string $federatedUserId): array
    {
        return Cache::remember(
            self::PREFIX_USER_LINKS . $federatedUserId,
            self::CACHE_TTL,
            function () use ($federatedUserId) {
                $federatedUser = FederatedUser::find($federatedUserId);
                if (!$federatedUser) {
                    return [];
                }

                return $federatedUser->activeLinks()->pluck('tenant_id')->toArray();
            }
        );
    }

    /**
     * Invalidate user's links cache.
     */
    public function invalidateUserLinks(string $federatedUserId): void
    {
        Cache::forget(self::PREFIX_USER_LINKS . $federatedUserId);
    }

    // =========================================================================
    // User by Email Cache
    // =========================================================================

    /**
     * Get federated user by email in a group (cached).
     */
    public function getUserByEmailInGroup(string $email, string $groupId): ?FederatedUser
    {
        $cacheKey = self::PREFIX_USER_BY_EMAIL . md5(strtolower($email) . ':' . $groupId);

        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            function () use ($email, $groupId) {
                return FederatedUser::findByEmailInGroup($email, $groupId);
            }
        );
    }

    /**
     * Invalidate user email cache.
     */
    public function invalidateUserByEmail(string $email, string $groupId): void
    {
        $cacheKey = self::PREFIX_USER_BY_EMAIL . md5(strtolower($email) . ':' . $groupId);
        Cache::forget($cacheKey);
    }

    // =========================================================================
    // Bulk Invalidation
    // =========================================================================

    /**
     * Invalidate all caches for a federation group.
     */
    public function invalidateAllForGroup(FederationGroup $group): void
    {
        // Invalidate group cache
        $this->invalidateGroup($group->id);

        // Invalidate all tenant caches
        foreach ($group->tenants as $tenant) {
            $this->invalidateTenant($tenant->id);
        }

        // Invalidate all user caches
        foreach ($group->federatedUsers as $user) {
            $this->invalidateUserLinks($user->id);
            $this->invalidateUserByEmail($user->global_email, $group->id);
        }
    }

    /**
     * Clear all federation caches.
     */
    public function clearAll(): void
    {
        // This is a simple implementation - in production you might want to use
        // tagged caching or a more sophisticated approach
        Cache::flush();
    }

    // =========================================================================
    // Debounce Support
    // =========================================================================

    /**
     * Check if we should process a sync for a user/field.
     * Implements debouncing to avoid duplicate syncs.
     *
     * @param int $debounceSeconds How long to debounce (default: 5 seconds)
     */
    public function shouldSync(string $federatedUserId, string $field, int $debounceSeconds = 5): bool
    {
        $key = "federation:debounce:{$federatedUserId}:{$field}";

        if (Cache::has($key)) {
            return false;
        }

        Cache::put($key, true, $debounceSeconds);
        return true;
    }

    /**
     * Mark a sync as completed (clear debounce).
     */
    public function syncCompleted(string $federatedUserId, string $field): void
    {
        $key = "federation:debounce:{$federatedUserId}:{$field}";
        Cache::forget($key);
    }
}
