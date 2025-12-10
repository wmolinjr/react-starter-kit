<?php

declare(strict_types=1);

namespace App\Jobs\Central;

use App\Mail\Tenant\BoletoReminder;
use App\Models\Central\AddonPurchase;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends reminder emails for pending boleto payments.
 *
 * This job runs daily and sends reminders for boletos:
 * - 3 days before due date
 * - 1 day before due date
 * - On due date
 * - 1 day after due date (overdue warning)
 */
class SendBoletoRemindersJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 300;

    /**
     * Days before due date to send reminders.
     *
     * @var array<int>
     */
    private const REMINDER_DAYS = [3, 1, 0, -1];

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly ?int $specificDays = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $daysToCheck = $this->specificDays !== null
            ? [$this->specificDays]
            : self::REMINDER_DAYS;

        foreach ($daysToCheck as $daysUntilDue) {
            $this->sendRemindersForDay($daysUntilDue);
        }
    }

    /**
     * Send reminders for a specific number of days until due.
     */
    private function sendRemindersForDay(int $daysUntilDue): void
    {
        $targetDate = Carbon::today()->addDays($daysUntilDue);

        $purchases = $this->getPendingBoletoPurchases($targetDate);

        $sentCount = 0;
        foreach ($purchases as $purchase) {
            if ($this->shouldSendReminder($purchase, $daysUntilDue)) {
                $this->sendReminder($purchase, $daysUntilDue);
                $sentCount++;
            }
        }

        if ($sentCount > 0) {
            Log::info('SendBoletoRemindersJob: Sent reminders', [
                'days_until_due' => $daysUntilDue,
                'sent_count' => $sentCount,
                'target_date' => $targetDate->toDateString(),
            ]);
        }
    }

    /**
     * Get pending boleto purchases for a specific due date.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, AddonPurchase>
     */
    private function getPendingBoletoPurchases(Carbon $dueDate): \Illuminate\Database\Eloquent\Collection
    {
        return AddonPurchase::query()
            ->where('status', 'pending')
            ->where('provider', 'asaas')
            ->whereNotNull('provider_data')
            ->whereJsonContains('provider_data->payment_method', 'boleto')
            ->whereDate('provider_data->boleto->due_date', $dueDate)
            ->with(['tenant', 'addon', 'bundle'])
            ->get();
    }

    /**
     * Check if we should send a reminder for this purchase.
     */
    private function shouldSendReminder(AddonPurchase $purchase, int $daysUntilDue): bool
    {
        // Get the tenant owner's email
        $tenant = $purchase->tenant;
        if (! $tenant) {
            return false;
        }

        // Check if we already sent a reminder today
        $reminderKey = "boleto_reminder_{$purchase->id}_{$daysUntilDue}";
        $sentReminders = $purchase->provider_data['sent_reminders'] ?? [];

        if (in_array($reminderKey, $sentReminders, true)) {
            return false;
        }

        return true;
    }

    /**
     * Send a reminder for a specific purchase.
     */
    private function sendReminder(AddonPurchase $purchase, int $daysUntilDue): void
    {
        $tenant = $purchase->tenant;
        if (! $tenant) {
            return;
        }

        // Get the billing email (owner or customer)
        $billingEmail = $this->getBillingEmail($purchase);
        if (! $billingEmail) {
            Log::warning('SendBoletoRemindersJob: No billing email found', [
                'purchase_id' => $purchase->id,
                'tenant_id' => $tenant->id,
            ]);

            return;
        }

        // Extract boleto data
        $providerData = $purchase->provider_data ?? [];
        $boletoData = $providerData['boleto'] ?? [];

        $boletoUrl = $boletoData['bank_slip_url'] ?? $boletoData['url'] ?? '';
        $barcode = $boletoData['identification_field'] ?? $boletoData['digitable_line'] ?? $boletoData['barcode'] ?? '';

        if (! $boletoUrl || ! $barcode) {
            Log::warning('SendBoletoRemindersJob: Missing boleto data', [
                'purchase_id' => $purchase->id,
                'has_url' => ! empty($boletoUrl),
                'has_barcode' => ! empty($barcode),
            ]);

            return;
        }

        // Send the email
        Mail::to($billingEmail)->send(
            new BoletoReminder(
                purchase: $purchase,
                boletoUrl: $boletoUrl,
                barcode: $barcode,
                daysUntilDue: $daysUntilDue,
            )
        );

        // Mark reminder as sent
        $this->markReminderSent($purchase, $daysUntilDue);

        Log::info('SendBoletoRemindersJob: Reminder sent', [
            'purchase_id' => $purchase->id,
            'email' => $billingEmail,
            'days_until_due' => $daysUntilDue,
        ]);
    }

    /**
     * Get the billing email for a purchase.
     */
    private function getBillingEmail(AddonPurchase $purchase): ?string
    {
        $tenant = $purchase->tenant;

        // First, try the customer email
        if ($tenant->customer) {
            return $tenant->customer->email;
        }

        // Fall back to tenant owner
        tenancy()->initialize($tenant);

        try {
            $owner = \App\Models\Tenant\User::query()
                ->role('owner')
                ->first();

            return $owner?->email;
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Mark a reminder as sent to avoid duplicates.
     */
    private function markReminderSent(AddonPurchase $purchase, int $daysUntilDue): void
    {
        $reminderKey = "boleto_reminder_{$purchase->id}_{$daysUntilDue}";
        $providerData = $purchase->provider_data ?? [];
        $sentReminders = $providerData['sent_reminders'] ?? [];

        $sentReminders[] = $reminderKey;
        $providerData['sent_reminders'] = $sentReminders;

        $purchase->update(['provider_data' => $providerData]);
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['billing', 'boleto-reminders'];
    }
}
