<x-layouts.customer title="Ambil Antrean - SatSet" :show-header="false">
    <x-customer.app-header title="Ambil Antrean" eyebrow="Web Notification" :fallback="route('restaurants.show', $restaurant)" />

    <section class="customer-narrow-page">
        <article class="queue-join-summary customer-content-card">
            <p class="eyebrow">Flow SatSet</p>
            <h1>Antrean dibuat langsung dari web.</h1>
            <p>Pilih tombol antrean dari halaman restoran. Setelah tiket muncul, aktifkan notifikasi agar SatSet memberi tahu saat giliran mendekat.</p>

            <div class="stats-grid compact-stats">
                <div><span>Restoran</span><strong>{{ $restaurant->name }}</strong></div>
                <div><span>Estimasi saat ini</span><strong>{{ $snapshot['estimated_wait'] }} menit</strong></div>
            </div>

            <form method="post" action="{{ route('queue.store', $restaurant) }}">
                @csrf
                <button class="button" type="submit">Ambil Antrean</button>
            </form>
        </article>
    </section>
</x-layouts.customer>
