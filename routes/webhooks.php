<?php

use App\Http\Controllers\Billing\AddonWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Webhook Routes
|--------------------------------------------------------------------------
|
| These routes handle incoming webhooks from external services like Stripe.
| They bypass CSRF verification and are accessible without authentication.
|
*/

Route::post('/stripe/webhook', [AddonWebhookController::class, 'handleWebhook'])
    ->name('cashier.webhook');
