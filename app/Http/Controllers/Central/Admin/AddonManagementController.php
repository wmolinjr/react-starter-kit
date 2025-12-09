<?php

namespace App\Http\Controllers\Central\Admin;

use App\Enums\AddonType;
use App\Enums\CentralPermission;
use App\Http\Controllers\Controller;
use App\Http\Resources\Central\AddonSubscriptionResource;
use App\Models\Central\AddonSubscription;
use App\Models\Central\Tenant;
use App\Services\Central\AddonService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class AddonManagementController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:'.CentralPermission::ADDONS_VIEW->value, only: ['index']),
            new Middleware('can:'.CentralPermission::ADDONS_REVENUE->value, only: ['revenue']),
            new Middleware('can:'.CentralPermission::ADDONS_GRANT->value, only: ['grantAddon']),
            new Middleware('can:'.CentralPermission::ADDONS_REVOKE->value, only: ['revokeAddon']),
        ];
    }

    public function __construct(
        protected AddonService $addonService
    ) {}

    public function index(): Response
    {
        $addons = AddonSubscription::with('tenant')
            ->latest()
            ->paginate(20);

        $stats = [
            'total_addons' => AddonSubscription::count(),
            'active_addons' => AddonSubscription::active()->count(),
            'total_revenue' => AddonSubscription::active()->sum('price'),
            'tenants_with_addons' => AddonSubscription::active()->distinct('tenant_id')->count('tenant_id'),
        ];

        return Inertia::render('central/admin/addons/index', [
            'addons' => AddonSubscriptionResource::collection($addons)->response()->getData(true),
            'stats' => $stats,
        ]);
    }

    public function revenue(): Response
    {
        $monthlyRevenue = AddonSubscription::active()
            ->where('billing_period', 'monthly')
            ->sum('price');

        $yearlyRevenue = AddonSubscription::active()
            ->where('billing_period', 'yearly')
            ->sum('price');

        $revenueByType = AddonSubscription::active()
            ->selectRaw('addon_type, SUM(price * quantity) as total')
            ->groupBy('addon_type')
            ->get()
            ->map(function ($item) {
                $addonType = AddonType::tryFrom($item->addon_type);

                return [
                    'addon_type' => $item->addon_type,
                    'addon_type_label' => $addonType?->label() ?? ucfirst(str_replace('_', ' ', $item->addon_type)),
                    'total' => (int) $item->total,
                    'formatted_total' => '$'.number_format($item->total / 100, 2),
                ];
            });

        return Inertia::render('central/admin/addons/revenue', [
            'monthly_revenue' => $monthlyRevenue,
            'yearly_revenue' => $yearlyRevenue,
            'revenue_by_type' => $revenueByType,
            'formatted_monthly' => '$'.number_format($monthlyRevenue / 100, 2),
            'formatted_yearly' => '$'.number_format($yearlyRevenue / 100, 2),
        ]);
    }

    public function grantAddon(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'addon_slug' => 'required|string',
            'quantity' => 'required|integer|min:1',
            'billing_period' => 'required|in:monthly,yearly,one_time,manual',
            'price' => 'nullable|integer|min:0',
        ]);

        try {
            $this->addonService->purchaseAddon(
                $tenant,
                $validated['addon_slug'],
                $validated['quantity'],
                $validated['billing_period'],
                $validated['price'] ?? null
            );

            return back()->with('success', __('flash.addon.granted'));
        } catch (\Exception $e) {
            return back()->withErrors(['addon' => $e->getMessage()]);
        }
    }

    public function revokeAddon(AddonSubscription $addon)
    {
        try {
            $this->addonService->cancelAddon($addon, 'Revoked by admin');

            return back()->with('success', __('flash.addon.revoked'));
        } catch (\Exception $e) {
            return back()->withErrors(['addon' => $e->getMessage()]);
        }
    }
}
