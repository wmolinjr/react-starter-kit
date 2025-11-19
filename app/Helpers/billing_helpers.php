<?php

if (! function_exists('billing_plans')) {
    function billing_plans(): array
    {
        return [
            'starter' => [
                'name' => 'Starter',
                'price_id' => config('services.stripe.prices.starter'),
                'price' => '$9',
                'interval' => 'month',
                'features' => [
                    '10 team members',
                    '50 projects',
                    '1GB storage',
                    'Email support',
                ],
                'limits' => [
                    'max_users' => 10,
                    'max_projects' => 50,
                    'storage_mb' => 1000,
                ],
            ],
            'professional' => [
                'name' => 'Professional',
                'price_id' => config('services.stripe.prices.professional'),
                'price' => '$29',
                'interval' => 'month',
                'features' => [
                    '50 team members',
                    'Unlimited projects',
                    '10GB storage',
                    'Priority support',
                    'Custom domains',
                ],
                'limits' => [
                    'max_users' => 50,
                    'max_projects' => null,
                    'storage_mb' => 10000,
                ],
            ],
            'enterprise' => [
                'name' => 'Enterprise',
                'price_id' => config('services.stripe.prices.enterprise'),
                'price' => '$99',
                'interval' => 'month',
                'features' => [
                    'Unlimited team members',
                    'Unlimited projects',
                    '100GB storage',
                    '24/7 support',
                    'Custom domains',
                    'SSO',
                    'SLA',
                ],
                'limits' => [
                    'max_users' => null,
                    'max_projects' => null,
                    'storage_mb' => 100000,
                ],
            ],
        ];
    }
}
