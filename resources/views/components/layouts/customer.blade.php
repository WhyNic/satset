@props(['title' => 'SatSet', 'showHeader' => true])

<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" media="(prefers-color-scheme: light)" content="#07130D">
    <meta name="theme-color" media="(prefers-color-scheme: dark)" content="#0F172A">
    <title>{{ $title }}</title>
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/satset.css') }}">
    <script src="{{ asset('js/customer.js') }}" defer></script>
</head>
<body>
    <div class="ambient-bg" aria-hidden="true">
        @foreach ([
            ['bowl', 'food-float-one'],
            ['burger', 'food-float-two'],
            ['fries', 'food-float-three'],
            ['cup', 'food-float-four'],
            ['box', 'food-float-five'],
            ['chili', 'food-float-six'],
            ['plate', 'food-float-seven'],
            ['chicken', 'food-float-eight'],
            ['coffee', 'food-float-nine'],
            ['cutlery', 'food-float-ten'],
        ] as [$icon, $class])
            <svg class="food-float {{ $class }}" viewBox="0 0 64 64" aria-hidden="true">
                <use href="{{ asset('images/food-symbols.svg') }}#{{ $icon }}"></use>
            </svg>
        @endforeach
    </div>
    @if ($showHeader)
        <header class="topbar">
            <a class="brand" href="{{ route('home') }}">
                <span class="brand-mark">S</span>
                <span>
                    SatSet
                    <small>Antre lebih cerdas</small>
                </span>
            </a>
            <nav class="customer-nav" aria-label="Customer navigation">
                <a href="{{ route('home') }}">Beranda</a>
                <a href="{{ route('home') }}#restaurants">Restoran</a>
            </nav>
            <a class="topbar-cta" href="{{ route('home') }}#restaurants">Cari Restoran</a>
        </header>
    @endif

    <main class="page">
        @if (session('status'))
            <div class="notice">{{ session('status') }}</div>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
