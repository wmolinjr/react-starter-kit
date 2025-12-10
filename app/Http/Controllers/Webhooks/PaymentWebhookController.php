<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Events\Payment\WebhookReceived;
use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentGatewayManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Payment Webhook Controller
 *
 * Handles incoming webhooks from all payment providers.
 * Validates signatures, dispatches events, and delegates to gateway handlers.
 */
class PaymentWebhookController extends Controller
{
    public function __construct(
        protected PaymentGatewayManager $gatewayManager
    ) {}

    /**
     * Handle Stripe webhook.
     */
    public function handleStripe(Request $request): Response
    {
        return $this->handleWebhook('stripe', $request);
    }

    /**
     * Handle Asaas webhook.
     */
    public function handleAsaas(Request $request): Response
    {
        return $this->handleWebhook('asaas', $request);
    }

    /**
     * Handle PagSeguro webhook.
     */
    public function handlePagseguro(Request $request): Response
    {
        return $this->handleWebhook('pagseguro', $request);
    }

    /**
     * Handle MercadoPago webhook.
     */
    public function handleMercadopago(Request $request): Response
    {
        return $this->handleWebhook('mercadopago', $request);
    }

    /**
     * Handle webhook for any provider.
     */
    protected function handleWebhook(string $provider, Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $this->getSignatureHeader($provider, $request);

        // Get gateway
        try {
            $gateway = $this->gatewayManager->driver($provider);
        } catch (\Exception $e) {
            Log::error("Payment webhook: Gateway not found", [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return $this->missingMethod();
        }

        // Validate signature
        if (! $gateway->validateWebhookSignature($payload, $signature)) {
            Log::warning("Payment webhook: Invalid signature", [
                'provider' => $provider,
            ]);

            return $this->invalidSignature();
        }

        // Parse payload
        $data = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error("Payment webhook: Invalid JSON payload", [
                'provider' => $provider,
            ]);

            return $this->invalidPayload();
        }

        Log::info("Payment webhook received", [
            'provider' => $provider,
            'type' => $data['type'] ?? 'unknown',
        ]);

        // Dispatch event for listeners
        WebhookReceived::dispatch($provider, $data, $request->headers->all());

        // Let gateway handle provider-specific logic
        try {
            $gateway->handleWebhook($data, $request->headers->all());
        } catch (\Exception $e) {
            Log::error("Payment webhook: Handler error", [
                'provider' => $provider,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Still return success to prevent retries for handled errors
            return $this->successMethod();
        }

        return $this->successMethod();
    }

    /**
     * Get signature header based on provider.
     */
    protected function getSignatureHeader(string $provider, Request $request): string
    {
        return match ($provider) {
            'stripe' => $request->header('Stripe-Signature', ''),
            'asaas' => $request->header('asaas-access-token', ''),
            'pagseguro' => $request->header('x-pagseguro-signature', ''),
            'mercadopago' => $request->header('x-signature', ''),
            default => '',
        };
    }

    /**
     * Handle successful webhook.
     */
    protected function successMethod(): Response
    {
        return new Response('Webhook handled', 200);
    }

    /**
     * Handle invalid signature.
     */
    protected function invalidSignature(): Response
    {
        return new Response('Invalid signature', 403);
    }

    /**
     * Handle invalid payload.
     */
    protected function invalidPayload(): Response
    {
        return new Response('Invalid payload', 400);
    }

    /**
     * Handle missing gateway method.
     */
    protected function missingMethod(): Response
    {
        return new Response('Gateway not configured', 404);
    }
}
