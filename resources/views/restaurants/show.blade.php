<x-layouts.customer title="{{ $restaurant->name }} - SatSet" :show-header="false">
    <x-customer.app-header title="Detail Restoran" :fallback="route('home')" />

    <section class="customer-detail-page">
        <x-customer.restaurant-image :restaurant="$restaurant" variant="hero" loading="eager" class="restaurant-hero-card" />

        <article class="customer-content-card">
            <div class="detail-title-row">
                <x-status-badge :status="$snapshot['status']" />
                @if ($snapshot['estimated_wait'] > $restaurant->max_wait_minutes)
                    <span class="soft-warning">Antrean panjang</span>
                @endif
            </div>

            <h1>{{ $restaurant->name }}</h1>
            <p class="lead-text">{{ $restaurant->category }} · {{ $restaurant->description }}</p>

            <div class="stats-grid compact-stats">
                <div><span>Antrean aktif</span><strong>{{ $snapshot['active_count'] }}</strong></div>
                <div><span>Estimasi</span><strong>{{ $snapshot['estimated_wait'] }} menit</strong></div>
                <div><span>Rata-rata servis</span><strong>{{ $restaurant->average_service_minutes }} menit</strong></div>
            </div>

            @if ($snapshot['estimated_wait'] > $restaurant->max_wait_minutes)
                <div class="inline-alert">
                    <strong>Antrean sedang padat.</strong>
                    <span>SatSet bisa bantu cari restoran kategori serupa dengan estimasi lebih singkat.</span>
                    <a href="{{ route('restaurants.recommendations', $restaurant) }}">Lihat rekomendasi</a>
                </div>
            @endif

            <div class="cta-stack sticky-mobile-actions">
                <form method="post" action="{{ route('queue.store', $restaurant) }}">
                    @csrf
                    <button class="button" type="submit">Ambil Antrean</button>
                </form>
                <p>Nomor antrean dibuat langsung di SatSet. Aktifkan notifikasi setelah tiket muncul agar tidak melewatkan giliran.</p>
            </div>

            <p class="microcopy">Tidak perlu install aplikasi. Tiket digital langsung aktif setelah data tersimpan.</p>
        </article>
    </section>
</x-layouts.customer>
