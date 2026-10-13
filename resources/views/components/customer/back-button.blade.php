@props(['fallback' => null])

@php($fallbackUrl = $fallback ?? route('home'))

<button
    type="button"
    class="customer-back-button"
    aria-label="Kembali"
    data-back-button
    data-fallback-url="{{ $fallbackUrl }}"
>
    <svg aria-hidden="true" viewBox="0 0 24 24" focusable="false">
        <path d="M15 19 8 12l7-7" />
    </svg>
</button>
