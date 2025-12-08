<?php

namespace App\Http\Controllers\Tenant\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Handles logout for tenant users.
 *
 * Custom implementation replacing Laravel Fortify.
 * Uses 'tenant' guard for logout.
 */
class LogoutController extends Controller
{
    /**
     * Log the user out of the application.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // Logout from tenant guard
        Auth::guard('tenant')->logout();

        // Invalidate session and regenerate token
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Redirect to tenant login
        return redirect()->route('tenant.admin.auth.login');
    }
}
