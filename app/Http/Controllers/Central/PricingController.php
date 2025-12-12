<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Resources\Central\PlanResource;
use App\Models\Central\Plan;
use Inertia\Inertia;
use Inertia\Response;

/**
 * PricingController
 *
 * Handles the public pricing page.
 */
class PricingController extends Controller
{
    /**
     * Display the public pricing page.
     *
     * GET /pricing
     */
    public function index(): Response
    {
        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('price')
            ->get();

        return Inertia::render('central/pricing/index', [
            'plans' => PlanResource::collection($plans),
        ]);
    }
}
