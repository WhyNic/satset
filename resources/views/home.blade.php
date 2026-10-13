<x-layouts.customer title="SatSet - Antre lebih cerdas">
    <section class="hero">
        <div class="hero-copy">
            <p class="eyebrow">SatSet ID</p>
            <h1>Antre lebih cerdas</h1>
            <p>Nikmati makanan favorit tanpa buang waktu.</p>
            <div class="hero-pills" aria-label="Keunggulan SatSet">
                <span>Live queue</span>
                <span>Web Notification</span>
                <span>Smart recommendation</span>
            </div>
        </div>
        <form class="search-box" method="get" action="{{ route('home') }}">
            <input type="search" name="q" value="{{ $search }}" placeholder="Cari restoran atau makanan">
            <button type="submit">Cari</button>
        </form>
    </section>

    <section class="section-heading" id="restaurants">
        <div>
            <h2>Daftar restoran bazaar</h2>
            <p>Pilih restoran, lihat estimasi, lalu ambil antrean virtual dari web. SatSet akan memberi notifikasi saat giliran mendekat.</p>
        </div>
    </section>

    <div class="restaurant-grid">
        @forelse ($restaurants as $restaurant)
            @php($snapshot = $restaurant->getAttribute('queue_snapshot'))
            <a class="restaurant-card" href="{{ route('restaurants.show', $restaurant) }}">
                <x-customer.restaurant-image :restaurant="$restaurant" />
                <div class="card-body">
                    <div class="card-title-row">
                        <h3>{{ $restaurant->name }}</h3>
                        <x-status-badge :status="$snapshot['status']" />
                    </div>
                    <p>{{ $restaurant->category }} · {{ $restaurant->description }}</p>
                    <div class="metrics">
                        <span><strong>{{ $snapshot['active_count'] }}</strong> antrean</span>
                        <span><strong>{{ $snapshot['estimated_wait'] }}</strong> menit</span>
                    </div>
                    <div class="card-footer">
                        <span>Lihat detail</span>
                        <b aria-hidden="true">&rarr;</b>
                    </div>
                </div>
            </a>
        @empty
            <div class="empty-state">Belum ada restoran yang cocok dengan pencarian.</div>
        @endforelse
    </div>
</x-layouts.customer>
