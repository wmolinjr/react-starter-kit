<?php

use App\Http\Controllers\Customer\Auth\ForgotPasswordController;
use App\Http\Controllers\Customer\Auth\LoginController;
use App\Http\Controllers\Customer\Auth\LogoutController;
use App\Http\Controllers\Customer\Auth\RegisterController;
use App\Http\Controllers\Customer\Auth\ResetPasswordController;
use App\Http\Controllers\Customer\Auth\VerifyEmailController;
use App\Http\Controllers\Customer\DashboardController;
use App\Http\Controllers\Customer\InvoiceController;
use App\Http\Controllers\Customer\PaymentMethodController;
use App\Http\Controllers\Customer\ProfileController;
use App\Http\Controllers\Customer\TenantController;
use App\Http\Controllers\Customer\TransferController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer Portal Routes
|--------------------------------------------------------------------------
|
| Routes for the Customer Billing Portal at /account/*
| Uses 'customer' guard for authentication
|
| Customers are the billing entities - real people who pay for tenants.
| This portal allows them to:
| - Manage their profile and billing information
| - View and manage all their tenants (workspaces)
| - Manage payment methods
| - View invoices
| - Transfer tenant ownership
|
*/

Route::prefix('account')->name('customer.')->group(function () {

    // ─────────────────────────────────────────────────────────────────────────
    // Guest Routes (not authenticated)
    // ─────────────────────────────────────────────────────────────────────────

    Route::middleware('guest:customer')->group(function () {
        // Registration
        Route::get('register', [RegisterController::class, 'create'])->name('register');
        Route::post('register', [RegisterController::class, 'store'])->name('register.store');

        // Login
        Route::get('login', [LoginController::class, 'create'])->name('login');
        Route::post('login', [LoginController::class, 'store'])->name('login.store');

        // Password Reset
        Route::get('forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
        Route::post('forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');
        Route::get('reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
        Route::post('reset-password', [ResetPasswordController::class, 'store'])->name('password.update');

        // Transfer acceptance (can view before login, but must login to accept)
        Route::get('transfers/{token}/accept', [TransferController::class, 'showAccept'])->name('transfers.accept.show');
    });

    // ─────────────────────────────────────────────────────────────────────────
    // Authenticated Routes
    // ─────────────────────────────────────────────────────────────────────────

    Route::middleware('auth:customer')->group(function () {
        // Logout
        Route::post('logout', LogoutController::class)->name('logout');

        // Email Verification
        Route::get('verify-email', [VerifyEmailController::class, 'notice'])->name('verification.notice');
        Route::get('verify-email/{id}/{hash}', [VerifyEmailController::class, 'verify'])
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');
        Route::post('email/verification-notification', [VerifyEmailController::class, 'send'])
            ->middleware('throttle:6,1')
            ->name('verification.send');

        // ─────────────────────────────────────────────────────────────────────
        // Verified Routes (email must be verified)
        // ─────────────────────────────────────────────────────────────────────

        Route::middleware('customer.verified')->group(function () {
            // Dashboard
            Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

            // Profile Management
            Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
            Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
            Route::patch('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
            Route::patch('profile/billing', [ProfileController::class, 'updateBilling'])->name('profile.billing');
            Route::delete('profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

            // Tenants (Workspaces)
            Route::get('tenants', [TenantController::class, 'index'])->name('tenants.index');
            Route::get('tenants/create', [TenantController::class, 'create'])->name('tenants.create');
            Route::post('tenants', [TenantController::class, 'store'])->name('tenants.store');
            Route::get('tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
            Route::get('tenants/{tenant}/billing', [TenantController::class, 'billing'])->name('tenants.billing');
            Route::patch('tenants/{tenant}/payment-method', [TenantController::class, 'updatePaymentMethod'])
                ->name('tenants.payment-method');

            // Tenant Transfers
            Route::get('tenants/{tenant}/transfer', [TransferController::class, 'create'])->name('transfers.create');
            Route::post('tenants/{tenant}/transfer', [TransferController::class, 'store'])->name('transfers.store');
            Route::post('transfers/{token}/confirm', [TransferController::class, 'confirm'])->name('transfers.confirm');
            Route::post('transfers/{transfer}/cancel', [TransferController::class, 'cancel'])->name('transfers.cancel');
            Route::post('transfers/{transfer}/reject', [TransferController::class, 'reject'])->name('transfers.reject');

            // Payment Methods
            Route::get('payment-methods', [PaymentMethodController::class, 'index'])->name('payment-methods.index');
            Route::get('payment-methods/create', [PaymentMethodController::class, 'create'])->name('payment-methods.create');
            Route::post('payment-methods', [PaymentMethodController::class, 'store'])->name('payment-methods.store');
            Route::delete('payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy'])
                ->name('payment-methods.destroy');
            Route::post('payment-methods/{paymentMethod}/default', [PaymentMethodController::class, 'setDefault'])
                ->name('payment-methods.default');

            // Invoices
            Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
            Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
            Route::get('invoices/{invoice}/download', [InvoiceController::class, 'download'])->name('invoices.download');

            // Stripe Billing Portal (redirect to Stripe-hosted portal)
            Route::get('billing-portal', [ProfileController::class, 'billingPortal'])->name('billing-portal');

            // API Routes (JSON responses for AJAX polling)
            Route::prefix('api')->name('api.')->group(function () {
                // Purchase status polling for async payments (PIX, Boleto)
                Route::get('purchases/{purchase}/status', [TenantController::class, 'purchaseStatus'])
                    ->name('purchases.status');
            });
        });
    });
});
