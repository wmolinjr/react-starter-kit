<?php

namespace App\Providers;

use App\Actions\Fortify\Tenant\CreateNewUser;
use App\Actions\Fortify\Tenant\ResetUserPassword;
use App\Models\Tenant\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * TENANT-ONLY ARCHITECTURE (Option C):
     * - Fortify is used ONLY for tenant user authentication
     * - Central admin authentication is handled separately (AdminLoginController)
     * - FortifyRouteBootstrapper handles tenant-aware redirects automatically
     *
     * @see App\Http\Controllers\Central\Auth\AdminLoginController
     * @see config/tenancy.php (FortifyRouteBootstrapper)
     */
    public function register(): void
    {
        // No custom Response classes needed - Fortify defaults + FortifyRouteBootstrapper
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureAuthentication();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure custom authentication logic for tenant-only users.
     *
     * TENANT-ONLY ARCHITECTURE (Option C):
     * - Users exist ONLY in tenant databases
     * - When tenancy is initialized, User::where() queries the tenant database
     * - No cross-database lookups needed
     */
    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request) {
            // Find user in the current database context
            // - In tenant context: queries tenant database (users table)
            // - In central context: queries central database (but users don't exist there)
            $user = User::where('email', $request->email)->first();

            if ($user && Hash::check($request->password, $user->password)) {
                return $user;
            }

            return null;
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('shared/auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'canRegister' => Features::enabled(Features::registration()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('shared/auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('shared/auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('shared/auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('shared/auth/register'));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('shared/auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('shared/auth/confirm-password'));
    }

    /**
     * Configure rate limiting for authentication endpoints.
     *
     * Implements strict rate limiting to prevent brute force attacks,
     * credential stuffing, and enumeration attacks.
     */
    private function configureRateLimiting(): void
    {
        // Two-factor authentication: 5 attempts per minute
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        // Login: 5 attempts per minute per email+IP combination
        // Prevents brute force attacks
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        // Registration: 3 attempts per hour per IP
        // Prevents mass account creation and spam
        RateLimiter::for('register', function (Request $request) {
            return [
                Limit::perMinute(3)->by($request->ip())->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many registration attempts. Please try again later.',
                    ], 429, $headers);
                }),
                Limit::perHour(10)->by($request->ip()),
            ];
        });

        // Password reset request: 3 attempts per hour per IP
        // Prevents email enumeration and spam
        RateLimiter::for('password.reset', function (Request $request) {
            return Limit::perHour(3)->by($request->ip());
        });

        // Email verification: 6 attempts per minute
        // Allows legitimate users to retry but prevents abuse
        RateLimiter::for('verification', function (Request $request) {
            return Limit::perMinute(6)->by($request->user()?->id ?: $request->ip());
        });
    }
}
