@props(['restaurant'])
@php($snapshot = $restaurant->getAttribute('queue_snapshot'))
<a class="restaurant-card" href="{{ route('outlets.show', $restaurant->slug) }}">
    <x-customer.restaurant-image :restaurant="$restaurant" />
    <div class="restaurant-card-content">
        <div class="card-topline"><span class="category">{{ $restaurant->category }}</span><span class="availability {{ $restaurant->queue_open ? '' : 'closed' }}">{{ $restaurant->queue_open ? 'Antrean dibuka' : 'Antrean ditutup' }}</span></div>
        <h3>{{ $restaurant->name }}</h3>
        <p>{{ $restaurant->description }}</p>
        <div class="card-stats"><span><strong>{{ $snapshot['waiting_count'] }}</strong> menunggu</span><span>Est. <strong>{{ $snapshot['estimated_wait'] }} menit</strong></span><span class="card-arrow" aria-hidden="true">&nearr;</span></div>
    </div>
</a>
