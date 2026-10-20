<x-layouts.admin :title="'QR '.$restaurant->name.' | SatSet Admin'">
    <header class="admin-page-header no-print">
        <div>
            <a class="admin-back" href="{{ route('admin.qr.index') }}">&larr; Semua QR</a>
            <h1>QR {{ $restaurant->name }}</h1>
        </div>
    </header>

    @if(in_array(parse_url($url, PHP_URL_HOST), ['localhost','127.0.0.1','::1'], true))
        <div class="admin-notice admin-notice-warning no-print">QR ini memakai alamat lokal komputer sehingga tidak bisa dibuka dari HP. Jalankan <code>php artisan satset:lan</code> lalu buka halaman ini lewat alamat jaringan yang ditampilkan, atau atur APP_URL ke domain HTTPS sebelum mencetak.</div>
    @endif

    <div class="admin-qr-layout">
        <section class="qr-poster">
            <span class="qr-brand">SatSet<span>.</span></span>
            <p class="qr-tagline">Antre tanpa berdiri menunggu</p>
            <h2>{{ $restaurant->name }}</h2>
            <img src="{{ route('admin.qr.image', $restaurant) }}" width="320" height="320" alt="QR antrean {{ $restaurant->name }}">
            <h3>Scan. Daftar. Santai.</h3>
            <p>Scan QR, lanjutkan lewat WhatsApp.<br>Kami kabari saat giliran Anda.</p>
        </section>

        <aside class="admin-panel admin-qr-side no-print">
            <h2>Bagikan</h2>
            <label class="admin-form">URL outlet
                <input class="admin-mono" readonly value="{{ $url }}" data-url-input>
            </label>
            <div class="admin-qr-actions">
                <button class="admin-button admin-button-primary" type="button" data-print-qr>Cetak QR</button>
                <a class="admin-button admin-button-secondary" href="{{ route('admin.qr.image', [$restaurant, 'download' => 1]) }}">Download SVG</a>
                <button class="admin-button admin-button-ghost" type="button" data-copy-url="{{ $url }}">Salin URL</button>
                <a class="admin-button admin-button-ghost" href="{{ $url }}" target="_blank" rel="noopener">Buka halaman outlet</a>
            </div>
            <p class="admin-hint" role="status" data-copy-status></p>
        </aside>
    </div>
</x-layouts.admin>
