<x-mail::message>
# {{ __('billing.email.boleto_payment_title') }}

{{ __('billing.email.boleto_payment_intro', ['product' => $productName, 'amount' => $amount]) }}

<x-mail::panel>
## {{ __('billing.email.boleto_details') }}

**{{ __('billing.email.amount') }}:** {{ $amount }}

**{{ __('billing.email.due_date') }}:** {{ $dueDate }}

**{{ __('billing.email.barcode') }}:**
<div style="background-color: #f3f4f6; padding: 12px; border-radius: 6px; word-break: break-all; font-family: monospace; font-size: 12px; margin-top: 8px;">
{{ $barcode }}
</div>
</x-mail::panel>

<x-mail::button :url="$boletoUrl" color="primary">
{{ __('billing.email.view_boleto') }}
</x-mail::button>

<x-mail::subcopy>
{{ __('billing.email.boleto_payment_instructions') }}
</x-mail::subcopy>

{{ __('billing.email.thanks') }}<br>
{{ config('app.name') }}
</x-mail::message>
