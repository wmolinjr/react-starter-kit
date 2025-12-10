<?php

use App\Http\Controllers\Webhooks\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Webhook Routes
|--------------------------------------------------------------------------
|
| These routes handle incoming webhooks from payment providers.
| They bypass CSRF verification and are accessible without authentication.
|
| Each provider has its own endpoint for webhook handling:
| - /stripe/webhook    - Stripe webhooks
| - /asaas/webhook     - Asaas webhooks (Brazil)
| - /pagseguro/webhook - PagSeguro webhooks (Brazil)
| - /mercadopago/webhook - MercadoPago webhooks (LATAM)
|
*/

// Stripe webhooks
Route::post('/stripe/webhook', [PaymentWebhookController::class, 'handleStripe'])
    ->name('payment.webhook.stripe');

// Asaas webhooks (Brazilian payment provider)
Route::post('/asaas/webhook', [PaymentWebhookController::class, 'handleAsaas'])
    ->name('payment.webhook.asaas');

// PagSeguro webhooks (Brazilian payment provider)
Route::post('/pagseguro/webhook', [PaymentWebhookController::class, 'handlePagseguro'])
    ->name('payment.webhook.pagseguro');

// MercadoPago webhooks (Latin America payment provider)
Route::post('/mercadopago/webhook', [PaymentWebhookController::class, 'handleMercadopago'])
    ->name('payment.webhook.mercadopago');

// Legacy alias for backwards compatibility with existing Stripe integrations
Route::post('/webhook/stripe', [PaymentWebhookController::class, 'handleStripe'])
    ->name('cashier.webhook');
