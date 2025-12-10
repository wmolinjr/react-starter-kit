<x-mail::message>
@if($isOverdue)
# {{ __('billing.email.boleto_overdue_title') }}

{{ __('billing.email.boleto_overdue_intro', ['product' => $productName, 'amount' => $amount]) }}
@else
# {{ __('billing.email.boleto_reminder_title') }}

{{ __('billing.email.boleto_reminder_intro', ['product' => $productName, 'days' => $daysUntilDue]) }}
@endif

<x-mail::panel>
## {{ __('billing.email.boleto_details') }}

**{{ __('billing.email.product') }}:** {{ $productName }}

**{{ __('billing.email.amount') }}:** {{ $amount }}

@if($dueDate)
**{{ __('billing.email.due_date') }}:** {{ $dueDate }}
@endif

**{{ __('billing.email.barcode') }}:**
<div style="background-color: #f3f4f6; padding: 12px; border-radius: 6px; word-break: break-all; font-family: monospace; font-size: 12px; margin-top: 8px;">
{{ $barcode }}
</div>
</x-mail::panel>

<x-mail::button :url="$boletoUrl" color="{{ $isOverdue ? 'error' : 'primary' }}">
{{ __('billing.email.pay_now') }}
</x-mail::button>

@if($isOverdue)
<x-mail::subcopy>
{{ __('billing.email.boleto_overdue_warning') }}
</x-mail::subcopy>
@endif

{{ __('billing.email.thanks') }}<br>
{{ config('app.name') }}
</x-mail::message>
