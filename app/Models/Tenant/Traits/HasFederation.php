<?php

namespace App\Models\Tenant\Traits;

use App\Models\Central\FederatedUser;
use App\Models\Central\FederatedUserLink;
use App\Models\Central\FederationGroup;

/**
 * HasFederation - Trait for Tenant\User to support federation sync.
 *
 * Provides methods to check if a user is federated and access
 * their federation data from the central database.
 */
trait HasFederation
{
    /**
     * Check if this user is part of a federation group.
     */
    public function isFederated(): bool
    {
        return $this->federated_user_id !== null;
    }

    /**
     * Get the federated user ID (stored locally for quick checks).
     */
    public function getFederatedUserId(): ?string
    {
        return $this->federated_user_id;
    }

    /**
     * Get the FederatedUser model from the central database.
     * Returns null if not federated.
     */
    public function getFederatedUser(): ?FederatedUser
    {
        if (!$this->isFederated()) {
            return null;
        }

        return FederatedUser::find($this->federated_user_id);
    }

    /**
     * Get the federation group this user belongs to.
     */
    public function getFederationGroup(): ?FederationGroup
    {
        $federatedUser = $this->getFederatedUser();

        return $federatedUser?->federationGroup;
    }

    /**
     * Get the federation link for the current tenant context.
     */
    public function getFederationLink(): ?FederatedUserLink
    {
        if (!$this->isFederated()) {
            return null;
        }

        $tenant = tenant();
        if (!$tenant) {
            return null;
        }

        return FederatedUserLink::where('federated_user_id', $this->federated_user_id)
            ->where('tenant_id', $tenant->id)
            ->first();
    }

    /**
     * Check if this user is the master user (exists in master tenant).
     */
    public function isMasterUser(): bool
    {
        $federatedUser = $this->getFederatedUser();
        if (!$federatedUser) {
            return false;
        }

        $tenant = tenant();
        if (!$tenant) {
            return false;
        }

        return $federatedUser->master_tenant_id === $tenant->id
            && $federatedUser->master_tenant_user_id === $this->id;
    }

    /**
     * Get all tenant IDs where this federated user exists.
     */
    public function getFederatedTenantIds(): array
    {
        $federatedUser = $this->getFederatedUser();

        return $federatedUser?->getLinkedTenantIds() ?? [];
    }

    /**
     * Get the number of tenants this user is linked to.
     */
    public function getFederatedTenantCount(): int
    {
        return count($this->getFederatedTenantIds());
    }

    /**
     * Get fields that are synced across tenants.
     */
    public function getSyncedFields(): array
    {
        $group = $this->getFederationGroup();

        return $group?->getSyncFields() ?? [];
    }

    /**
     * Check if a specific field is synced.
     */
    public function isFieldSynced(string $field): bool
    {
        return in_array($field, $this->getSyncedFields());
    }

    /**
     * Get the synced data from the central FederatedUser.
     */
    public function getSyncedData(): array
    {
        $federatedUser = $this->getFederatedUser();

        return $federatedUser?->synced_data ?? [];
    }

    /**
     * Get the last sync timestamp.
     */
    public function getLastSyncedAt(): ?\Carbon\Carbon
    {
        $link = $this->getFederationLink();

        return $link?->last_synced_at;
    }

    /**
     * Check if sync is currently enabled for this user.
     */
    public function isSyncEnabled(): bool
    {
        $link = $this->getFederationLink();

        return $link !== null && $link->isSynced();
    }

    /**
     * Get the sync status for this user.
     */
    public function getSyncStatus(): ?string
    {
        return $this->getFederationLink()?->sync_status;
    }

    /**
     * Prepare data for federation sync.
     * Returns only the fields that should be synced.
     */
    public function toFederationSyncData(): array
    {
        $syncFields = $this->getSyncedFields();

        if (empty($syncFields)) {
            $syncFields = FederationGroup::DEFAULT_SYNC_FIELDS;
        }

        $data = [];

        foreach ($syncFields as $field) {
            if ($field === 'password') {
                // Store password hash, not raw password
                $data['password_hash'] = $this->password;
            } elseif ($field === 'two_factor_secret' || $field === 'two_factor_recovery_codes') {
                // Include 2FA data
                $data[$field] = $this->$field;
                if ($field === 'two_factor_secret') {
                    $data['two_factor_enabled'] = $this->two_factor_confirmed_at !== null;
                }
            } elseif ($field === 'two_factor_confirmed_at') {
                $data[$field] = $this->two_factor_confirmed_at?->toIso8601String();
            } elseif (isset($this->$field)) {
                $data[$field] = $this->$field;
            }
        }

        return $data;
    }

    /**
     * Apply synced data from FederatedUser to this local user.
     * Does NOT save - caller must save after applying.
     */
    public function applyFederationSyncData(array $syncedData): void
    {
        $syncFields = $this->getSyncedFields();

        if (empty($syncFields)) {
            $syncFields = FederationGroup::DEFAULT_SYNC_FIELDS;
        }

        foreach ($syncFields as $field) {
            if ($field === 'password' && isset($syncedData['password_hash'])) {
                // Apply password hash directly (already hashed)
                $this->attributes['password'] = $syncedData['password_hash'];
            } elseif ($field === 'two_factor_confirmed_at' && isset($syncedData[$field])) {
                $this->two_factor_confirmed_at = $syncedData[$field]
                    ? \Carbon\Carbon::parse($syncedData[$field])
                    : null;
            } elseif (isset($syncedData[$field]) && in_array($field, $this->getFillable())) {
                $this->$field = $syncedData[$field];
            } elseif (isset($syncedData[$field])) {
                // For guarded fields like 2FA, set directly
                $this->attributes[$field] = $syncedData[$field];
            }
        }
    }

    /**
     * Link this user to a FederatedUser.
     * Updates the local federated_user_id.
     */
    public function linkToFederatedUser(FederatedUser $federatedUser): bool
    {
        $this->federated_user_id = $federatedUser->id;

        return $this->save();
    }

    /**
     * Unlink this user from federation.
     * User will continue to exist locally but won't sync anymore.
     */
    public function unlinkFromFederation(): bool
    {
        $this->federated_user_id = null;

        return $this->save();
    }

    /**
     * Scope for federated users.
     */
    public function scopeFederated($query)
    {
        return $query->whereNotNull('federated_user_id');
    }

    /**
     * Scope for non-federated users.
     */
    public function scopeNotFederated($query)
    {
        return $query->whereNull('federated_user_id');
    }

    /**
     * Scope for users in a specific federation group.
     */
    public function scopeInFederationGroup($query, string $groupId)
    {
        $federatedUserIds = FederatedUser::where('federation_group_id', $groupId)
            ->pluck('id');

        return $query->whereIn('federated_user_id', $federatedUserIds);
    }
}
