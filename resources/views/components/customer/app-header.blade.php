@props(['title', 'fallback' => null, 'eyebrow' => null])

@php($fallbackUrl = $fallback ?? route('home'))

<header class="customer-app-header">
    <x-customer.back-button :fallback="$fallbackUrl" />
    <div class="customer-app-header-title">
        <strong>{{ $title }}</strong>
        @if ($eyebrow)
            <span>{{ $eyebrow }}</span>
        @endif
    </div>
    <span class="customer-app-header-spacer" aria-hidden="true"></span>
</header>
