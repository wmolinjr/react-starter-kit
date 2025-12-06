<?php

namespace App\Http\Controllers\Central\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Handles logout for central admins.
 *
 * TENANT-ONLY ARCHITECTURE (Option C):
 * - Uses 'central' guard for logout
 * - Separate from tenant user logout (Fortify)
 */
class AdminLogoutController extends Controller
{
    /**
     * Log the admin out of the application.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // Logout from central guard
        Auth::guard('central')->logout();

        // Invalidate session and regenerate token
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Redirect to central home
        return redirect()->route('central.home');
    }
}
