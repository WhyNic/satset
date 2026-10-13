@props(['status'])

@php
    $class = match ($status) {
        'Normal' => 'badge-normal',
        'Padat' => 'badge-warning',
        'Terlalu Padat' => 'badge-danger',
        default => 'badge-muted',
    };
@endphp

<span class="badge {{ $class }}">{{ $status }}</span>
