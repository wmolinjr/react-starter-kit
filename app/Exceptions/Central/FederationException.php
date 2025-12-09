<?php

namespace App\Exceptions\Central;

use App\Models\Central\FederationGroup;
use App\Models\Central\Tenant;
use Exception;

/**
 * FederationException
 *
 * Exception for federation-related errors.
 */
class FederationException extends Exception
{
    public static function tenantAlreadyInGroup(Tenant $tenant): self
    {
        return new self(
            __('federation.errors.tenant_already_in_group', [
                'tenant' => $tenant->name,
            ])
        );
    }

    public static function tenantNotInGroup(Tenant $tenant, FederationGroup $group): self
    {
        return new self(
            __('federation.errors.tenant_not_in_group', [
                'tenant' => $tenant->name,
                'group' => $group->name,
            ])
        );
    }

    public static function cannotRemoveMasterTenant(): self
    {
        return new self(__('federation.errors.cannot_remove_master'));
    }

    public static function alreadyMaster(Tenant $tenant): self
    {
        return new self(
            __('federation.errors.already_master', [
                'tenant' => $tenant->name,
            ])
        );
    }

    public static function userAlreadyFederated(string $email): self
    {
        return new self(
            __('federation.errors.user_already_federated', [
                'email' => $email,
            ])
        );
    }

    public static function userNotFederated(string $email): self
    {
        return new self(
            __('federation.errors.user_not_federated', [
                'email' => $email,
            ])
        );
    }

    public static function groupNotActive(FederationGroup $group): self
    {
        return new self(
            __('federation.errors.group_not_active', [
                'group' => $group->name,
            ])
        );
    }

    public static function syncDisabled(Tenant $tenant): self
    {
        return new self(
            __('federation.errors.sync_disabled', [
                'tenant' => $tenant->name,
            ])
        );
    }

    public static function invalidSyncStrategy(string $strategy): self
    {
        return new self(
            __('federation.errors.invalid_sync_strategy', [
                'strategy' => $strategy,
            ])
        );
    }

    public static function conflictUnresolved(string $field): self
    {
        return new self(
            __('federation.errors.conflict_unresolved', [
                'field' => $field,
            ])
        );
    }

    public static function notInFederationGroup(): self
    {
        return new self(__('federation.errors.not_in_federation_group'));
    }

    public static function userAlreadyLinked(string $email): self
    {
        return new self(
            __('federation.errors.user_already_linked', [
                'email' => $email,
            ])
        );
    }
}
