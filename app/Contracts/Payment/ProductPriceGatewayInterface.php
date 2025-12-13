<?php

declare(strict_types=1);

namespace App\Contracts\Payment;

/**
 * Interface for gateways that support product and price management.
 *
 * Used for syncing plans and addons to payment providers.
 */
interface ProductPriceGatewayInterface
{
    /**
     * Create a product in the payment provider.
     *
     * @param  array{name: string, description?: string, metadata?: array}  $data
     * @return array{id: string, ...}
     */
    public function createProduct(array $data): array;

    /**
     * Update a product in the payment provider.
     *
     * @param  array{name?: string, description?: string, active?: bool, metadata?: array}  $data
     * @return array{id: string, ...}
     */
    public function updateProduct(string $productId, array $data): array;

    /**
     * Retrieve a product from the payment provider.
     *
     * @return array{id: string, name: string, ...}
     */
    public function retrieveProduct(string $productId): array;

    /**
     * Archive (deactivate) a product.
     */
    public function archiveProduct(string $productId): bool;

    /**
     * Create a price for a product.
     *
     * @param  array{
     *     product: string,
     *     currency: string,
     *     unit_amount: int,
     *     recurring?: array{interval: string, interval_count?: int},
     *     metadata?: array
     * }  $data
     * @return array{id: string, ...}
     */
    public function createPrice(array $data): array;

    /**
     * Update a price (limited - mainly for deactivating).
     *
     * @param  array{active?: bool, metadata?: array}  $data
     */
    public function updatePrice(string $priceId, array $data): array;

    /**
     * List all prices for a product.
     *
     * @return array<array{id: string, ...}>
     */
    public function listPrices(string $productId, bool $activeOnly = true): array;

    /**
     * Archive (deactivate) a price.
     */
    public function archivePrice(string $priceId): bool;
}
