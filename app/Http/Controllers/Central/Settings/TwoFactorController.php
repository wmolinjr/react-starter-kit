<?php

namespace App\Http\Controllers\Central\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Fortify;

/**
 * Two-Factor Authentication API for Central administrators.
 *
 * Central admins don't use Fortify routes (which use tenant guard),
 * so we provide custom routes that use the same Fortify Actions.
 *
 * Routes:
 * - POST   /admin/settings/two-factor/enable     - Enable 2FA (generates secret + recovery codes)
 * - POST   /admin/settings/two-factor/confirm    - Confirm 2FA with TOTP code
 * - DELETE /admin/settings/two-factor/disable    - Disable 2FA
 * - GET    /admin/settings/two-factor/qr-code    - Get QR code SVG
 * - GET    /admin/settings/two-factor/recovery-codes - Get recovery codes
 * - POST   /admin/settings/two-factor/recovery-codes - Regenerate recovery codes
 */
class TwoFactorController extends Controller
{
    /**
     * Enable two-factor authentication for the user.
     * Generates secret key and recovery codes.
     */
    public function enable(Request $request, EnableTwoFactorAuthentication $enable): RedirectResponse
    {
        $enable($request->user(), $request->boolean('force', false));

        return back()->with('status', Fortify::TWO_FACTOR_AUTHENTICATION_ENABLED);
    }

    /**
     * Confirm two-factor authentication with a valid TOTP code.
     */
    public function confirm(Request $request, ConfirmTwoFactorAuthentication $confirm): RedirectResponse
    {
        $confirm($request->user(), $request->input('code'));

        return back()->with('status', Fortify::TWO_FACTOR_AUTHENTICATION_CONFIRMED);
    }

    /**
     * Disable two-factor authentication for the user.
     */
    public function disable(Request $request, DisableTwoFactorAuthentication $disable): RedirectResponse
    {
        $disable($request->user());

        return back()->with('status', Fortify::TWO_FACTOR_AUTHENTICATION_DISABLED);
    }

    /**
     * Get the QR code SVG for the user's two-factor authentication.
     */
    public function qrCode(Request $request): JsonResponse
    {
        if (is_null($request->user()->two_factor_secret)) {
            return response()->json([]);
        }

        return response()->json([
            'svg' => $request->user()->twoFactorQrCodeSvg(),
            'url' => $request->user()->twoFactorQrCodeUrl(),
        ]);
    }

    /**
     * Get the user's two-factor authentication recovery codes.
     */
    public function recoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->two_factor_secret || ! $user->two_factor_recovery_codes) {
            return response()->json([]);
        }

        return response()->json(
            json_decode(Fortify::currentEncrypter()->decrypt($user->two_factor_recovery_codes), true)
        );
    }

    /**
     * Generate a fresh set of two-factor authentication recovery codes.
     */
    public function regenerateRecoveryCodes(Request $request, GenerateNewRecoveryCodes $generate): RedirectResponse
    {
        $generate($request->user());

        return back()->with('status', Fortify::RECOVERY_CODES_GENERATED);
    }
}
