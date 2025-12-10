<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Admin;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\Central\PaymentAdminResource;
use App\Models\Central\AddonPurchase;
use App\Models\Central\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
    ) {}

    /**
     * Display a listing of all payments.
     */
    public function index(Request $request): Response
    {
        $query = Payment::query()
            ->with(['tenant', 'customer'])
            ->latest('created_at');

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by provider
        if ($request->filled('provider')) {
            $query->where('provider', $request->input('provider'));
        }

        // Filter by payment method
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->input('payment_method'));
        }

        // Filter by date range
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        // Search by tenant name or customer email
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('tenant', function ($tq) use ($search) {
                    $tq->where('id', 'like', "%{$search}%");
                })->orWhereHas('customer', function ($cq) use ($search) {
                    $cq->where('email', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            });
        }

        $payments = $query->paginate(20)->withQueryString();

        // Get statistics
        $stats = [
            'total_amount' => Payment::sum('amount'),
            'pending_count' => Payment::where('status', 'pending')->count(),
            'completed_count' => Payment::where('status', 'succeeded')->count(),
            'failed_count' => Payment::where('status', 'failed')->count(),
            'refunded_amount' => Payment::where('status', 'refunded')->sum('refunded_amount'),
        ];

        return Inertia::render('central/admin/payments/index', [
            'payments' => PaymentAdminResource::collection($payments),
            'filters' => $request->only(['status', 'provider', 'payment_method', 'from', 'to', 'search']),
            'stats' => $stats,
        ]);
    }

    /**
     * Display the specified payment.
     */
    public function show(Payment $payment): Response
    {
        $payment->load(['tenant', 'customer', 'paymentMethod']);

        // Get related purchase if exists
        $purchase = AddonPurchase::where('provider_payment_id', $payment->provider_payment_id)->first();
        if ($purchase) {
            $purchase->load(['addon', 'bundle']);
        }

        return Inertia::render('central/admin/payments/show', [
            'payment' => new PaymentAdminResource($payment),
            'purchase' => $purchase,
        ]);
    }

    /**
     * Process a refund for a payment.
     */
    public function refund(Request $request, Payment $payment): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'amount' => ['nullable', 'integer', 'min:1', 'max:'.($payment->amount - $payment->refunded_amount)],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $amount = $request->input('amount', $payment->amount - $payment->refunded_amount);

        if ($payment->status !== 'succeeded') {
            return back()->withErrors(['payment' => __('payments.cannot_refund_status')]);
        }

        if ($payment->refunded_amount >= $payment->amount) {
            return back()->withErrors(['payment' => __('payments.already_fully_refunded')]);
        }

        try {
            $result = $this->gateway->refund(
                paymentId: $payment->provider_payment_id,
                amount: $amount,
                options: [
                    'reason' => $request->input('reason', 'requested_by_admin'),
                ]
            );

            if ($result->success) {
                $payment->update([
                    'refunded_amount' => $payment->refunded_amount + $amount,
                    'status' => $payment->refunded_amount + $amount >= $payment->amount ? 'refunded' : 'partially_refunded',
                    'provider_data' => array_merge($payment->provider_data ?? [], [
                        'refund' => [
                            'amount' => $amount,
                            'reason' => $request->input('reason'),
                            'refund_id' => $result->providerRefundId,
                            'processed_at' => now()->toIso8601String(),
                        ],
                    ]),
                ]);

                Log::info('Payment refunded', [
                    'payment_id' => $payment->id,
                    'amount' => $amount,
                    'admin_id' => auth('central')->id(),
                ]);

                return back()->with('success', __('payments.refund_success'));
            }

            return back()->withErrors(['payment' => $result->failureMessage ?? __('payments.refund_failed')]);
        } catch (\Exception $e) {
            Log::error('Payment refund failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['payment' => __('payments.refund_error')]);
        }
    }

    /**
     * Export payments to CSV.
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $query = Payment::query()
            ->with(['tenant', 'customer'])
            ->latest('created_at');

        // Apply same filters as index
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('provider')) {
            $query->where('provider', $request->input('provider'));
        }
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->input('payment_method'));
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        $payments = $query->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="payments-'.now()->format('Y-m-d').'.csv"',
        ];

        return response()->stream(function () use ($payments) {
            $handle = fopen('php://output', 'w');

            // Header row
            fputcsv($handle, [
                'ID',
                'Data',
                'Tenant',
                'Cliente',
                'Valor',
                'Status',
                'Método',
                'Provider',
                'Provider ID',
            ]);

            // Data rows
            foreach ($payments as $payment) {
                fputcsv($handle, [
                    $payment->id,
                    $payment->created_at->format('Y-m-d H:i:s'),
                    $payment->tenant?->id ?? 'N/A',
                    $payment->customer?->email ?? 'N/A',
                    number_format($payment->amount / 100, 2, ',', '.'),
                    $payment->status,
                    $payment->payment_method,
                    $payment->provider,
                    $payment->provider_payment_id,
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }
}
