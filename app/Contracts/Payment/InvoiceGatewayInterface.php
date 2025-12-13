<?php

declare(strict_types=1);

namespace App\Contracts\Payment;

use App\Models\Central\Customer;

/**
 * Interface for gateways that support invoice operations.
 */
interface InvoiceGatewayInterface
{
    /**
     * Retrieve an invoice by ID.
     *
     * @return array{id: string, status: string, amount_due: int, ...}
     */
    public function retrieveInvoice(string $invoiceId): array;

    /**
     * Get the upcoming invoice for a customer/subscription.
     *
     * @return array{amount_due: int, lines: array, ...}|null
     */
    public function getUpcomingInvoice(Customer $customer, ?string $subscriptionId = null): ?array;

    /**
     * List invoices for a customer.
     *
     * @return array<array{id: string, ...}>
     */
    public function listInvoices(Customer $customer, int $limit = 10): array;

    /**
     * Download invoice PDF URL.
     */
    public function getInvoicePdfUrl(string $invoiceId): ?string;
}
