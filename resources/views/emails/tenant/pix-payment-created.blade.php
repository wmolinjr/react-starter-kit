<x-mail::message>
# {{ __('billing.email.pix_payment_title') }}

{{ __('billing.email.pix_payment_intro', ['product' => $productName, 'amount' => $amount]) }}

<x-mail::panel>
## {{ __('billing.email.pix_qr_code') }}

@if($qrCodeBase64)
<div style="text-align: center; margin: 20px 0;">
<img src="data:image/png;base64,{{ $qrCodeBase64 }}" alt="PIX QR Code" style="max-width: 200px; height: auto;" />
</div>
@endif

{{ __('billing.email.pix_copy_paste_instruction') }}

<x-mail::panel style="background-color: #f3f4f6; padding: 12px; border-radius: 6px; word-break: break-all; font-family: monospace; font-size: 12px;">
{{ $copyPasteCode }}
</x-mail::panel>
</x-mail::panel>

<x-mail::panel>
**{{ __('billing.email.expires_at') }}:** {{ $expiresAt }}
</x-mail::panel>

<x-mail::subcopy>
{{ __('billing.email.pix_expires_warning') }}
</x-mail::subcopy>

{{ __('billing.email.thanks') }}<br>
{{ config('app.name') }}
</x-mail::message>
