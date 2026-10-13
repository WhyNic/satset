@props(['restaurant', 'variant' => 'card', 'loading' => 'lazy'])

@php
    $fallbackImages = [
        'A' => 'images/restaurants/ayam-bakar.jpg',
        'B' => 'images/restaurants/ayam-geprek.jpg',
        'C' => 'images/restaurants/rice-bowl.jpg',
    ];

    $image = $restaurant->image ?: ($fallbackImages[strtoupper((string) $restaurant->prefix)] ?? 'images/restaurants/fallback.jpg');
    $imageUrl = \Illuminate\Support\Str::startsWith($image, ['http://', 'https://'])
        ? $image
        : asset(ltrim($image, '/'));
@endphp

<div {{ $attributes->class(['restaurant-image', 'restaurant-image-'.$variant]) }}>
    <img src="{{ $imageUrl }}" alt="Foto {{ $restaurant->name }}" loading="{{ $loading }}">
    <span>{{ $restaurant->category }}</span>
</div>
