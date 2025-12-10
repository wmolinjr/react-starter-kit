<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class InvoiceController extends Controller
{
    /**
     * Display a list of invoices.
     */
    public function index(Request $request): Response
    {
        $customer = $request->user('customer');

        $invoices = collect($customer->invoices())->map(fn ($invoice) => [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'date' => $invoice->date()->toISOString(),
            'total' => $invoice->total(),
            'status' => $invoice->status,
            'description' => $invoice->description,
        ]);

        return Inertia::render('customer/invoices/index', [
            'invoices' => $invoices,
        ]);
    }

    /**
     * Display the specified invoice.
     */
    public function show(Request $request, string $invoice): Response
    {
        $customer = $request->user('customer');

        $invoiceObj = $customer->findInvoice($invoice);

        if (!$invoiceObj) {
            abort(404);
        }

        return Inertia::render('customer/invoices/show', [
            'invoice' => [
                'id' => $invoiceObj->id,
                'number' => $invoiceObj->number,
                'date' => $invoiceObj->date()->toISOString(),
                'due_date' => $invoiceObj->dueDate()?->toISOString(),
                'total' => $invoiceObj->total(),
                'subtotal' => $invoiceObj->subtotal(),
                'tax' => $invoiceObj->tax(),
                'status' => $invoiceObj->status,
                'description' => $invoiceObj->description,
                'lines' => collect($invoiceObj->lines->data)->map(fn ($line) => [
                    'description' => $line->description,
                    'amount' => $line->amount,
                    'quantity' => $line->quantity,
                ]),
            ],
        ]);
    }

    /**
     * Download the specified invoice as PDF.
     */
    public function download(Request $request, string $invoice): HttpResponse
    {
        $customer = $request->user('customer');

        return $customer->downloadInvoice($invoice, [
            'vendor' => config('app.name'),
            'product' => 'Subscription',
        ]);
    }
}
