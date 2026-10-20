<x-layouts.customer title="Tempat makan lainnya | SatSet">
    <section class="content-shell outlet-directory">
        <a class="text-link" href="{{ route('outlets.show', $restaurant->slug) }}">&larr; {{ $restaurant->name }}</a>
        <div class="section-heading"><div><p class="eyebrow">PILIHAN LAIN</p><h1>Selera serupa, waktu berbeda.</h1></div></div>
        <div class="restaurant-grid">
            @forelse($recommendations as $recommendation)
                <x-customer.outlet-card :restaurant="$recommendation" />
            @empty
                <div class="empty-state"><h2>Belum ada rekomendasi</h2><a class="text-link" href="{{ route('home') }}#outlets">Lihat seluruh outlet</a></div>
            @endforelse
        </div>
    </section>
</x-layouts.customer>
