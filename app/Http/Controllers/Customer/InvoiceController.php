<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Central\PaymentResource;
use App\Models\Central\Payment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    /**
     * Display a list of invoices (payments).
     */
    public function index(Request $request): Response
    {
        $customer = $request->user('customer');

        $payments = Payment::where('customer_id', $customer->id)
            ->whereIn('status', ['paid', 'refunded', 'failed'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return Inertia::render('customer/invoices/index', [
            'invoices' => PaymentResource::collection($payments),
        ]);
    }

    /**
     * Display the specified invoice.
     */
    public function show(Request $request, Payment $invoice): Response
    {
        $customer = $request->user('customer');

        // Verify ownership
        if ($invoice->customer_id !== $customer->id) {
            abort(404);
        }

        // Load relationships
        $invoice->load(['paymentMethod', 'tenant']);

        return Inertia::render('customer/invoices/show', [
            'invoice' => [
                'id' => $invoice->id,
                'number' => $this->generateInvoiceNumber($invoice),
                'date' => $invoice->created_at->toISOString(),
                'paid_at' => $invoice->paid_at?->toISOString(),
                'due_date' => $invoice->expires_at?->toISOString(),
                'amount' => $invoice->amount,
                'amount_formatted' => $invoice->getFormattedAmount(),
                'fee' => $invoice->fee,
                'fee_formatted' => $this->formatMoney($invoice->fee, $invoice->currency),
                'net_amount' => $invoice->amount - $invoice->fee,
                'net_amount_formatted' => $this->formatMoney($invoice->amount - $invoice->fee, $invoice->currency),
                'currency' => $invoice->currency,
                'status' => $this->mapStatus($invoice->status),
                'payment_type' => $invoice->payment_type,
                'provider' => $invoice->provider,
                'description' => $invoice->description,
                'failure_message' => $invoice->failure_message,
                'is_refundable' => $invoice->isRefundable(),
                'amount_refunded' => $invoice->amount_refunded,
                'amount_refunded_formatted' => $this->formatMoney($invoice->amount_refunded, $invoice->currency),
                'payment_method' => $invoice->paymentMethod ? [
                    'type' => $invoice->paymentMethod->type,
                    'brand' => $invoice->paymentMethod->brand,
                    'last4' => $invoice->paymentMethod->last4,
                ] : null,
                'tenant' => $invoice->tenant ? [
                    'id' => $invoice->tenant->id,
                    'name' => $invoice->tenant->name,
                ] : null,
                'lines' => $this->getInvoiceLines($invoice),
            ],
        ]);
    }

    /**
     * Download the specified invoice as PDF.
     */
    public function download(Request $request, Payment $invoice): HttpResponse
    {
        $customer = $request->user('customer');

        // Verify ownership
        if ($invoice->customer_id !== $customer->id) {
            abort(404);
        }

        // Generate PDF receipt
        $pdf = $this->generatePdf($invoice);

        $filename = 'invoice-' . $this->generateInvoiceNumber($invoice) . '.pdf';

        return new StreamedResponse(
            function () use ($pdf) {
                echo $pdf;
            },
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }

    /**
     * Generate a display invoice number.
     */
    protected function generateInvoiceNumber(Payment $payment): string
    {
        $date = $payment->created_at->format('Ymd');
        $shortId = strtoupper(substr($payment->id, 0, 8));

        return "INV-{$date}-{$shortId}";
    }

    /**
     * Map internal status to display status.
     */
    protected function mapStatus(string $status): string
    {
        return match ($status) {
            'paid' => 'paid',
            'pending', 'processing' => 'open',
            'failed' => 'failed',
            'refunded' => 'refunded',
            'expired', 'canceled' => 'void',
            default => $status,
        };
    }

    /**
     * Format money for display.
     */
    protected function formatMoney(int $cents, string $currency = 'BRL'): string
    {
        $value = $cents / 100;
        $symbol = match ($currency) {
            'BRL' => 'R$',
            'USD' => '$',
            'EUR' => '€',
            default => $currency . ' ',
        };

        return $symbol . ' ' . number_format($value, 2, ',', '.');
    }

    /**
     * Get invoice line items.
     */
    protected function getInvoiceLines(Payment $payment): array
    {
        // For now, return a single line item based on description
        // In the future, this could be expanded to support multiple line items
        return [
            [
                'description' => $payment->description ?? __('billing.payment'),
                'quantity' => 1,
                'amount' => $payment->amount,
                'amount_formatted' => $payment->getFormattedAmount(),
            ],
        ];
    }

    /**
     * Generate PDF for invoice.
     *
     * This is a simple implementation. In production, you might want to use
     * a proper PDF library like DomPDF or Laravel Snappy.
     */
    protected function generatePdf(Payment $payment): string
    {
        // Simple HTML-based receipt
        // In production, use a proper PDF generator
        $html = view('receipts.payment', [
            'payment' => $payment,
            'invoiceNumber' => $this->generateInvoiceNumber($payment),
        ])->render();

        // If DomPDF is available, use it
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->output();
        }

        // Fallback: return HTML (browser will handle it)
        return $html;
    }
}
