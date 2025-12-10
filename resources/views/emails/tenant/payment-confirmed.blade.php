<x-mail::message>
# {{ __('billing.email.payment_confirmed_title') }}

{{ __('billing.email.payment_confirmed_intro', ['product' => $productName]) }}

<x-mail::panel>
## {{ __('billing.email.payment_details') }}

**{{ __('billing.email.product') }}:** {{ $productName }}

**{{ __('billing.email.amount') }}:** {{ $amount }}

**{{ __('billing.email.payment_method') }}:** {{ __('billing.payment_methods.' . $paymentMethod) }}

**{{ __('billing.email.paid_at') }}:** {{ $paidAt }}
</x-mail::panel>

<x-mail::button :url="config('app.url') . '/admin/billing'" color="primary">
{{ __('billing.email.view_billing') }}
</x-mail::button>

{{ __('billing.email.thanks') }}<br>
{{ config('app.name') }}
</x-mail::message>
