<x-layouts.customer title="Rekomendasi - SatSet" :show-header="false">
    <x-customer.app-header title="Rekomendasi" :fallback="route('restaurants.show', $restaurant)" />

    <section class="customer-narrow-page">
        <article class="customer-content-card recommendation-hero">
            <p class="eyebrow">Antrean cukup panjang</p>
            <h1>Estimasi waktu tunggu {{ $restaurant->name }} saat ini {{ $snapshot['estimated_wait'] }} menit.</h1>
            <p>SatSet menyarankan restoran serupa dengan antrean lebih singkat supaya kamu tetap bisa makan tanpa menunggu terlalu lama.</p>
            <form method="post" action="{{ route('queue.store', $restaurant) }}">
                @csrf
                <button class="subtle-button" type="submit">Tetap pilih {{ $restaurant->name }}</button>
            </form>
        </article>

        <section class="section-heading compact-heading">
            <div>
                <h2>Restoran serupa dengan antrean lebih singkat</h2>
                <p>Diurutkan dari estimasi tunggu paling cepat.</p>
            </div>
        </section>

        <div class="recommendation-list">
            @forelse ($recommendations as $recommendation)
                @php($recSnapshot = $recommendation->getAttribute('queue_snapshot'))
                <form class="recommendation-form" method="post" action="{{ route('queue.store', $recommendation) }}">
                    @csrf
                    <button class="recommendation-card" type="submit">
                        <x-customer.restaurant-image :restaurant="$recommendation" variant="thumb" />
                        <div>
                            <strong>{{ $recommendation->name }}</strong>
                            <span>{{ $recommendation->category }}</span>
                        </div>
                        <div class="recommendation-meta">
                            <x-status-badge :status="$recSnapshot['status']" />
                            <span>{{ $recSnapshot['estimated_wait'] }} menit</span>
                        </div>
                        <b>Pilih Restoran</b>
                    </button>
                </form>
            @empty
                <div class="empty-state">Belum ada rekomendasi kategori serupa. Kamu tetap bisa memilih restoran awal.</div>
            @endforelse
        </div>
    </section>
</x-layouts.customer>
