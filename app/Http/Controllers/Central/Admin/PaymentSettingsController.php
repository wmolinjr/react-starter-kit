<?php

namespace App\Http\Controllers\Central\Admin;

use App\Enums\CentralPermission;
use App\Enums\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\UpdatePaymentSettingRequest;
use App\Http\Resources\Central\PaymentSettingResource;
use App\Services\Central\PaymentSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * PaymentSettingsController
 *
 * Manages payment gateway configuration in Central Admin.
 * Supports multiple gateways with separate sandbox/production credentials.
 */
class PaymentSettingsController extends Controller implements HasMiddleware
{
    public function __construct(
        protected PaymentSettingsService $paymentSettingsService
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:'.CentralPermission::PAYMENT_SETTINGS_VIEW->value, only: ['index']),
            new Middleware('can:'.CentralPermission::PAYMENT_SETTINGS_MANAGE->value, only: ['update', 'toggleSandbox', 'test']),
        ];
    }

    /**
     * Display payment settings page.
     */
    public function index(): Response
    {
        $settings = $this->paymentSettingsService->getAllSettings();

        return Inertia::render('central/admin/payment-settings/index', [
            'settings' => PaymentSettingResource::collection($settings),
            'gateways' => PaymentGateway::toFrontendMap(),
        ]);
    }

    /**
     * Update a gateway's settings.
     */
    public function update(UpdatePaymentSettingRequest $request, string $gateway): RedirectResponse
    {
        $gatewayEnum = PaymentGateway::from($gateway);

        $this->paymentSettingsService->updateSetting($gatewayEnum, $request->validated());

        return back()->with('success', __('payment_settings.success.updated', [
            'gateway' => $gatewayEnum->displayName(),
        ]));
    }

    /**
     * Toggle sandbox mode for a gateway.
     */
    public function toggleSandbox(string $gateway): RedirectResponse
    {
        $gatewayEnum = PaymentGateway::from($gateway);

        $setting = $this->paymentSettingsService->toggleSandbox($gatewayEnum);

        $mode = $setting->is_sandbox ? __('payment_settings.mode.sandbox') : __('payment_settings.mode.production');

        return back()->with('success', __('payment_settings.success.mode_changed', [
            'gateway' => $gatewayEnum->displayName(),
            'mode' => $mode,
        ]));
    }

    /**
     * Test connection to a gateway.
     */
    public function test(string $gateway): RedirectResponse
    {
        $gatewayEnum = PaymentGateway::from($gateway);

        $result = $this->paymentSettingsService->testConnection($gatewayEnum);

        return $result['success']
            ? back()->with('success', $result['message'])
            : back()->with('error', $result['message']);
    }
}
