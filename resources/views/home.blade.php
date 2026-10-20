<x-layouts.customer title="SatSet | Virtual Queue for F&B">
    <section class="home-hero">
        <div class="hero-content">
            <p class="eyebrow">VIRTUAL QUEUE FOR F&amp;B</p>
            <h1>SatSet<span class="accent">.</span></h1>
            <p class="hero-title">Antre tanpa harus<br>berdiri menunggu.</p>
            <p class="hero-description">Scan QR di tempat makan favorit Anda. Daftar dan tunggu kabar giliran lewat WhatsApp.</p>
            <a class="button button-green" href="#outlets">Temukan tempat makan <span aria-hidden="true">&darr;</span></a>
            <div class="hero-notes"><span>Tanpa akun</span><span>Tanpa install aplikasi</span></div>
        </div>
    </section>
    <section class="content-shell outlet-directory" id="outlets">
        <div class="section-heading"><div><p class="eyebrow">PILIH TEMPATNYA</p><h2>Selera Anda. Waktu Anda.</h2></div><span>{{ $restaurants->count() }} outlet tersedia</span></div>
        <form class="search-form" action="{{ route('home') }}#outlets" method="get" role="search">
            <label class="sr-only" for="outlet-search">Cari nama outlet atau menu</label>
            <input id="outlet-search" type="search" name="q" value="{{ $search }}" placeholder="Cari restoran, cafe, atau makanan..." autocomplete="off">
            <button class="button button-dark" type="submit">Cari</button>
            @if($search)<a class="text-link" href="{{ route('home') }}#outlets">Reset</a>@endif
        </form>
        <div class="restaurant-grid">
            @forelse($restaurants as $restaurant)
                <x-customer.outlet-card :restaurant="$restaurant" />
            @empty
                <div class="empty-state"><h3>Belum ada outlet yang cocok</h3><p>Coba nama tempat atau jenis makanan lainnya.</p></div>
            @endforelse
        </div>
    </section>
</x-layouts.customer>
