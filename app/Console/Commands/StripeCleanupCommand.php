<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Payment\Gateways\StripeGateway;
use App\Services\Payment\PaymentGatewayManager;
use Illuminate\Console\Command;
use Stripe\StripeClient;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class StripeCleanupCommand extends Command
{
    protected $signature = 'stripe:cleanup
                            {--list : List all resources without deleting}
                            {--products : Clean up products and prices}
                            {--customers : Clean up customers}
                            {--subscriptions : Cancel all subscriptions}
                            {--all : Clean up everything}
                            {--force : Skip confirmation prompts}';

    protected $description = 'Clean up Stripe test environment (DEVELOPMENT ONLY)';

    private StripeClient $stripe;

    private ?StripeGateway $gateway = null;

    private int $deletedProducts = 0;

    private int $archivedProducts = 0;

    private int $archivedPrices = 0;

    private int $deletedCustomers = 0;

    private int $cancelledSubscriptions = 0;

    public function __construct(
        protected PaymentGatewayManager $gatewayManager
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // Safety check - only allow in local/testing environments
        if (app()->environment('production')) {
            error('This command cannot be run in production!');

            return self::FAILURE;
        }

        // Get Stripe gateway
        try {
            $this->gateway = $this->gatewayManager->stripe();
        } catch (\Exception $e) {
            error('Stripe gateway is not configured');

            return self::FAILURE;
        }

        if (! $this->gateway->isAvailable()) {
            error('Stripe gateway is not available');

            return self::FAILURE;
        }

        // Check if using test key
        if (! $this->gateway->isTestMode()) {
            $stripeKey = $this->gateway->getSecretKey();
            error('This command only works with Stripe TEST keys (sk_test_*)');
            error('Current key starts with: '.substr($stripeKey ?? '', 0, 10).'...');

            return self::FAILURE;
        }

        $this->stripe = new StripeClient($this->gateway->getSecretKey());

        info('Stripe Cleanup Tool - Development Environment');
        info('Using Stripe TEST mode');
        $this->newLine();

        // List mode
        if ($this->option('list')) {
            return $this->listResources();
        }

        // Determine what to clean
        $cleanAll = $this->option('all');
        $cleanProducts = $cleanAll || $this->option('products');
        $cleanCustomers = $cleanAll || $this->option('customers');
        $cleanSubscriptions = $cleanAll || $this->option('subscriptions');

        // If no specific option, ask user
        if (! $cleanProducts && ! $cleanCustomers && ! $cleanSubscriptions) {
            $choice = select(
                label: 'What do you want to clean?',
                options: [
                    'list' => 'Just list resources (no deletion)',
                    'products' => 'Products and Prices',
                    'customers' => 'Customers',
                    'subscriptions' => 'Subscriptions (cancel all)',
                    'all' => 'Everything (nuclear option)',
                ],
                default: 'list'
            );

            if ($choice === 'list') {
                return $this->listResources();
            }

            $cleanAll = $choice === 'all';
            $cleanProducts = $cleanAll || $choice === 'products';
            $cleanCustomers = $cleanAll || $choice === 'customers';
            $cleanSubscriptions = $cleanAll || $choice === 'subscriptions';
        }

        // Final confirmation
        if (! $this->option('force')) {
            warning('This will permanently delete/archive resources from your Stripe TEST account!');

            if (! confirm('Are you sure you want to proceed?', default: false)) {
                info('Aborted.');

                return self::SUCCESS;
            }
        }

        // Execute cleanup
        if ($cleanSubscriptions) {
            $this->cleanupSubscriptions();
        }

        if ($cleanCustomers) {
            $this->cleanupCustomers();
        }

        if ($cleanProducts) {
            $this->cleanupProducts();
        }

        // Summary
        $this->newLine();
        info('Cleanup completed!');

        $summary = [];
        if ($cleanProducts) {
            $summary[] = ['Products deleted', (string) $this->deletedProducts];
            $summary[] = ['Products archived', (string) $this->archivedProducts];
            $summary[] = ['Prices archived', (string) $this->archivedPrices];
        }
        if ($cleanSubscriptions) {
            $summary[] = ['Subscriptions cancelled', (string) $this->cancelledSubscriptions];
        }
        if ($cleanCustomers) {
            $summary[] = ['Customers deleted', (string) $this->deletedCustomers];
        }

        table(['Action', 'Count'], $summary);

        return self::SUCCESS;
    }

    private function listResources(): int
    {
        // Products
        info('Products:');
        $products = $this->stripe->products->all(['limit' => 100, 'active' => true]);

        if (count($products->data) === 0) {
            $this->line('  No active products found.');
        } else {
            $productData = [];
            foreach ($products->data as $product) {
                $productData[] = [
                    $product->id,
                    substr($product->name ?? 'N/A', 0, 30),
                    $product->active ? 'Active' : 'Inactive',
                    date('Y-m-d', $product->created),
                ];
            }
            table(['ID', 'Name', 'Status', 'Created'], $productData);
        }

        $this->newLine();

        // Prices
        info('Prices:');
        $prices = $this->stripe->prices->all(['limit' => 100, 'active' => true]);

        if (count($prices->data) === 0) {
            $this->line('  No active prices found.');
        } else {
            $priceData = [];
            foreach ($prices->data as $price) {
                $amount = $price->unit_amount ? number_format($price->unit_amount / 100, 2) : 'N/A';
                $priceData[] = [
                    $price->id,
                    $price->product,
                    $amount.' '.strtoupper($price->currency),
                    $price->recurring ? $price->recurring->interval : 'one-time',
                ];
            }
            table(['ID', 'Product', 'Amount', 'Interval'], $priceData);
        }

        $this->newLine();

        // Customers
        info('Customers:');
        $customers = $this->stripe->customers->all(['limit' => 100]);

        if (count($customers->data) === 0) {
            $this->line('  No customers found.');
        } else {
            $customerData = [];
            foreach ($customers->data as $customer) {
                $customerData[] = [
                    $customer->id,
                    substr($customer->email ?? 'N/A', 0, 30),
                    substr($customer->name ?? 'N/A', 0, 20),
                    date('Y-m-d', $customer->created),
                ];
            }
            table(['ID', 'Email', 'Name', 'Created'], $customerData);
        }

        $this->newLine();

        // Subscriptions
        info('Subscriptions:');
        $subscriptions = $this->stripe->subscriptions->all(['limit' => 100, 'status' => 'all']);

        if (count($subscriptions->data) === 0) {
            $this->line('  No subscriptions found.');
        } else {
            $subData = [];
            foreach ($subscriptions->data as $sub) {
                $subData[] = [
                    $sub->id,
                    $sub->customer,
                    $sub->status,
                    date('Y-m-d', $sub->created),
                ];
            }
            table(['ID', 'Customer', 'Status', 'Created'], $subData);
        }

        // Totals
        $this->newLine();
        info('Summary:');
        table(['Resource', 'Count'], [
            ['Active Products', (string) count($products->data)],
            ['Active Prices', (string) count($prices->data)],
            ['Customers', (string) count($customers->data)],
            ['Subscriptions', (string) count($subscriptions->data)],
        ]);

        return self::SUCCESS;
    }

    private function cleanupSubscriptions(): void
    {
        info('Cancelling subscriptions...');

        $subscriptions = $this->stripe->subscriptions->all([
            'limit' => 100,
            'status' => 'active',
        ]);

        foreach ($subscriptions->data as $subscription) {
            try {
                $this->stripe->subscriptions->cancel($subscription->id);
                $this->cancelledSubscriptions++;
                $this->line("  Cancelled: {$subscription->id}");
            } catch (\Exception $e) {
                warning("  Failed to cancel {$subscription->id}: ".$e->getMessage());
            }
        }

        // Also cancel trialing subscriptions
        $trialingSubscriptions = $this->stripe->subscriptions->all([
            'limit' => 100,
            'status' => 'trialing',
        ]);

        foreach ($trialingSubscriptions->data as $subscription) {
            try {
                $this->stripe->subscriptions->cancel($subscription->id);
                $this->cancelledSubscriptions++;
                $this->line("  Cancelled: {$subscription->id}");
            } catch (\Exception $e) {
                warning("  Failed to cancel {$subscription->id}: ".$e->getMessage());
            }
        }
    }

    private function cleanupCustomers(): void
    {
        info('Deleting customers...');

        $hasMore = true;
        $startingAfter = null;

        while ($hasMore) {
            $params = ['limit' => 100];
            if ($startingAfter) {
                $params['starting_after'] = $startingAfter;
            }

            $customers = $this->stripe->customers->all($params);

            foreach ($customers->data as $customer) {
                try {
                    $this->stripe->customers->delete($customer->id);
                    $this->deletedCustomers++;
                    $this->line("  Deleted: {$customer->id} ({$customer->email})");
                } catch (\Exception $e) {
                    warning("  Failed to delete {$customer->id}: ".$e->getMessage());
                }
            }

            $hasMore = $customers->has_more;
            if ($hasMore && count($customers->data) > 0) {
                $startingAfter = end($customers->data)->id;
            }
        }
    }

    private function cleanupProducts(): void
    {
        info('Cleaning up prices...');

        // First, archive all prices (can't delete prices)
        $hasMore = true;
        $startingAfter = null;

        while ($hasMore) {
            $params = ['limit' => 100, 'active' => true];
            if ($startingAfter) {
                $params['starting_after'] = $startingAfter;
            }

            $prices = $this->stripe->prices->all($params);

            foreach ($prices->data as $price) {
                try {
                    $this->stripe->prices->update($price->id, ['active' => false]);
                    $this->archivedPrices++;
                    $this->line("  Archived price: {$price->id}");
                } catch (\Exception $e) {
                    warning("  Failed to archive price {$price->id}: ".$e->getMessage());
                }
            }

            $hasMore = $prices->has_more;
            if ($hasMore && count($prices->data) > 0) {
                $startingAfter = end($prices->data)->id;
            }
        }

        info('Cleaning up products...');

        // Then delete/archive products
        $hasMore = true;
        $startingAfter = null;

        while ($hasMore) {
            $params = ['limit' => 100];
            if ($startingAfter) {
                $params['starting_after'] = $startingAfter;
            }

            $products = $this->stripe->products->all($params);

            foreach ($products->data as $product) {
                try {
                    // Try to delete first
                    $this->stripe->products->delete($product->id);
                    $this->deletedProducts++;
                    $this->line("  Deleted: {$product->id} ({$product->name})");
                } catch (\Exception $e) {
                    // If delete fails, archive it
                    try {
                        $this->stripe->products->update($product->id, ['active' => false]);
                        $this->archivedProducts++;
                        $this->line("  Archived: {$product->id} ({$product->name})");
                    } catch (\Exception $e2) {
                        warning("  Failed to cleanup {$product->id}: ".$e2->getMessage());
                    }
                }
            }

            $hasMore = $products->has_more;
            if ($hasMore && count($products->data) > 0) {
                $startingAfter = end($products->data)->id;
            }
        }
    }
}
