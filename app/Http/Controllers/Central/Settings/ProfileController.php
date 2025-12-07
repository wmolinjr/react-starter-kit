<?php

namespace App\Http\Controllers\Central\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('central/admin/user-settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile settings.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return to_route('central.admin.settings.profile.edit');
    }

    /**
     * Update the user's locale preference.
     */
    public function updateLocale(Request $request): RedirectResponse
    {
        $availableLocales = config('app.locales', ['en']);

        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:'.implode(',', $availableLocales)],
        ]);

        $request->user()->update([
            'locale' => $validated['locale'],
        ]);

        $cookie = cookie('locale', $validated['locale'], 60 * 24 * 365);

        return back()
            ->with('success', __('settings.language.updated'))
            ->withCookie($cookie);
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
