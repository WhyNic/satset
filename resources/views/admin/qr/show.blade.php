<x-layouts.admin :title="'QR '.$restaurant->name.' | SatSet'">
    <section class="admin-page-header no-print"><div><p class="admin-kicker">QR Code outlet</p><h1>{{ $restaurant->name }}</h1></div><a class="admin-secondary-button" href="{{ route('admin.qr.index') }}">Semua QR</a></section>
    @if(in_array(parse_url($url, PHP_URL_HOST), ['localhost','127.0.0.1','::1'], true))
        <div class="admin-notice no-print">QR ini memakai alamat lokal komputer. Untuk scan dari HP, atur APP_URL ke alamat LAN atau domain HTTPS yang dapat diakses HP sebelum mencetak.</div>
    @endif
    <section class="qr-poster">
        <span class="qr-brand">SatSet<span>.</span></span>
        <p class="admin-kicker">ANTRE TANPA BERDIRI MENUNGGU</p>
        <h2>{{ $restaurant->name }}</h2>
        <img src="{{ route('admin.qr.image', $restaurant) }}" width="360" height="360" alt="QR antrean {{ $restaurant->name }}">
        <h3>Scan. Daftar. Santai.</h3>
        <p>Scan QR, isi jumlah tamu dan email.<br>Kami kirim email saat giliran Anda.</p>
        <small>{{ $url }}</small>
    </section>
    <div class="qr-actions no-print">
        <a class="admin-primary-button" href="{{ route('admin.qr.image', [$restaurant, 'download'=>1]) }}">Download SVG</a>
        <button class="admin-secondary-button" type="button" data-print-qr>Cetak QR</button>
        <button class="admin-secondary-button" type="button" data-copy-url="{{ $url }}">Salin URL</button>
        <a class="admin-secondary-button" href="{{ $url }}" target="_blank" rel="noopener">Buka outlet</a>
        <span role="status" data-copy-status></span>
    </div>
    <label class="qr-url-label no-print">URL outlet<input readonly value="{{ $url }}" aria-label="URL outlet"></label>
</x-layouts.admin>
