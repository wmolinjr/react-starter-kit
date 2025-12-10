<?php

declare(strict_types=1);

/**
 * Payment Gateway Configuration
 *
 * Multi-provider payment gateway configuration.
 * Supports Stripe, Asaas, PagSeguro, and MercadoPago.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Default Payment Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default payment "driver" that will be used
    | when no specific driver is specified. Supported: "stripe", "asaas",
    | "pagseguro", "mercadopago"
    |
    */

    'default' => env('PAYMENT_DRIVER', 'stripe'),

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | The default currency used for payments when not specified.
    |
    */

    'currency' => env('PAYMENT_CURRENCY', 'BRL'),

    /*
    |--------------------------------------------------------------------------
    | Payment Drivers
    |--------------------------------------------------------------------------
    |
    | Configuration for each payment provider. Each driver can be enabled
    | or disabled independently, allowing for gradual rollout.
    |
    */

    'drivers' => [

        /*
        |----------------------------------------------------------------------
        | Stripe Configuration
        |----------------------------------------------------------------------
        */
        'stripe' => [
            'enabled' => env('STRIPE_ENABLED', true),
            'key' => env('STRIPE_KEY'),
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'webhook_tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),

            // Stripe-specific options
            'options' => [
                'api_version' => env('STRIPE_API_VERSION', '2024-06-20'),
            ],

            // Supported payment types
            'payment_types' => ['card'],

            // Features
            'features' => [
                'subscriptions' => true,
                'payment_methods' => true,
                'setup_intents' => true,
                'invoices' => true,
                'metered_billing' => true,
                'trials' => true,
                'proration' => true,
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | Asaas Configuration (Brazilian Gateway)
        |----------------------------------------------------------------------
        */
        'asaas' => [
            'enabled' => env('ASAAS_ENABLED', false),
            'api_key' => env('ASAAS_API_KEY'),
            'webhook_token' => env('ASAAS_WEBHOOK_TOKEN'),
            'sandbox' => env('ASAAS_SANDBOX', true),

            // API URLs
            'api_url' => env('ASAAS_API_URL', 'https://api.asaas.com/v3'),
            'sandbox_url' => env('ASAAS_SANDBOX_URL', 'https://sandbox.asaas.com/api/v3'),

            // Supported payment types
            'payment_types' => ['card', 'pix', 'boleto'],

            // Features
            'features' => [
                'subscriptions' => true,
                'payment_methods' => true,
                'setup_intents' => false,
                'invoices' => true,
                'metered_billing' => false,
                'trials' => true,
                'proration' => false,
                'pix' => true,
                'boleto' => true,
            ],

            // PIX configuration
            'pix' => [
                'expiration_minutes' => env('ASAAS_PIX_EXPIRATION', 30),
            ],

            // Boleto configuration
            'boleto' => [
                'due_days' => env('ASAAS_BOLETO_DUE_DAYS', 3),
                'interest_percent' => env('ASAAS_BOLETO_INTEREST', 1.0),
                'fine_percent' => env('ASAAS_BOLETO_FINE', 2.0),
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | PagSeguro Configuration (Brazilian Gateway)
        |----------------------------------------------------------------------
        */
        'pagseguro' => [
            'enabled' => env('PAGSEGURO_ENABLED', false),
            'api_key' => env('PAGSEGURO_API_KEY'),      // Bearer token for API v4
            'public_key' => env('PAGSEGURO_PUBLIC_KEY'), // For client-side encryption
            'receiver_email' => env('PAGSEGURO_EMAIL'), // For subscriptions
            'webhook_token' => env('PAGSEGURO_WEBHOOK_TOKEN'),
            'sandbox' => env('PAGSEGURO_SANDBOX', true),

            // API URLs
            'api_url' => env('PAGSEGURO_API_URL', 'https://api.pagseguro.com'),
            'sandbox_url' => env('PAGSEGURO_SANDBOX_URL', 'https://sandbox.api.pagseguro.com'),

            // Supported payment types
            'payment_types' => ['card', 'pix', 'boleto'],

            // Features
            'features' => [
                'subscriptions' => true,
                'payment_methods' => true,
                'setup_intents' => false,
                'invoices' => false,
                'metered_billing' => false,
                'trials' => true,
                'proration' => false,
                'pix' => true,
                'boleto' => true,
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | MercadoPago Configuration (Latin America Gateway)
        |----------------------------------------------------------------------
        */
        'mercadopago' => [
            'enabled' => env('MERCADOPAGO_ENABLED', false),
            'public_key' => env('MERCADOPAGO_PUBLIC_KEY'),
            'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
            'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET'),
            'sandbox' => env('MERCADOPAGO_SANDBOX', true),

            // API URL
            'api_url' => env('MERCADOPAGO_API_URL', 'https://api.mercadopago.com'),

            // Supported payment types
            'payment_types' => ['card', 'pix', 'boleto', 'debit'],

            // Supported currencies (Latin America)
            'currencies' => ['BRL', 'ARS', 'CLP', 'COP', 'MXN', 'PEN', 'UYU'],

            // Features
            'features' => [
                'subscriptions' => true,
                'payment_methods' => true,
                'setup_intents' => false,
                'invoices' => false,
                'metered_billing' => false,
                'trials' => true,
                'proration' => false,
                'pix' => true,
                'boleto' => true,
                'installments' => true, // Parcelamento
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Configure webhook settings for all providers.
    |
    */

    'webhooks' => [
        // Route prefix for webhook endpoints
        'path' => env('PAYMENT_WEBHOOK_PATH', 'webhooks/payment'),

        // Middleware to apply to webhook routes
        'middleware' => [],

        // Queue for processing webhooks
        'queue' => env('PAYMENT_WEBHOOK_QUEUE', 'default'),

        // Maximum retry attempts
        'max_retries' => env('PAYMENT_WEBHOOK_MAX_RETRIES', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Billing Defaults
    |--------------------------------------------------------------------------
    |
    | Default values for billing operations.
    |
    */

    'billing' => [
        // Default billing period
        'default_period' => 'monthly',

        // Trial days for new subscriptions
        'trial_days' => env('PAYMENT_TRIAL_DAYS', 14),

        // Grace period days after cancellation
        'grace_days' => env('PAYMENT_GRACE_DAYS', 0),

        // Automatically create customer in provider
        'auto_create_customer' => true,

        // Sync customer data on every charge
        'sync_customer_on_charge' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Types
    |--------------------------------------------------------------------------
    |
    | Display names and icons for payment types.
    |
    */

    'payment_types' => [
        'card' => [
            'label' => 'Cartão de Crédito',
            'icon' => 'credit-card',
        ],
        'pix' => [
            'label' => 'PIX',
            'icon' => 'qr-code',
        ],
        'boleto' => [
            'label' => 'Boleto Bancário',
            'icon' => 'file-text',
        ],
        'bank_transfer' => [
            'label' => 'Transferência Bancária',
            'icon' => 'building-2',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Card Brands
    |--------------------------------------------------------------------------
    |
    | Display names and icons for card brands.
    |
    */

    'card_brands' => [
        'visa' => ['label' => 'Visa', 'icon' => 'visa'],
        'mastercard' => ['label' => 'Mastercard', 'icon' => 'mastercard'],
        'amex' => ['label' => 'American Express', 'icon' => 'amex'],
        'elo' => ['label' => 'Elo', 'icon' => 'elo'],
        'hipercard' => ['label' => 'Hipercard', 'icon' => 'hipercard'],
        'diners' => ['label' => 'Diners Club', 'icon' => 'diners'],
        'discover' => ['label' => 'Discover', 'icon' => 'discover'],
        'jcb' => ['label' => 'JCB', 'icon' => 'jcb'],
    ],

];
