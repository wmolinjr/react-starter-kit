<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Enums\TenantPermission;
use App\Exceptions\Tenant\FederationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\FederateBulkUsersRequest;
use App\Http\Requests\Tenant\FederateUserRequest;
use App\Http\Resources\Tenant\FederationGroupForTenantResource;
use App\Http\Resources\Tenant\TeamMemberResource;
use App\Http\Resources\Tenant\TenantFederationMembershipResource;
use App\Http\Resources\Tenant\UserFederationInfoResource;
use App\Models\Tenant\User;
use App\Services\Tenant\FederationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * FederationController (Tenant)
 *
 * Handles federation operations from the tenant's perspective.
 * Allows tenant admins to:
 * - View federation status
 * - Federate/unfederate local users
 * - View federated users in this tenant
 */
class FederationController extends Controller implements HasMiddleware
{
    public function __construct(
        protected FederationService $federationService
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:'.TenantPermission::FEDERATION_VIEW->value, only: ['index', 'show']),
            new Middleware('permission:'.TenantPermission::FEDERATION_MANAGE->value, only: ['federateUser', 'unfederateUser', 'federateAll', 'federateBulk']),
        ];
    }

    /**
     * Display federation status and users.
     */
    public function index(): Response
    {
        $stats = $this->federationService->getStats();
        $group = $this->federationService->getCurrentGroup();
        $membership = $this->federationService->getMembership();

        return Inertia::render('tenant/admin/settings/federation', [
            'stats' => $stats,
            'group' => $group
                ? (new FederationGroupForTenantResource($group))
                    ->additional(['is_master' => $this->federationService->isMaster()])
                : null,
            'membership' => $membership ? new TenantFederationMembershipResource($membership) : null,
            'federatedUsers' => TeamMemberResource::collection($this->federationService->getFederatedUsers()),
            'localOnlyUsers' => TeamMemberResource::collection($this->federationService->getLocalOnlyUsers()),
        ]);
    }

    /**
     * Show federation info for a specific user.
     */
    public function show(User $user): Response
    {
        $federationInfo = $this->federationService->getUserFederationInfo($user);

        return Inertia::render('tenant/admin/team/federation-info', [
            'user' => new TeamMemberResource($user),
            'federationInfo' => new UserFederationInfoResource($federationInfo),
            'canFederate' => $this->federationService->isFederated() && ! $user->isFederated(),
            'canUnfederate' => $user->isFederated() && ! $user->isMasterUser(),
        ]);
    }

    /**
     * Federate an existing local user.
     */
    public function federateUser(FederateUserRequest $request): RedirectResponse
    {
        try {
            $user = User::findOrFail($request->validated()['user_id']);

            $this->federationService->federateUser($user);

            return back()->with('success', __('flash.federation.user_federated', ['email' => $user->email]));

        } catch (FederationException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Unfederate a user (remove from federation but keep local).
     */
    public function unfederateUser(User $user): RedirectResponse
    {
        try {
            // Prevent unfederating the master user
            if ($user->isMasterUser()) {
                return back()->with('error', __('flash.federation.cannot_unfederate_master'));
            }

            $this->federationService->unfederateUser($user);

            return back()->with('success', __('flash.federation.user_unfederated', ['email' => $user->email]));

        } catch (FederationException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Manually sync a federated user's data.
     */
    public function syncUser(User $user): RedirectResponse
    {
        if (! $user->isFederated()) {
            return back()->with('error', __('flash.federation.user_not_federated'));
        }

        // Apply federation data to local user
        $this->federationService->applyFederationDataToUser($user);

        return back()->with('success', __('flash.federation.user_synced'));
    }

    /**
     * Federate all local users at once.
     */
    public function federateAll(): RedirectResponse
    {
        $localUsers = $this->federationService->getLocalOnlyUsers();

        if ($localUsers->isEmpty()) {
            return back()->with('info', __('flash.federation.no_local_users'));
        }

        $results = $this->federationService->federateUsers($localUsers);

        if ($results['failed'] > 0) {
            return back()->with('warning', __('flash.federation.bulk_federated_partial', [
                'success' => $results['success'],
                'failed' => $results['failed'],
            ]));
        }

        return back()->with('success', __('flash.federation.bulk_federated', [
            'count' => $results['success'],
        ]));
    }

    /**
     * Federate selected users.
     */
    public function federateBulk(FederateBulkUsersRequest $request): RedirectResponse
    {
        $users = User::whereIn('id', $request->validated()['user_ids'])
            ->whereNull('federated_user_id')
            ->get();

        if ($users->isEmpty()) {
            return back()->with('error', __('flash.federation.no_users_selected'));
        }

        $results = $this->federationService->federateUsers($users);

        if ($results['failed'] > 0) {
            return back()->with('warning', __('flash.federation.bulk_federated_partial', [
                'success' => $results['success'],
                'failed' => $results['failed'],
            ]));
        }

        return back()->with('success', __('flash.federation.bulk_federated', [
            'count' => $results['success'],
        ]));
    }
}
