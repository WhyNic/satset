@props(['title' => 'SatSet', 'showHeader' => true])
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0b2118">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>
    <link rel="icon" href="{{ asset('icons/icon-192.png') }}">
    <link rel="stylesheet" href="{{ asset('css/satset.css') }}?v=qr-1">
    <script src="{{ asset('js/customer.js') }}?v=qr-1" defer></script>
</head>
<body>
    <a class="skip-link" href="#main">Lewati navigasi</a>
    <header class="topbar">
        <div class="nav-inner">
            <a class="brand" href="{{ route('home') }}" aria-label="SatSet beranda"><span class="brand-mark">S</span>SatSet<span class="brand-caption">VIRTUAL QUEUE</span></a>
            <nav aria-label="Navigasi utama"><a href="{{ route('home') }}#outlets">Temukan outlet <span aria-hidden="true">&nearr;</span></a></nav>
        </div>
    </header>
    <main id="main">
        @if(session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
        {{ $slot }}
    </main>
    <footer class="footer"><a class="brand" href="{{ route('home') }}">SatSet</a><span>Waktu Anda lebih berharga.</span><small>Virtual queue untuk restoran, cafe, dan tempat makan.</small><a class="text-link" href="{{ route('privacy') }}">Kebijakan privasi</a></footer>
</body>
</html>
