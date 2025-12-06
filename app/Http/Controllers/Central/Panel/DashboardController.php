<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Central Panel Dashboard Controller.
 *
 * OPTION C ARCHITECTURE:
 * This page is primarily for the central domain. In Option C:
 * - Users exist ONLY in tenant databases
 * - There's no User->tenants() relationship
 * - Users access their tenant directly via domain
 *
 * This controller is kept for backwards compatibility but returns
 * empty tenants list since tenant users don't have central accounts.
 *
 * Note: Admins use a separate admin panel (Central Admin Dashboard).
 */
class DashboardController extends Controller
{
    /**
     * Display the central panel dashboard.
     *
     * Option C: Returns empty tenants since users only exist in tenant databases.
     */
    public function __invoke(Request $request): Response
    {
        // Option C: Users don't have tenants() relationship
        // They access their tenant directly via domain
        return Inertia::render('central/panel/dashboard', [
            'tenants' => [],
        ]);
    }
}
